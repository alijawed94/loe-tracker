<?php

namespace App\Services;

use App\Models\LoeReport;
use App\Models\Project;
use App\Models\User;
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
                    'project' => $entry->project?->name,
                    'engagement_type' => $entry->project?->engagement_type,
                    'engagement_type_label' => $entry->project?->engagement_type_label,
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
            $actualByProject = collect($report?->entries ?? [])->mapWithKeys(fn ($entry) => [$entry->project_id => (float) $entry->percentage]);

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
