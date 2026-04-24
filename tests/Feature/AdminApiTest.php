<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\LoeFeedback;
use App\Models\LoeEntry;
use App\Models\LoeReport;
use App\Models\Project;
use App\Models\User;
use App\Notifications\LoeFeedbackNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use InteractsWithRoles;
    use RefreshDatabase;

    public function test_employee_cannot_access_admin_endpoints(): void
    {
        $employee = $this->createUserWithRoles(['employee']);

        $this->actingAs($employee);

        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_admin_dashboard_returns_metrics_and_charts(): void
    {
        $admin = $this->createUserWithRoles(['admin']);
        $employee = $this->createUserWithRoles(['employee']);
        $project = Project::factory()->create();
        $report = LoeReport::factory()->create([
            'user_id' => $employee->id,
            'month' => now()->month,
            'year' => now()->year,
            'total_percentage' => 25,
        ]);
        LoeEntry::factory()->create([
            'loe_report_id' => $report->id,
            'project_id' => $project->id,
            'percentage' => 25,
        ]);

        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'metrics' => ['active_employees', 'active_projects', 'submitted_loe_reports', 'missing_submissions', 'current_allocation_total'],
                'charts' => ['project_allocation_headcount', 'engagement_distribution', 'submission_trend', 'allocation_vs_actual'],
                'exceptions',
            ]);

        $this->assertIsArray($response->json('charts.project_allocation_headcount'));
        $this->assertIsArray($response->json('charts.submission_trend'));
        $this->assertIsArray($response->json('charts.engagement_distribution'));
        $this->assertIsArray($response->json('charts.allocation_vs_actual'));
        $this->assertIsArray($response->json('exceptions'));
    }

    public function test_admin_can_crud_users(): void
    {
        $admin = $this->createUserWithRoles(['admin']);
        $employeeCode = 'EMP-'.fake()->unique()->numerify('9##');
        $this->actingAs($admin);

        $storeResponse = $this->postJson('/api/admin/users', [
            'name' => 'API User',
            'email' => 'api-user@example.com',
            'employee_code' => $employeeCode,
            'designation' => 'Engineer',
            'stream' => 'engineering',
            'timezone' => 'Asia/Karachi',
            'status' => true,
            'password' => 'Password@123',
            'roles' => ['employee'],
        ]);

        $storeResponse->assertCreated()->assertJsonPath('email', 'api-user@example.com');
        $userId = $storeResponse->json('id');

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment(['email' => 'api-user@example.com']);

        $this->getJson("/api/admin/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('id', $userId);

        $this->putJson("/api/admin/users/{$userId}", [
            'name' => 'Updated API User',
            'email' => 'api-user@example.com',
            'employee_code' => $employeeCode,
            'designation' => 'Lead Engineer',
            'stream' => 'engineering',
            'timezone' => 'Asia/Karachi',
            'status' => true,
            'password' => '',
            'roles' => ['employee', 'admin'],
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Updated API User');

        $this->deleteJson("/api/admin/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('message', 'User archived successfully.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'users',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_password_changes_are_not_captured_in_activity_logs(): void
    {
        $admin = $this->createUserWithRoles(['admin']);
        $user = $this->createUserWithRoles(['employee'], [
            'email' => 'safe-log-user@example.com',
        ]);

        $this->actingAs($admin);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'employee_code' => $user->employee_code,
            'designation' => $user->designation,
            'stream' => $user->stream,
            'timezone' => $user->timezone,
            'status' => true,
            'password' => 'ChangedPassword@123',
            'roles' => ['employee'],
        ])->assertOk();

        $activities = Activity::query()
            ->where('log_name', 'users')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->latest()
            ->get();

        $this->assertTrue($activities->isNotEmpty());
        $this->assertStringNotContainsString('ChangedPassword@123', $activities->pluck('properties')->toJson());
        $this->assertStringNotContainsString('password', $activities->pluck('properties')->toJson());
    }

    public function test_admin_can_view_user_loe_history(): void
    {
        $admin = $this->createUserWithRoles(['admin']);
        $employee = $this->createUserWithRoles(['employee'], [
            'name' => 'History User',
            'email' => 'history.user@example.com',
            'employee_code' => 'EMP-HISTORY-1',
        ]);
        $projectA = Project::factory()->create([
            'name' => 'Atlas',
            'engagement_type' => 'project',
        ]);
        $projectB = Project::factory()->create([
            'name' => 'Orbit',
            'engagement_type' => 'product',
        ]);
        $report = LoeReport::factory()->create([
            'user_id' => $employee->id,
            'month' => 3,
            'year' => 2026,
            'total_percentage' => 95,
        ]);
        LoeEntry::factory()->create([
            'loe_report_id' => $report->id,
            'project_id' => $projectA->id,
            'percentage' => 55,
        ]);
        LoeEntry::factory()->create([
            'loe_report_id' => $report->id,
            'project_id' => $projectB->id,
            'percentage' => 40,
        ]);

        $this->actingAs($admin);

        $this->getJson("/api/admin/users/{$employee->id}/loe-reports")
            ->assertOk()
            ->assertJsonPath('user.id', $employee->id)
            ->assertJsonPath('user.name', 'History User')
            ->assertJsonCount(1, 'reports')
            ->assertJsonPath('reports.0.month', 3)
            ->assertJsonPath('reports.0.year', 2026)
            ->assertJsonCount(2, 'reports.0.entries')
            ->assertJsonPath('reports.0.entries.0.project_name', 'Atlas')
            ->assertJsonPath('reports.0.entries.1.project_name', 'Orbit');

        $this->get("/api/admin/users/{$employee->id}/loe-reports/export?format=pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get("/api/admin/users/{$employee->id}/loe-reports/export?format=xlsx")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_admin_can_approve_a_users_loe(): void
    {
        $admin = $this->createUserWithRoles(['admin']);
        $employee = $this->createUserWithRoles(['employee']);
        $report = LoeReport::factory()->create([
            'user_id' => $employee->id,
            'month' => 4,
            'year' => 2026,
            'status' => 'submitted',
        ]);

        $this->actingAs($admin);

        $this->patchJson("/api/admin/users/{$employee->id}/loe-reports/{$report->id}/review", [
            'status' => 'approved',
            'review_notes' => 'Looks good for reporting.',
        ])
            ->assertOk()
            ->assertJsonPath('report.status', 'approved');

        $this->assertDatabaseHas('loe_reports', [
            'id' => $report->id,
            'status' => 'approved',
            'review_notes' => 'Looks good for reporting.',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_admin_can_leave_feedback_on_employee_loe_and_employee_is_notified(): void
    {
        Notification::fake();

        $admin = $this->createUserWithRoles(['admin']);
        $employee = $this->createUserWithRoles(['employee']);
        $project = Project::factory()->create();
        $report = LoeReport::factory()->create([
            'user_id' => $employee->id,
            'month' => 4,
            'year' => 2026,
            'total_percentage' => 80,
        ]);
        LoeEntry::factory()->create([
            'loe_report_id' => $report->id,
            'project_id' => $project->id,
            'percentage' => 80,
        ]);

        $this->actingAs($admin);

        $this->postJson("/api/loe-reports/{$report->id}/feedback", [
            'message' => 'Please clarify the allocation split for this month.',
        ])
            ->assertCreated()
            ->assertJsonPath('author.id', $admin->id)
            ->assertJsonPath('message', 'Please clarify the allocation split for this month.');

        $this->assertDatabaseHas('loe_feedback', [
            'loe_report_id' => $report->id,
            'user_id' => $admin->id,
            'message' => 'Please clarify the allocation split for this month.',
        ]);

        Notification::assertSentTo($employee, LoeFeedbackNotification::class);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'loe_feedback',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_admin_can_search_users_projects_and_allocations(): void
    {
        $admin = $this->createUserWithRoles(['admin']);
        $user = $this->createUserWithRoles(['employee'], [
            'name' => 'Search Target',
            'email' => 'target.user@example.com',
            'employee_code' => 'EMP-SEARCH-1',
        ]);
        $otherUser = $this->createUserWithRoles(['employee'], [
            'name' => 'Other Person',
            'email' => 'other.user@example.com',
            'employee_code' => 'EMP-SEARCH-2',
        ]);
        $projectA = Project::factory()->create([
            'name' => 'Searchable Project',
            'engagement' => 'Search Engagement',
        ]);
        $projectB = Project::factory()->create([
            'name' => 'Different Project',
            'engagement' => 'Other Engagement',
        ]);
        Allocation::factory()->create([
            'user_id' => $user->id,
            'project_id' => $projectA->id,
            'percentage' => 55,
        ]);
        Allocation::factory()->create([
            'user_id' => $otherUser->id,
            'project_id' => $projectB->id,
            'percentage' => 45,
        ]);

        $this->actingAs($admin);

        $this->getJson('/api/admin/users?search=target.user@example.com')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $user->id);

        $this->getJson('/api/admin/projects?search=Search Engagement')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $projectA->id);

        $this->getJson("/api/admin/allocations?search=Search Target&project_ids[]={$projectA->id}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.user_id', $user->id)
            ->assertJsonPath('0.project_id', $projectA->id);
    }

    public function test_admin_can_crud_projects(): void
    {
        $admin = $this->createUserWithRoles(['admin']);
        $this->actingAs($admin);

        $storeResponse = $this->postJson('/api/admin/projects', [
            'name' => 'Atlas Platform',
            'engagement' => 'Client X',
            'description' => 'Platform rebuild',
            'engagement_type' => 'project',
            'status' => true,
        ]);

        $storeResponse->assertCreated()->assertJsonPath('name', 'Atlas Platform');
        $projectId = $storeResponse->json('id');

        $this->getJson('/api/admin/projects')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Atlas Platform']);

        $this->getJson("/api/admin/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('id', $projectId);

        $this->putJson("/api/admin/projects/{$projectId}", [
            'name' => 'Atlas Platform Updated',
            'engagement' => 'Client X',
            'description' => 'Platform rebuild phase 2',
            'engagement_type' => 'product',
            'status' => true,
        ])
            ->assertOk()
            ->assertJsonPath('engagement_type', 'product');

        $this->deleteJson("/api/admin/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('message', 'Project archived successfully.');
    }

    public function test_admin_can_crud_allocations_and_allocation_cap_is_enforced(): void
    {
        $admin = $this->createUserWithRoles(['admin']);
        $employee = $this->createUserWithRoles(['employee']);
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();
        $projectC = Project::factory()->create();

        $this->actingAs($admin);

        $storeResponse = $this->postJson('/api/admin/allocations', [
            'user_id' => $employee->id,
            'project_id' => $projectA->id,
            'percentage' => 60,
        ]);

        $storeResponse->assertCreated()->assertJsonPath('percentage', '60.00');
        $allocationId = $storeResponse->json('id');

        $this->postJson('/api/admin/allocations', [
            'user_id' => $employee->id,
            'project_id' => $projectB->id,
            'percentage' => 50,
        ])
            ->assertStatus(422)
            ->assertSeeText('Total allocation cannot exceed 100%.');

        $this->getJson('/api/admin/allocations')
            ->assertOk()
            ->assertJsonFragment(['user_id' => $employee->id]);

        $this->getJson("/api/admin/allocations/{$allocationId}")
            ->assertOk()
            ->assertJsonPath('id', $allocationId);

        $this->putJson("/api/admin/allocations/{$allocationId}", [
            'user_id' => $employee->id,
            'project_id' => $projectC->id,
            'percentage' => 45,
        ])
            ->assertOk()
            ->assertJsonPath('project_id', $projectC->id);

        $this->deleteJson("/api/admin/allocations/{$allocationId}")
            ->assertOk()
            ->assertJsonPath('message', 'Allocation removed successfully.');
    }

    public function test_admin_can_view_and_export_reports(): void
    {
        $admin = $this->createUserWithRoles(['admin']);
        $employee = $this->createUserWithRoles(['employee']);
        $project = Project::factory()->create(['engagement_type' => 'project']);
        $lastMonth = now()->subMonthNoOverflow();
        $twoMonthsAgo = now()->subMonthsNoOverflow(2);
        Allocation::factory()->create([
            'user_id' => $employee->id,
            'project_id' => $project->id,
            'percentage' => 35,
        ]);
        $report = LoeReport::factory()->create([
            'user_id' => $employee->id,
            'month' => $lastMonth->month,
            'year' => $lastMonth->year,
            'total_percentage' => 40,
        ]);
        LoeEntry::factory()->create([
            'loe_report_id' => $report->id,
            'project_id' => $project->id,
            'percentage' => 40,
        ]);
        LoeReport::factory()->create([
            'user_id' => $employee->id,
            'month' => $twoMonthsAgo->month,
            'year' => $twoMonthsAgo->year,
            'total_percentage' => 95,
        ]);

        $this->actingAs($admin);

        $reportsResponse = $this->getJson(sprintf('/api/admin/reports?month=%d&year=%d', now()->month, now()->year))
            ->assertOk()
            ->assertJsonStructure([
                'employee_monthly',
                'employee_yearly',
                'project_summary',
                'missing_submissions',
                'allocation_variance',
            ]);

        $reportsResponse
            ->assertJsonCount(1, 'employee_monthly')
            ->assertJsonPath('employee_monthly.0.employee', $employee->name)
            ->assertJsonPath('employee_monthly.0.month', $lastMonth->month)
            ->assertJsonPath('employee_monthly.0.year', $lastMonth->year)
            ->assertJsonPath('employee_monthly.0.loe_status', 'Critical')
            ->assertJsonPath('employee_monthly.0.loe_status_tone', 'critical');

        $this->assertSame(40.0, (float) $reportsResponse->json('employee_monthly.0.total_percentage'));

        $this->get(sprintf('/api/admin/reports/export?month=%d&year=%d&type=employee-monthly&format=pdf', now()->month, now()->year))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(sprintf('/api/admin/reports/export?month=%d&year=%d&type=employee-monthly&format=xlsx', now()->month, now()->year))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $logsResponse = $this->getJson('/api/admin/activity-logs');

        $logsResponse
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'log_name', 'description', 'event', 'causer', 'subject', 'properties'],
                ],
            ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'exports',
            'description' => 'Admin exported report',
            'causer_id' => $admin->id,
        ]);
    }
}
