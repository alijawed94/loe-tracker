<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\LoeEntry;
use App\Models\LoeReport;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'charts' => ['engagement_distribution', 'submission_trend', 'allocation_vs_actual'],
            ]);

        $this->assertIsArray($response->json('charts.submission_trend'));
        $this->assertIsArray($response->json('charts.engagement_distribution'));
        $this->assertIsArray($response->json('charts.allocation_vs_actual'));
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
        Allocation::factory()->create([
            'user_id' => $employee->id,
            'project_id' => $project->id,
            'percentage' => 35,
        ]);
        $report = LoeReport::factory()->create([
            'user_id' => $employee->id,
            'month' => 4,
            'year' => 2026,
            'total_percentage' => 40,
        ]);
        LoeEntry::factory()->create([
            'loe_report_id' => $report->id,
            'project_id' => $project->id,
            'percentage' => 40,
        ]);

        $this->actingAs($admin);

        $this->getJson('/api/admin/reports?month=4&year=2026')
            ->assertOk()
            ->assertJsonStructure([
                'employee_monthly',
                'employee_yearly',
                'project_summary',
                'missing_submissions',
                'allocation_variance',
            ]);

        $this->get('/api/admin/reports/export?month=4&year=2026&type=employee-monthly&format=pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get('/api/admin/reports/export?month=4&year=2026&type=employee-monthly&format=xlsx')
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
