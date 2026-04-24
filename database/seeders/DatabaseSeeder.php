<?php

namespace Database\Seeders;

use App\Models\Allocation;
use App\Models\LoeFeedback;
use App\Models\LoeReport;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $timezone = 'Asia/Karachi';
        $password = Hash::make('Password@1');
        $currentPeriod = CarbonImmutable::now($timezone)->startOfMonth();
        $previousPeriod = $currentPeriod->subMonth();

        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['label' => 'Admin']
        );

        $employeeRole = Role::query()->firstOrCreate(
            ['name' => 'employee'],
            ['label' => 'Employee']
        );

        $admin = User::query()->create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'employee_code' => 'ADM-001',
            'designation' => 'Administrator',
            'stream' => 'admin',
            'timezone' => $timezone,
            'status' => true,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
        $admin->roles()->sync([$adminRole->id]);

        $employees = collect([
            [
                'name' => 'Sample Employee',
                'email' => 'employee@example.com',
                'employee_code' => 'EMP-001',
                'designation' => 'Software Engineer',
                'stream' => 'engineering',
            ],
            [
                'name' => 'Amna Shahid',
                'email' => 'amna.shahid@example.com',
                'employee_code' => 'EMP-002',
                'designation' => 'Product Designer',
                'stream' => 'experience',
            ],
            [
                'name' => 'Bilal Ahmed',
                'email' => 'bilal.ahmed@example.com',
                'employee_code' => 'EMP-003',
                'designation' => 'Engineering Lead',
                'stream' => 'engineering',
            ],
            [
                'name' => 'Sana Rafiq',
                'email' => 'sana.rafiq@example.com',
                'employee_code' => 'EMP-004',
                'designation' => 'People Operations Specialist',
                'stream' => 'admin',
            ],
        ])->map(function (array $employee) use ($employeeRole, $password, $timezone) {
            $user = User::query()->create([
                ...$employee,
                'timezone' => $timezone,
                'status' => true,
                'password' => $password,
                'email_verified_at' => now(),
            ]);

            $user->roles()->sync([$employeeRole->id]);

            return $user;
        })->keyBy('employee_code');

        $projectCatalog = collect([
            ['name' => 'AngelCatalyst (ACA) - Product', 'engagement' => 'AngelCatalyst (ACA)', 'engagement_type' => 'product'],
            ['name' => 'BDC - Product', 'engagement' => 'BDC', 'engagement_type' => 'product'],
            ['name' => 'LKM - Product', 'engagement' => 'LKM', 'engagement_type' => 'product'],
            ['name' => 'Bookflow - Product', 'engagement' => 'Bookflow', 'engagement_type' => 'product'],
            ['name' => 'Desi Folks Music - Product', 'engagement' => 'Desi Folks Music', 'engagement_type' => 'product'],
            ['name' => 'Energy Intel (ICAST) - Product', 'engagement' => 'Energy Intel (ICAST)', 'engagement_type' => 'product'],
            ['name' => 'EPR Complete - Product', 'engagement' => 'EPR Complete', 'engagement_type' => 'product'],
            ['name' => 'ERMAssess (HSI) - Product', 'engagement' => 'ERMAssess (HSI)', 'engagement_type' => 'product'],
            ['name' => 'ERMClarity (PSI) - Product', 'engagement' => 'ERMClarity (PSI)', 'engagement_type' => 'product'],
            ['name' => 'Netzero Compass (Bigfoot) - Product', 'engagement' => 'Netzero Compass (Bigfoot)', 'engagement_type' => 'product'],
            ['name' => 'Other - Product', 'engagement' => 'Other', 'engagement_type' => 'product'],
            ['name' => 'PixelEdge Platform - Product', 'engagement' => 'PixelEdge Platform', 'engagement_type' => 'product'],
            ['name' => 'Project Intel - Product', 'engagement' => 'Project Intel', 'engagement_type' => 'product'],
            ['name' => 'Quatro - Product', 'engagement' => 'Quatro', 'engagement_type' => 'product'],
            ['name' => 'EcoTours - Project', 'engagement' => 'EcoTours', 'engagement_type' => 'project'],
            ['name' => 'ICAST - Salesforce - Project', 'engagement' => 'ICAST - Salesforce', 'engagement_type' => 'project'],
            ['name' => 'Kainaat - Project', 'engagement' => 'Kainaat', 'engagement_type' => 'project'],
            ['name' => 'Lansweeper - Project', 'engagement' => 'Lansweeper', 'engagement_type' => 'project'],
            ['name' => 'PixelEdge Processes - Project', 'engagement' => 'PixelEdge Processes', 'engagement_type' => 'project'],
            ['name' => 'Sahr - Project', 'engagement' => 'Sahr', 'engagement_type' => 'project'],
            ['name' => 'Collateral  - M & S', 'engagement' => 'Collateral ', 'engagement_type' => 'marketing'],
            ['name' => 'Content Marketing - M & S', 'engagement' => 'Content Marketing', 'engagement_type' => 'marketing'],
            ['name' => 'Digital Marketing - M & S', 'engagement' => 'Digital Marketing', 'engagement_type' => 'marketing'],
            ['name' => 'Sales Pipeline - M & S', 'engagement' => 'Sales Pipeline', 'engagement_type' => 'marketing'],
            ['name' => 'General HR - HR & Admin', 'engagement' => 'General HR', 'engagement_type' => 'admin'],
            ['name' => 'Onboarding - HR & Admin', 'engagement' => 'Onboarding', 'engagement_type' => 'admin'],
            ['name' => 'Recruiting - HR & Admin', 'engagement' => 'Recruiting', 'engagement_type' => 'admin'],
            ['name' => 'CH Capital - Project', 'engagement' => 'CH Capital', 'engagement_type' => 'project'],
            ['name' => 'Value Navigator - Project', 'engagement' => 'Value Navigator', 'engagement_type' => 'project'],
            ['name' => 'TRI Data Governance - Project', 'engagement' => 'TRI Data Governance', 'engagement_type' => 'project'],
            ['name' => 'EPCRA - Part II - Project', 'engagement' => 'EPCRA - Part II', 'engagement_type' => 'project'],
            ['name' => 'LoanEdge - Product', 'engagement' => 'LoanEdge', 'engagement_type' => 'product'],
        ]);

        $projects = $projectCatalog
            ->map(fn (array $project) => Project::query()->create([
                ...$project,
                'description' => "{$project['engagement']} seeded project catalogue entry.",
                'status' => true,
            ]))
            ->keyBy('name');

        $allocationMap = [
            'EMP-001' => [
                'EcoTours - Project' => 50,
                'PixelEdge Platform - Product' => 30,
                'Recruiting - HR & Admin' => 20,
            ],
            'EMP-002' => [
                'Bookflow - Product' => 40,
                'Content Marketing - M & S' => 35,
                'Onboarding - HR & Admin' => 25,
            ],
            'EMP-003' => [
                'LoanEdge - Product' => 45,
                'TRI Data Governance - Project' => 35,
                'General HR - HR & Admin' => 20,
            ],
            'EMP-004' => [
                'PixelEdge Processes - Project' => 40,
                'Sales Pipeline - M & S' => 30,
                'General HR - HR & Admin' => 30,
            ],
        ];

        foreach ($allocationMap as $employeeCode => $allocations) {
            $employee = $employees[$employeeCode];

            foreach ($allocations as $projectName => $percentage) {
                Allocation::query()->create([
                    'user_id' => $employee->id,
                    'project_id' => $projects[$projectName]->id,
                    'percentage' => $percentage,
                ]);
            }
        }

        $seedReport = function (User $employee, CarbonImmutable $period, array $entries, string $status = 'submitted', array $attributes = []) use ($admin, $projects) {
            $total = collect($entries)->sum();

            $report = LoeReport::query()->create([
                'user_id' => $employee->id,
                'month' => $period->month,
                'year' => $period->year,
                'total_percentage' => $total,
                'status' => $status,
                'submitted_at' => $status === 'draft' ? null : ($attributes['submitted_at'] ?? now()),
                'reviewed_by' => $attributes['reviewed_by'] ?? null,
                'reviewed_at' => $attributes['reviewed_at'] ?? null,
                'review_notes' => $attributes['review_notes'] ?? null,
            ]);

            foreach ($entries as $projectName => $percentage) {
                $report->entries()->create([
                    'project_id' => $projects[$projectName]->id,
                    'percentage' => $percentage,
                ]);
            }

            return $report;
        };

        $seedReport(
            $employees['EMP-001'],
            $previousPeriod,
            [
                'EcoTours - Project' => 45,
                'PixelEdge Platform - Product' => 35,
                'Recruiting - HR & Admin' => 20,
            ],
            'approved',
            [
                'submitted_at' => $previousPeriod->endOfMonth()->setTime(16, 15),
                'reviewed_by' => $admin->id,
                'reviewed_at' => $currentPeriod->subWeeks(3),
                'review_notes' => 'Healthy allocation distribution for the month.',
            ]
        );

        $approvedCurrent = $seedReport(
            $employees['EMP-001'],
            $currentPeriod,
            [
                'EcoTours - Project' => 50,
                'PixelEdge Platform - Product' => 30,
                'Recruiting - HR & Admin' => 20,
            ],
            'approved',
            [
                'submitted_at' => $currentPeriod->addDays(8)->setTime(11, 0),
                'reviewed_by' => $admin->id,
                'reviewed_at' => $currentPeriod->addDays(9)->setTime(15, 30),
                'review_notes' => 'Approved with balanced utilization.',
            ]
        );

        LoeFeedback::query()->create([
            'loe_report_id' => $approvedCurrent->id,
            'user_id' => $admin->id,
            'message' => 'Thanks for submitting on time. The distribution looks good.',
        ]);
        LoeFeedback::query()->create([
            'loe_report_id' => $approvedCurrent->id,
            'user_id' => $employees['EMP-001']->id,
            'message' => 'Thanks. I will keep the same breakup if the allocation stays stable next month.',
        ]);

        $seedReport(
            $employees['EMP-002'],
            $previousPeriod,
            [
                'Bookflow - Product' => 35,
                'Content Marketing - M & S' => 40,
                'Onboarding - HR & Admin' => 25,
            ],
            'approved',
            [
                'submitted_at' => $previousPeriod->endOfMonth()->setTime(18, 0),
                'reviewed_by' => $admin->id,
                'reviewed_at' => $currentPeriod->subWeeks(3)->setTime(10, 0),
                'review_notes' => 'Previous month was reviewed and approved.',
            ]
        );

        $seedReport(
            $employees['EMP-002'],
            $currentPeriod,
            [
                'Bookflow - Product' => 30,
                'Content Marketing - M & S' => 32,
                'Onboarding - HR & Admin' => 20,
            ],
            'draft'
        );

        $seedReport(
            $employees['EMP-003'],
            $previousPeriod,
            [
                'LoanEdge - Product' => 45,
                'TRI Data Governance - Project' => 35,
                'General HR - HR & Admin' => 20,
            ],
            'approved',
            [
                'submitted_at' => $previousPeriod->endOfMonth()->setTime(14, 10),
                'reviewed_by' => $admin->id,
                'reviewed_at' => $currentPeriod->subWeeks(2)->setTime(12, 45),
                'review_notes' => 'Previous month approved after review.',
            ]
        );

        $seedReport(
            $employees['EMP-003'],
            $currentPeriod,
            [
                'LoanEdge - Product' => 55,
                'TRI Data Governance - Project' => 45,
                'General HR - HR & Admin' => 20,
            ],
            'submitted',
            [
                'submitted_at' => $currentPeriod->addDays(12)->setTime(9, 20),
            ]
        );

        $seedReport(
            $employees['EMP-004'],
            $previousPeriod,
            [
                'PixelEdge Processes - Project' => 50,
                'Sales Pipeline - M & S' => 20,
                'General HR - HR & Admin' => 30,
            ],
            'approved',
            [
                'submitted_at' => $previousPeriod->endOfMonth()->setTime(17, 35),
                'reviewed_by' => $admin->id,
                'reviewed_at' => $currentPeriod->subWeeks(2)->setTime(16, 0),
                'review_notes' => 'Approved. Current month submission still pending.',
            ]
        );
    }
}
