<?php

namespace App\Services;

use App\Models\LoeReport;
use App\Models\Project;
use App\Models\User;
use App\Support\LoeInsights;
use App\Support\LoePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    public function employeeMonthly(?string $userId = null): Collection
    {
        $lastMonth = CarbonImmutable::now()->subMonthNoOverflow();

        return LoeReport::query()
            ->with(['user', 'entries.project'])
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->where('month', $lastMonth->month)
            ->where('year', $lastMonth->year)
            ->whereHas('user.roles', fn (Builder $query) => $query->where('name', 'employee'))
            ->orderBy('user_id')
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(fn (LoeReport $report) => [
                'employee_id' => $report->user->id,
                'employee' => $report->user->name,
                'employee_code' => $report->user->employee_code,
                'stream' => $report->user->stream,
                'stream_label' => $report->user->stream_label,
                'month' => $report->month,
                'year' => $report->year,
                'total_percentage' => (float) $report->total_percentage,
                'loe_status' => $this->resolveLoeStatus((float) $report->total_percentage),
                'loe_status_tone' => $this->resolveLoeStatusTone((float) $report->total_percentage),
                'submitted_at' => optional($report->submitted_at)?->toIso8601String(),
                'entries' => $report->entries->map(fn ($entry) => [
                    'id' => $entry->id,
                    'entry_type' => $entry->entry_type,
                    'entry_type_label' => $entry->entryTypeLabel(),
                    'entry_label' => $entry->displayName(),
                    'project' => $entry->project?->name,
                    'time_off_type' => $entry->time_off_type,
                    'time_off_type_label' => $entry->timeOffTypeLabel(),
                    'engagement_type' => $entry->entry_type === 'time_off' ? 'time_off' : $entry->project?->engagement_type,
                    'engagement_type_label' => $entry->entry_type === 'time_off' ? 'Time Off' : $entry->project?->engagement_type_label,
                    'percentage' => (float) $entry->percentage,
                ])->values()->all(),
            ]);
    }

    public function employeeYearly(int $year, ?string $userId = null): Collection
    {
        return LoeReport::query()
            ->with(['user', 'entries.project'])
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->where('year', $year)
            ->orderBy('month')
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $reports) {
                $user = $reports->first()->user;

                return [
                    'employee' => $user->name,
                    'employee_code' => $user->employee_code,
                    'months_submitted' => $reports->count(),
                    'average_total_percentage' => round($reports->avg('total_percentage'), 2),
                    'monthly_breakdown' => $reports->map(fn (LoeReport $report) => [
                        'month' => $report->month,
                        'total_percentage' => (float) $report->total_percentage,
                    ])->values()->all(),
                ];
            })->values();
    }

    public function projectSummary(int $month, int $year, ?string $projectId = null): Collection
    {
        return Project::query()
            ->with([
                'loeEntries' => fn ($query) => $query->whereHas('loeReport', fn ($reportQuery) => $reportQuery->where('month', $month)->where('year', $year)),
            ])
            ->withTrashed()
            ->when($projectId, fn (Builder $query) => $query->where('id', $projectId))
            ->get()
            ->map(fn (Project $project) => [
                'project' => $project->name,
                'engagement' => $project->engagement,
                'engagement_type' => $project->engagement_type,
                'engagement_type_label' => $project->engagement_type_label,
                'status' => $project->status,
                'total_percentage' => round($project->loeEntries->sum('percentage'), 2),
                'contributors' => $project->loeEntries->count(),
            ])
            ->filter(fn (array $row) => $row['contributors'] > 0 || $projectId !== null)
            ->values();
    }

    public function missingSubmissions(int $month, int $year): Collection
    {
        $submittedUserIds = LoeReport::query()
            ->where('month', $month)
            ->where('year', $year)
            ->pluck('user_id');

        return User::query()
            ->where('status', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'employee'))
            ->whereNotIn('id', $submittedUserIds)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'employee' => $user->name,
                'employee_code' => $user->employee_code,
                'email' => $user->email,
                'timezone' => $user->timezone,
            ]);
    }

    public function allocationVariance(int $month, int $year, ?string $userId = null): Collection
    {
        $users = User::query()
            ->with([
                'allocations.project',
                'loeReports' => fn ($query) => $query->where('month', $month)->where('year', $year)->with('entries.project'),
            ])
            ->when($userId, fn (Builder $query) => $query->where('id', $userId))
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'employee'))
            ->get();

        return $users->map(function (User $user) {
            $report = $user->loeReports->first();
            $actualByProject = collect($report?->entries ?? [])
                ->filter(fn ($entry) => $entry->project_id)
                ->mapWithKeys(fn ($entry) => [$entry->project_id => (float) $entry->percentage]);

            return [
                'employee' => $user->name,
                'employee_code' => $user->employee_code,
                'rows' => $user->allocations->map(fn ($allocation) => [
                    'project' => $allocation->project?->name,
                    'allocated_percentage' => (float) $allocation->percentage,
                    'actual_percentage' => (float) ($actualByProject[$allocation->project_id] ?? 0),
                    'variance' => round((float) ($actualByProject[$allocation->project_id] ?? 0) - (float) $allocation->percentage, 2),
                ])->values()->all(),
            ];
        })->values();
    }

    public function complianceScorecard(int $month, int $year): Collection
    {
        $employees = $this->employeeBaseQuery()->with([
            'loeReports' => fn ($query) => $query->where('month', $month)->where('year', $year),
        ])->get();

        $reports = $employees->flatMap->loeReports;
        $submittedReports = $reports->whereIn('status', ['submitted', 'approved']);
        $onTimeReports = $submittedReports->filter(fn (LoeReport $report) => $this->isSubmittedOnTime($report, $report->user));
        $lateReports = $submittedReports->reject(fn (LoeReport $report) => $this->isSubmittedOnTime($report, $report->user));
        $reviewedReports = $reports->filter(fn (LoeReport $report) => ! is_null($report->reviewed_at) && ! is_null($report->submitted_at));

        return collect([[
            'period' => CarbonImmutable::create($year, $month, 1)->format('F Y'),
            'employees' => $employees->count(),
            'submitted' => $submittedReports->count(),
            'approved' => $reports->where('status', 'approved')->count(),
            'drafts' => $reports->where('status', 'draft')->count(),
            'missing' => $employees->count() - $reports->count(),
            'on_time_rate' => $this->safePercentage($onTimeReports->count(), max($employees->count(), 1)),
            'late_rate' => $this->safePercentage($lateReports->count(), max($employees->count(), 1)),
            'approval_turnaround_hours' => round($reviewedReports->avg(fn (LoeReport $report) => $report->submitted_at?->diffInHours($report->reviewed_at)) ?? 0, 2),
            'pending_reviews' => $reports->where('status', 'submitted')->count(),
        ]]);
    }

    public function employeeConsistency(int $month, int $year, int $months = 6): Collection
    {
        $periods = collect(range($months - 1, 0))->map(fn ($offset) => CarbonImmutable::create($year, $month, 1)->subMonthsNoOverflow($offset));

        return $this->employeeBaseQuery()
            ->with([
                'loeReports' => fn ($query) => $query
                    ->where(function ($builder) use ($periods) {
                        $periods->each(function (CarbonImmutable $period) use ($builder) {
                            $builder->orWhere(fn ($inner) => $inner->where('month', $period->month)->where('year', $period->year));
                        });
                    }),
            ])
            ->get()
            ->map(function (User $user) use ($periods) {
                $reportsByPeriod = $user->loeReports->keyBy(fn (LoeReport $report) => sprintf('%04d-%02d', $report->year, $report->month));

                $missed = 0;
                $late = 0;
                $warningMonths = 0;
                $onTime = 0;

                foreach ($periods as $period) {
                    $report = $reportsByPeriod->get($period->format('Y-m'));

                    if (! $report) {
                        $missed++;
                        continue;
                    }

                    if ($report->status !== 'draft' && $this->isSubmittedOnTime($report, $user)) {
                        $onTime++;
                    }

                    if ($report->status !== 'draft' && ! $this->isSubmittedOnTime($report, $user)) {
                        $late++;
                    }

                    if (! empty(LoeInsights::reportWarnings($report, $user))) {
                        $warningMonths++;
                    }
                }

                return [
                    'employee' => $user->name,
                    'employee_code' => $user->employee_code,
                    'stream' => $user->stream_label,
                    'months_reviewed' => $periods->count(),
                    'submitted_months' => $user->loeReports->whereIn('status', ['submitted', 'approved'])->count(),
                    'on_time_months' => $onTime,
                    'late_months' => $late,
                    'missed_months' => $missed,
                    'warning_months' => $warningMonths,
                    'average_total' => round($user->loeReports->avg('total_percentage') ?? 0, 2),
                ];
            })
            ->values();
    }

    public function timeOffImpact(int $month, int $year): Collection
    {
        return $this->employeeBaseQuery()
            ->with([
                'loeReports' => fn ($query) => $query
                    ->where('month', $month)
                    ->where('year', $year)
                    ->with('entries.project'),
            ])
            ->get()
            ->map(function (User $user) {
                /** @var LoeReport|null $report */
                $report = $user->loeReports->first();
                $entries = collect($report?->entries ?? []);
                $timeOff = $entries->where('entry_type', 'time_off')->sum('percentage');
                $project = $entries->where('entry_type', 'project')->sum('percentage');
                $total = (float) ($report?->total_percentage ?? 0);

                return [
                    'employee' => $user->name,
                    'employee_code' => $user->employee_code,
                    'stream' => $user->stream_label,
                    'report_status' => $report?->status ? str($report->status)->replace('_', ' ')->title()->value() : 'Missing',
                    'project_percentage' => round($project, 2),
                    'time_off_percentage' => round($timeOff, 2),
                    'time_off_share' => $this->safePercentage($timeOff, $total ?: 1),
                    'total_percentage' => round($total, 2),
                ];
            })
            ->values();
    }

    public function reviewerEffectiveness(int $month, int $year): Collection
    {
        $reports = LoeReport::query()
            ->with(['reviewer', 'user'])
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $reviewed = $reports->filter(fn (LoeReport $report) => $report->reviewed_by && $report->submitted_at && $report->reviewed_at);
        $byReviewer = $reviewed->groupBy('reviewed_by')->map(function (Collection $items) {
            /** @var LoeReport $sample */
            $sample = $items->first();

            return [
                'reviewer' => $sample->reviewer?->name ?? 'Unknown Reviewer',
                'reviewed_reports' => $items->count(),
                'avg_turnaround_hours' => round($items->avg(fn (LoeReport $report) => $report->submitted_at?->diffInHours($report->reviewed_at)) ?? 0, 2),
                'fastest_turnaround_hours' => round($items->min(fn (LoeReport $report) => $report->submitted_at?->diffInHours($report->reviewed_at)) ?? 0, 2),
                'slowest_turnaround_hours' => round($items->max(fn (LoeReport $report) => $report->submitted_at?->diffInHours($report->reviewed_at)) ?? 0, 2),
            ];
        })->values();

        return $byReviewer->push([
            'reviewer' => 'Pending Review Pool',
            'reviewed_reports' => $reports->where('status', 'submitted')->count(),
            'avg_turnaround_hours' => 0,
            'fastest_turnaround_hours' => 0,
            'slowest_turnaround_hours' => 0,
        ]);
    }

    public function systemEffectivenessSummary(int $month, int $year): Collection
    {
        $employees = $this->employeeBaseQuery()->with([
            'allocations',
            'loeReports' => fn ($query) => $query
                ->where('month', $month)
                ->where('year', $year)
                ->with('entries'),
        ])->get();

        $reports = $employees->flatMap->loeReports;
        $submitted = $reports->whereIn('status', ['submitted', 'approved']);
        $warningReports = $reports->filter(fn (LoeReport $report) => ! empty(LoeInsights::reportWarnings($report, $report->user)));
        $varianceAverages = $employees->map(function (User $user) {
            $report = $user->loeReports->first();
            if (! $report) {
                return null;
            }

            return abs((float) $user->allocations->sum('percentage') - (float) $report->total_percentage);
        })->filter();

        return collect([
            [
                'metric' => 'Submission Rate',
                'value' => $this->safePercentage($submitted->count(), max($employees->count(), 1)).'%',
                'insight' => 'Share of employees who moved beyond draft for the selected month.',
            ],
            [
                'metric' => 'On-Time Submission Rate',
                'value' => $this->safePercentage(
                    $submitted->filter(fn (LoeReport $report) => $this->isSubmittedOnTime($report, $report->user))->count(),
                    max($employees->count(), 1)
                ).'%',
                'insight' => 'How many employees submitted by their own month-end deadline.',
            ],
            [
                'metric' => 'Warning-Free Reports',
                'value' => $this->safePercentage(max($reports->count() - $warningReports->count(), 0), max($reports->count(), 1)).'%',
                'insight' => 'Reports with totals and variance inside acceptable thresholds.',
            ],
            [
                'metric' => 'Average Variance',
                'value' => round($varianceAverages->avg() ?? 0, 2).'%',
                'insight' => 'Average gap between allocation totals and actual LOE totals.',
            ],
            [
                'metric' => 'Average Review Turnaround',
                'value' => round($reports->filter(fn (LoeReport $report) => $report->reviewed_at && $report->submitted_at)->avg(fn (LoeReport $report) => $report->submitted_at?->diffInHours($report->reviewed_at)) ?? 0, 2).' hrs',
                'insight' => 'Average time taken by admins to approve submitted LOEs.',
            ],
        ]);
    }

    protected function employeeBaseQuery(): Builder
    {
        return User::query()
            ->where('status', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'employee'));
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

    protected function resolveLoeStatus(float $totalPercentage): string
    {
        if ($totalPercentage < 50 || $totalPercentage > 110) {
            return 'Critical';
        }

        if ($totalPercentage < 90) {
            return 'Medium';
        }

        return 'Good';
    }

    protected function resolveLoeStatusTone(float $totalPercentage): string
    {
        if ($totalPercentage < 50 || $totalPercentage > 110) {
            return 'critical';
        }

        if ($totalPercentage < 90) {
            return 'medium';
        }

        return 'good';
    }
}
