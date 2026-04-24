<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectEngagementType;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\LoeEntry;
use App\Models\LoeReport;
use App\Models\Project;
use App\Models\User;
use App\Support\LoeInsights;
use App\Support\LoePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct()
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $month = (int) $request->integer('month', now()->month);
        $year = (int) $request->integer('year', now()->year);

        $employeeCount = User::query()->where('status', true)->whereHas('roles', fn ($query) => $query->where('name', 'employee'))->count();
        $employees = User::query()
            ->with([
                'allocations',
                'loeReports' => fn ($query) => $query->where('month', $month)->where('year', $year)->with('entries.project'),
            ])
            ->where('status', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'employee'))
            ->get();
        $reports = $employees->flatMap->loeReports;
        $submittedReports = $reports->whereIn('status', ['submitted', 'approved']);
        $missingCount = max($employeeCount - $reports->count(), 0);
        $reviewedReports = $reports->filter(fn (LoeReport $report) => $report->submitted_at && $report->reviewed_at);
        $onTimeReports = $submittedReports->filter(fn (LoeReport $report) => $this->isSubmittedOnTime($report, $report->user));
        $lateReports = $submittedReports->reject(fn (LoeReport $report) => $this->isSubmittedOnTime($report, $report->user));
        $varianceValues = $employees->map(function (User $user) {
            $report = $user->loeReports->first();

            if (! $report) {
                return null;
            }

            return abs((float) $user->allocations->sum('percentage') - (float) $report->total_percentage);
        })->filter();

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

        $statusBreakdown = [
            ['status' => 'Approved', 'total' => $reports->where('status', 'approved')->count()],
            ['status' => 'Submitted', 'total' => $reports->where('status', 'submitted')->count()],
            ['status' => 'Draft', 'total' => $reports->where('status', 'draft')->count()],
            ['status' => 'Missing', 'total' => $missingCount],
        ];

        $loeQualityDistribution = [
            ['label' => 'Critical', 'total' => $reports->filter(fn (LoeReport $report) => (float) $report->total_percentage < 50 || (float) $report->total_percentage > 110)->count()],
            ['label' => 'Medium', 'total' => $reports->filter(fn (LoeReport $report) => (float) $report->total_percentage >= 50 && (float) $report->total_percentage < 90)->count()],
            ['label' => 'Good', 'total' => $reports->filter(fn (LoeReport $report) => (float) $report->total_percentage >= 90 && (float) $report->total_percentage <= 110)->count()],
        ];

        $timeOffTrend = collect(range(5, 0))->reverse()->map(function ($offset) use ($employees, $month, $year) {
            $period = CarbonImmutable::create($year, $month, 1)->subMonthsNoOverflow($offset);
            $periodReports = LoeReport::query()
                ->with('entries')
                ->where('month', $period->month)
                ->where('year', $period->year)
                ->get();

            return [
                'month' => $period->format('M Y'),
                'time_off_percentage' => round($periodReports->flatMap->entries->where('entry_type', LoeEntry::ENTRY_TYPE_TIME_OFF)->sum('percentage'), 2),
                'project_percentage' => round($periodReports->flatMap->entries->where('entry_type', LoeEntry::ENTRY_TYPE_PROJECT)->sum('percentage'), 2),
            ];
        })->values();

        $streamUtilization = $employees->groupBy('stream')->map(function ($group, $stream) {
            $reportGroup = $group->flatMap->loeReports;
            $timeOffTotal = $reportGroup->flatMap->entries->where('entry_type', LoeEntry::ENTRY_TYPE_TIME_OFF)->sum('percentage');
            $projectTotal = $reportGroup->flatMap->entries->where('entry_type', LoeEntry::ENTRY_TYPE_PROJECT)->sum('percentage');

            return [
                'stream' => str((string) $stream)->replace('_', ' ')->title()->value(),
                'productive_loe' => round($projectTotal, 2),
                'time_off_loe' => round($timeOffTotal, 2),
            ];
        })->values();

        $reviewTurnaroundTrend = collect(range(5, 0))->reverse()->map(function ($offset) use ($month, $year) {
            $period = CarbonImmutable::create($year, $month, 1)->subMonthsNoOverflow($offset);
            $periodReports = LoeReport::query()
                ->where('month', $period->month)
                ->where('year', $period->year)
                ->whereNotNull('reviewed_at')
                ->whereNotNull('submitted_at')
                ->get();

            return [
                'month' => $period->format('M Y'),
                'avg_hours' => round($periodReports->avg(fn (LoeReport $report) => $report->submitted_at?->diffInHours($report->reviewed_at)) ?? 0, 2),
            ];
        })->values();

        $exceptionTrend = collect(range(5, 0))->reverse()->map(function ($offset) use ($month, $year) {
            $period = CarbonImmutable::create($year, $month, 1)->subMonthsNoOverflow($offset);
            $periodEmployees = User::query()
                ->with([
                    'allocations',
                    'loeReports' => fn ($query) => $query->where('month', $period->month)->where('year', $period->year)->with('entries'),
                ])
                ->where('status', true)
                ->whereHas('roles', fn ($query) => $query->where('name', 'employee'))
                ->get();

            $count = $periodEmployees->sum(function (User $user) {
                $report = $user->loeReports->first();

                if (! $report) {
                    return 1;
                }

                return count(LoeInsights::reportWarnings($report, $user))
                    + ($report->status === 'draft' ? 1 : 0)
                    + ($report->status === 'submitted' ? 1 : 0);
            });

            return [
                'month' => $period->format('M Y'),
                'exceptions' => $count,
            ];
        })->values();

        return response()->json([
            'metrics' => [
                'active_employees' => $employeeCount,
                'active_projects' => Project::query()->where('status', true)->count(),
                'submitted_loe_reports' => $submittedReports->count(),
                'missing_submissions' => $missingCount,
                'current_allocation_total' => (float) Allocation::query()->sum('percentage'),
                'on_time_submission_rate' => $this->safePercentage($onTimeReports->count(), max($employeeCount, 1)),
                'late_submission_rate' => $this->safePercentage($lateReports->count(), max($employeeCount, 1)),
                'average_variance' => round($varianceValues->avg() ?? 0, 2),
                'approval_turnaround_hours' => round($reviewedReports->avg(fn (LoeReport $report) => $report->submitted_at?->diffInHours($report->reviewed_at)) ?? 0, 2),
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
                'submission_status_breakdown' => collect($statusBreakdown)->values(),
                'loe_quality_distribution' => collect($loeQualityDistribution)->values(),
                'time_off_trend' => $timeOffTrend,
                'stream_utilization' => $streamUtilization,
                'review_turnaround_trend' => $reviewTurnaroundTrend,
                'exception_trend' => $exceptionTrend,
            ],
            'exceptions' => $exceptions,
        ]);
    }

    protected function isSubmittedOnTime(LoeReport $report, ?User $user = null): bool
    {
        if (! $report->submitted_at || ! $user) {
            return false;
        }

        return $report->submitted_at->lessThanOrEqualTo(LoePeriod::deadline($report->month, $report->year, $user));
    }

    protected function safePercentage(float|int $value, float|int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 2);
    }
}
