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

        $projects = collect([
            [
                'name' => 'Aurora Commerce',
                'engagement' => 'Northwind Group',
                'description' => 'Core commerce platform modernization.',
                'engagement_type' => 'project',
                'status' => true,
            ],
            [
                'name' => 'Pulse CRM',
                'engagement' => 'Internal Product',
                'description' => 'Internal customer relationship product.',
                'engagement_type' => 'product',
                'status' => true,
            ],
            [
                'name' => 'Spring Brand Sprint',
                'engagement' => 'Growth',
                'description' => 'Campaign design and content execution.',
                'engagement_type' => 'marketing',
                'status' => true,
            ],
            [
                'name' => 'People Operations',
                'engagement' => 'Company Admin',
                'description' => 'Operational and internal support effort.',
                'engagement_type' => 'admin',
                'status' => true,
            ],
        ])->map(fn (array $project) => Project::query()->firstOrCreate(['name' => $project['name']], $project));

        $allocationMatrix = [
            'EMP-002' => [
                'Aurora Commerce' => 60,
                'Pulse CRM' => 25,
                'People Operations' => 15,
            ],
            'EMP-003' => [
                'Pulse CRM' => 50,
                'Spring Brand Sprint' => 35,
                'People Operations' => 15,
            ],
            'EMP-004' => [
                'Aurora Commerce' => 45,
                'Pulse CRM' => 35,
                'People Operations' => 20,
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
