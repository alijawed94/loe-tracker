<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\LoeEntry;
use App\Models\LoeReport;
use App\Models\Project;
use App\Notifications\LoeFeedbackNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use InteractsWithRoles;
    use RefreshDatabase;

    public function test_employee_dashboard_returns_only_the_authenticated_users_reports_and_allocations(): void
    {
        $employee = $this->createUserWithRoles(['employee']);
        $otherEmployee = $this->createUserWithRoles(['employee']);
        $project = Project::factory()->create(['status' => true]);

        Allocation::factory()->create([
            'user_id' => $employee->id,
            'project_id' => $project->id,
            'percentage' => 40,
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

        LoeReport::factory()->create([
            'user_id' => $otherEmployee->id,
            'month' => 4,
            'year' => 2026,
            'total_percentage' => 55,
        ]);

        $this->actingAs($employee);

        $response = $this->getJson('/api/employee/dashboard');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'allocations')
            ->assertJsonCount(1, 'reports')
            ->assertJsonPath('reports.0.id', $report->id);
    }

    public function test_employee_can_list_create_show_update_and_export_loe_reports(): void
    {
        Notification::fake();

        $employee = $this->createUserWithRoles(['employee'], ['timezone' => 'Asia/Karachi']);
        $projectA = Project::factory()->create(['status' => true]);
        $projectB = Project::factory()->create(['status' => true]);

        $this->actingAs($employee);

        $storeResponse = $this->postJson('/api/employee/reports', [
            'month' => 12,
            'year' => 2099,
            'entries' => [
                ['entry_type' => 'project', 'project_id' => $projectA->id, 'percentage' => 60],
                ['entry_type' => 'project', 'project_id' => $projectB->id, 'percentage' => 40],
            ],
        ]);

        $storeResponse
            ->assertCreated()
            ->assertJsonPath('total_percentage', '100.00');

        $reportId = $storeResponse->json('id');

        $this->getJson('/api/employee/reports')
            ->assertOk()
            ->assertJsonCount(1);

        $this->getJson("/api/employee/reports/{$reportId}")
            ->assertOk()
            ->assertJsonPath('id', $reportId);

        $this->putJson("/api/employee/reports/{$reportId}", [
            'entries' => [
                ['entry_type' => 'project', 'project_id' => $projectA->id, 'percentage' => 70],
                ['entry_type' => 'project', 'project_id' => $projectB->id, 'percentage' => 20],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('total_percentage', '90.00');

        $this->deleteJson("/api/employee/reports/{$reportId}")
            ->assertOk()
            ->assertJsonPath('message', 'LOE deleted successfully.');

        $this->get('/api/employee/reports/export?format=pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get('/api/employee/reports/export?format=xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'loe_reports',
            'description' => 'LoeReport created',
            'causer_id' => $employee->id,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'loe_reports',
            'description' => 'LoeReport updated',
            'causer_id' => $employee->id,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'exports',
            'description' => 'Employee exported LOE report',
            'causer_id' => $employee->id,
        ]);
    }

    public function test_employee_can_save_a_draft_and_submit_it_later(): void
    {
        Notification::fake();

        $employee = $this->createUserWithRoles(['employee'], ['timezone' => 'Asia/Karachi']);
        $project = Project::factory()->create(['status' => true]);

        $this->actingAs($employee);

        $draftResponse = $this->postJson('/api/employee/reports', [
            'month' => 11,
            'year' => 2099,
            'status' => 'draft',
            'entries' => [
                ['entry_type' => 'project', 'project_id' => $project->id, 'percentage' => 45],
            ],
        ]);

        $draftResponse
            ->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('submitted_at', null);

        $reportId = $draftResponse->json('id');

        $this->putJson("/api/employee/reports/{$reportId}", [
            'status' => 'submitted',
            'entries' => [
                ['entry_type' => 'project', 'project_id' => $project->id, 'percentage' => 85],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'submitted');
    }

    public function test_employee_cannot_create_duplicate_monthly_loe_reports(): void
    {
        Notification::fake();

        $employee = $this->createUserWithRoles(['employee']);
        $project = Project::factory()->create(['status' => true]);

        LoeReport::factory()->create([
            'user_id' => $employee->id,
            'month' => 12,
            'year' => 2099,
            'total_percentage' => 50,
        ]);

        $this->actingAs($employee);

        $this->postJson('/api/employee/reports', [
            'month' => 12,
            'year' => 2099,
            'entries' => [
                ['entry_type' => 'project', 'project_id' => $project->id, 'percentage' => 50],
            ],
        ])
            ->assertStatus(422)
            ->assertSeeText('LOE has already been submitted for this month.');
    }

    public function test_employee_can_create_loe_for_a_past_month_if_it_has_not_been_submitted_yet(): void
    {
        Notification::fake();

        $employee = $this->createUserWithRoles(['employee'], ['timezone' => 'Asia/Karachi']);
        $project = Project::factory()->create(['status' => true]);

        $this->actingAs($employee);

        $this->postJson('/api/employee/reports', [
            'month' => 1,
            'year' => 2020,
            'entries' => [
                ['entry_type' => 'project', 'project_id' => $project->id, 'percentage' => 50],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('month', 1)
            ->assertJsonPath('year', 2020)
            ->assertJsonPath('total_percentage', '50.00');
    }

    public function test_employee_cannot_view_another_employees_report(): void
    {
        $employee = $this->createUserWithRoles(['employee']);
        $otherEmployee = $this->createUserWithRoles(['employee']);
        $report = LoeReport::factory()->create([
            'user_id' => $otherEmployee->id,
            'month' => 12,
            'year' => 2099,
            'total_percentage' => 20,
        ]);

        $this->actingAs($employee);

        $this->getJson("/api/employee/reports/{$report->id}")
            ->assertForbidden();
    }

    public function test_employee_cannot_update_a_locked_report(): void
    {
        Notification::fake();

        $employee = $this->createUserWithRoles(['employee'], ['timezone' => 'Asia/Karachi']);
        $project = Project::factory()->create(['status' => true]);
        $report = LoeReport::factory()->create([
            'user_id' => $employee->id,
            'month' => 1,
            'year' => 2020,
            'total_percentage' => 50,
        ]);
        LoeEntry::factory()->create([
            'loe_report_id' => $report->id,
            'project_id' => $project->id,
            'percentage' => 50,
        ]);

        $this->actingAs($employee);

        $this->putJson("/api/employee/reports/{$report->id}", [
            'entries' => [
                ['entry_type' => 'project', 'project_id' => $project->id, 'percentage' => 60],
            ],
        ])
            ->assertStatus(422)
            ->assertSeeText('This LOE is now read-only.');

        $this->deleteJson("/api/employee/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('message', 'LOE deleted successfully.');
    }

    public function test_employee_can_view_feedback_and_reply_to_admin_feedback(): void
    {
        Notification::fake();

        $employee = $this->createUserWithRoles(['employee']);
        $admin = $this->createUserWithRoles(['admin']);
        $project = Project::factory()->create(['status' => true]);
        $report = LoeReport::factory()->create([
            'user_id' => $employee->id,
            'month' => 12,
            'year' => 2099,
            'total_percentage' => 75,
        ]);
        LoeEntry::factory()->create([
            'loe_report_id' => $report->id,
            'project_id' => $project->id,
            'percentage' => 75,
        ]);

        $this->actingAs($admin);
        $this->postJson("/api/loe-reports/{$report->id}/feedback", [
            'message' => 'Can you share more detail on this distribution?',
        ])->assertCreated();

        $this->actingAs($employee);

        $this->getJson("/api/loe-reports/{$report->id}/feedback")
            ->assertOk()
            ->assertJsonCount(1, 'feedback')
            ->assertJsonPath('feedback.0.author.id', $admin->id);

        $this->postJson("/api/loe-reports/{$report->id}/feedback", [
            'message' => 'I was supporting two internal streams during the month.',
        ])
            ->assertCreated()
            ->assertJsonPath('author.id', $employee->id);

        Notification::assertSentTo($admin, LoeFeedbackNotification::class);
    }

    public function test_employee_cannot_access_feedback_for_another_employees_report(): void
    {
        $employee = $this->createUserWithRoles(['employee']);
        $otherEmployee = $this->createUserWithRoles(['employee']);
        $report = LoeReport::factory()->create([
            'user_id' => $otherEmployee->id,
            'month' => 12,
            'year' => 2099,
            'total_percentage' => 65,
        ]);

        $this->actingAs($employee);

        $this->getJson("/api/loe-reports/{$report->id}/feedback")
            ->assertForbidden();
    }

    public function test_employee_can_submit_time_off_and_it_counts_towards_total(): void
    {
        Notification::fake();

        $employee = $this->createUserWithRoles(['employee']);
        $project = Project::factory()->create(['status' => true]);

        $this->actingAs($employee);

        $response = $this->postJson('/api/employee/reports', [
            'month' => 10,
            'year' => 2099,
            'entries' => [
                ['entry_type' => 'project', 'project_id' => $project->id, 'percentage' => 70],
                ['entry_type' => 'time_off', 'time_off_type' => 'vacation', 'percentage' => 20],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('total_percentage', '90.00');

        $reportId = $response->json('id');

        $this->getJson('/api/employee/dashboard')
            ->assertOk()
            ->assertJsonPath('reports.0.entries.1.entry_type', 'time_off')
            ->assertJsonPath('reports.0.entries.1.time_off_type', 'vacation')
            ->assertJsonPath('reports.0.entries.1.entry_label', 'Vacation');

        $this->assertDatabaseHas('loe_entries', [
            'loe_report_id' => $reportId,
            'entry_type' => 'time_off',
            'time_off_type' => 'vacation',
            'project_id' => null,
        ]);
    }
}
