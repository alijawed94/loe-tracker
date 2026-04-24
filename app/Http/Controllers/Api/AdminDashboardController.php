<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectEngagementType;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\LoeReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $month = (int) $request->integer('month', now()->month);
        $year = (int) $request->integer('year', now()->year);

        $submittedCount = LoeReport::query()->where('month', $month)->where('year', $year)->count();
        $employeeCount = User::query()->where('status', true)->whereHas('roles', fn ($query) => $query->where('name', 'employee'))->count();
        $missingCount = max($employeeCount - $submittedCount, 0);

        return response()->json([
            'metrics' => [
                'active_employees' => $employeeCount,
                'active_projects' => Project::query()->where('status', true)->count(),
                'submitted_loe_reports' => $submittedCount,
                'missing_submissions' => $missingCount,
                'current_allocation_total' => (float) Allocation::query()->sum('percentage'),
            ],
            'charts' => [
                'project_allocation_headcount' => Project::query()
                    ->withCount('allocations')
                    ->where('status', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Project $project) => [
                        'project_name' => $project->name,
                        'allocated_people' => (int) $project->allocations_count,
                    ])
                    ->values(),
                'engagement_distribution' => Project::query()
                    ->selectRaw('engagement_type, count(*) as total')
                    ->groupBy('engagement_type')
                    ->get()
                    ->map(fn ($row) => [
                        'engagement_type' => $row->engagement_type,
                        'engagement_type_label' => ProjectEngagementType::from($row->engagement_type)->label(),
                        'total' => $row->total,
                    ])
                    ->values(),
                'submission_trend' => collect(range(5, 0))->reverse()->map(function ($offset) {
                    $date = now()->subMonths($offset);

                    return [
                        'month' => $date->format('M Y'),
                        'reports' => LoeReport::query()->where('month', $date->month)->where('year', $date->year)->count(),
                    ];
                })->values(),
                'allocation_vs_actual' => Allocation::query()
                    ->selectRaw('projects.name as project_name, sum(allocations.percentage) as allocation_total')
                    ->join('projects', 'projects.id', '=', 'allocations.project_id')
                    ->groupBy('projects.name')
                    ->get()
                    ->map(function ($row) use ($month, $year) {
                        $actual = LoeReport::query()
                            ->join('loe_entries', 'loe_entries.loe_report_id', '=', 'loe_reports.id')
                            ->join('projects', 'projects.id', '=', 'loe_entries.project_id')
                            ->where('projects.name', $row->project_name)
                            ->where('loe_reports.month', $month)
                            ->where('loe_reports.year', $year)
                            ->sum('loe_entries.percentage');

                        return [
                            'project_name' => $row->project_name,
                            'allocation_total' => (float) $row->allocation_total,
                            'actual_total' => (float) $actual,
                        ];
                    })
                    ->values(),
            ],
        ]);
    }
}
