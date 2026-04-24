<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectEngagementType;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\LoeReport;
use App\Models\Project;
use App\Models\User;
use App\Support\LoeInsights;
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
        $employees = User::query()
            ->with([
                'allocations',
                'loeReports' => fn ($query) => $query->where('month', $month)->where('year', $year),
            ])
            ->where('status', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'employee'))
            ->get();

        $exceptions = $employees->flatMap(function (User $user) {
            $report = $user->loeReports->first();

            if (! $report) {
                return [[
                    'type' => 'missing_submission',
                    'severity' => 'critical',
                    'user_id' => $user->id,
                    'employee' => $user->name,
                    'employee_code' => $user->employee_code,
                    'message' => 'No LOE has been submitted for the selected month.',
                ]];
            }

            $baseWarnings = collect(LoeInsights::reportWarnings($report, $user))
                ->map(fn ($warning) => [
                    'type' => 'report_warning',
                    'severity' => $warning['level'],
                    'user_id' => $user->id,
                    'employee' => $user->name,
                    'employee_code' => $user->employee_code,
                    'report_id' => $report->id,
                    'message' => $warning['message'],
                ]);

            $workflowWarnings = collect();

            if ($report->status === 'draft') {
                $workflowWarnings->push([
                    'type' => 'draft_report',
                    'severity' => 'medium',
                    'user_id' => $user->id,
                    'employee' => $user->name,
                    'employee_code' => $user->employee_code,
                    'report_id' => $report->id,
                    'message' => 'LOE is still saved as draft.',
                ]);
            }

            if ($report->status === 'submitted') {
                $workflowWarnings->push([
                    'type' => 'pending_review',
                    'severity' => 'medium',
                    'user_id' => $user->id,
                    'employee' => $user->name,
                    'employee_code' => $user->employee_code,
                    'report_id' => $report->id,
                    'message' => 'LOE has been submitted and is awaiting review.',
                ]);
            }

            return $baseWarnings->merge($workflowWarnings);
        })->sortByDesc(fn ($row) => match ($row['severity']) {
            'critical' => 3,
            'medium' => 2,
            default => 1,
        })->values()->take(12);

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
            'exceptions' => $exceptions,
        ]);
    }
}
