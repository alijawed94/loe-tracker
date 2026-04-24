<?php

namespace Database\Seeders;

use App\Models\Allocation;
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
        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['label' => 'Admin']
        );
        $employeeRole = Role::query()->firstOrCreate(
            ['name' => 'employee'],
            ['label' => 'Employee']
        );

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'ali.jawed@pixeledge.io'],
            [
                'name' => 'Ali Jawed',
                'employee_code' => 'EMP-001',
                'designation' => 'Super Admin',
                'stream' => 'admin',
                'timezone' => 'Asia/Karachi',
                'status' => true,
                'password' => Hash::make('Password@123'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->roles()->syncWithoutDetaching([$adminRole->id, $employeeRole->id]);

        $employees = collect([
            [
                'name' => 'Sara Khan',
                'email' => 'sara.khan@example.com',
                'employee_code' => 'EMP-002',
                'designation' => 'Senior Engineer',
                'stream' => 'engineering',
            ],
            [
                'name' => 'Usman Tariq',
                'email' => 'usman.tariq@example.com',
                'employee_code' => 'EMP-003',
                'designation' => 'Product Designer',
                'stream' => 'experience',
            ],
            [
                'name' => 'Hina Faisal',
                'email' => 'hina.faisal@example.com',
                'employee_code' => 'EMP-004',
                'designation' => 'Engineering Manager',
                'stream' => 'engineering',
            ],
        ])->map(function (array $employee) use ($employeeRole) {
            $user = User::query()->firstOrCreate(
                ['email' => $employee['email']],
                [
                    ...$employee,
                    'timezone' => 'Asia/Karachi',
                    'status' => true,
                    'password' => Hash::make('Password@123'),
                    'email_verified_at' => now(),
                ]
            );

            $user->roles()->syncWithoutDetaching([$employeeRole->id]);

            return $user;
        });

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
            ->map(fn (array $project) => [
                ...$project,
                'description' => "{$project['engagement']} seeded project catalogue entry.",
                'status' => true,
            ])
            ->map(fn (array $project) => Project::query()->firstOrCreate(['name' => $project['name']], $project));

        $allocationMatrix = [
            'EMP-002' => [
                'EcoTours - Project' => 50,
                'PixelEdge Platform - Product' => 30,
                'Recruiting - HR & Admin' => 20,
            ],
            'EMP-003' => [
                'Bookflow - Product' => 40,
                'Content Marketing - M & S' => 35,
                'Onboarding - HR & Admin' => 25,
            ],
            'EMP-004' => [
                'LoanEdge - Product' => 45,
                'TRI Data Governance - Project' => 35,
                'General HR - HR & Admin' => 20,
            ],
        ];

        foreach ($employees as $employee) {
            foreach ($allocationMatrix[$employee->employee_code] as $projectName => $percentage) {
                Allocation::query()->firstOrCreate(
                    [
                        'user_id' => $employee->id,
                        'project_id' => $projects->firstWhere('name', $projectName)->id,
                    ],
                    ['percentage' => $percentage]
                );
            }
        }

        $period = CarbonImmutable::now('Asia/Karachi');

        foreach ($employees as $employee) {
            $report = LoeReport::query()->firstOrCreate(
                [
                    'user_id' => $employee->id,
                    'month' => $period->month,
                    'year' => $period->year,
                ],
                [
                    'total_percentage' => 100,
                    'submitted_at' => now(),
                ]
            );

            if ($report->entries()->doesntExist()) {
                foreach ($allocationMatrix[$employee->employee_code] as $projectName => $percentage) {
                    $report->entries()->create([
                        'project_id' => $projects->firstWhere('name', $projectName)->id,
                        'percentage' => $percentage,
                    ]);
                }
            }
        }
    }
}
