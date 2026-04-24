<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoeEntry;
use App\Models\LoeReport;
use App\Models\Project;
use App\Support\LoeInsights;
use App\Support\LoePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'allocations.project',
            'loeReports.entries.project',
            'loeReports.feedback.user.roles',
            'loeReports.reviewer',
        ]);

        $now = now($user->timezone);
        $currentReport = $user->loeReports->first(
            fn ($report) => $report->month === $now->month && $report->year === $now->year
        );
        $deadline = LoePeriod::deadline($now->month, $now->year, $user);
        $currentPeriodStatus = match (true) {
            $currentReport?->status === 'approved' => 'approved',
            $currentReport?->status === 'submitted' => 'submitted',
            $currentReport?->status === 'draft' && $deadline->isPast() => 'overdue',
            $currentReport?->status === 'draft' => 'draft',
            $deadline->isPast() => 'overdue',
            default => 'not_started',
        };

        $reports = $user->loeReports
            ->sortByDesc(fn ($report) => sprintf('%04d-%02d', $report->year, $report->month))
            ->values()
            ->map(fn ($report) => [
                'id' => $report->id,
                'month' => $report->month,
                'year' => $report->year,
                'total_percentage' => (float) $report->total_percentage,
                'status' => $report->status,
                'submitted_at' => optional($report->submitted_at)?->toIso8601String(),
                'reviewed_at' => optional($report->reviewed_at)?->toIso8601String(),
                'review_notes' => $report->review_notes,
                'reviewer_name' => $report->reviewer?->name,
                'is_locked' => LoePeriod::isClosed($report->month, $report->year, $user),
                'warnings' => LoeInsights::reportWarnings($report, $user),
                'entries' => $report->entries->map(fn ($entry) => $this->transformEntry($entry))->values(),
                'feedback' => $report->feedback->map(fn ($feedback) => [
                    'id' => $feedback->id,
                    'message' => $feedback->message,
                    'created_at' => optional($feedback->created_at)?->toIso8601String(),
                    'author' => [
                        'id' => $feedback->user->id,
                        'name' => $feedback->user->name,
                        'roles' => $feedback->user->roles->pluck('name')->values()->all(),
                    ],
                ])->values(),
            ]);

        $currentEntries = collect($currentReport?->entries ?? []);
        $currentProjectTotal = round($currentEntries->where('entry_type', LoeEntry::ENTRY_TYPE_PROJECT)->sum('percentage'), 2);
        $currentTimeOffTotal = round($currentEntries->where('entry_type', LoeEntry::ENTRY_TYPE_TIME_OFF)->sum('percentage'), 2);
        $currentTotal = (float) ($currentReport?->total_percentage ?? 0);
        $allocationTotal = round((float) $user->allocations->sum('percentage'), 2);
        $variance = round(abs($allocationTotal - $currentTotal), 2);
        $previousReport = $user->loeReports
            ->where(fn ($report) => sprintf('%04d-%02d', $report->year, $report->month) < sprintf('%04d-%02d', $now->year, $now->month))
            ->sortByDesc(fn ($report) => sprintf('%04d-%02d', $report->year, $report->month))
            ->first();

        $trend = collect(range(5, 0))->reverse()->map(function ($offset) use ($user, $now) {
            $period = CarbonImmutable::create($now->year, $now->month, 1, 0, 0, 0, $user->timezone)->subMonthsNoOverflow($offset);
            /** @var LoeReport|null $report */
            $report = $user->loeReports->first(fn ($item) => $item->month === $period->month && $item->year === $period->year);
            $entries = collect($report?->entries ?? []);

            return [
                'month' => $period->format('M Y'),
                'total_percentage' => (float) ($report?->total_percentage ?? 0),
                'project_percentage' => round($entries->where('entry_type', LoeEntry::ENTRY_TYPE_PROJECT)->sum('percentage'), 2),
                'time_off_percentage' => round($entries->where('entry_type', LoeEntry::ENTRY_TYPE_TIME_OFF)->sum('percentage'), 2),
            ];
        })->push([
            'month' => $now->format('M Y'),
            'total_percentage' => $currentTotal,
            'project_percentage' => $currentProjectTotal,
            'time_off_percentage' => $currentTimeOffTotal,
        ])->values();

        $insights = collect();
        $insights->push([
            'title' => 'Current month coverage',
            'message' => $currentTotal > 0
                ? sprintf('You have logged %s so far, with %s still unassigned.', round($currentTotal, 2).'%', max(100 - $currentTotal, 0).'%')
                : 'You have not logged any LOE for the current month yet.',
        ]);
        $insights->push([
            'title' => 'Time off impact',
            'message' => $currentTimeOffTotal > 0
                ? sprintf('Time off accounts for %s of this month.', round($currentTimeOffTotal, 2).'%')
                : 'No time off has been logged for this month.',
        ]);
        $insights->push([
            'title' => 'Allocation variance',
            'message' => $allocationTotal > 0
                ? sprintf('Your LOE differs from your allocations by %s.', round($variance, 2).'%')
                : 'No current allocations are assigned, so variance tracking is not active.',
        ]);
        if ($previousReport) {
            $delta = round($currentTotal - (float) $previousReport->total_percentage, 2);
            $insights->push([
                'title' => 'Month over month',
                'message' => sprintf(
                    'Compared to %s, your total LOE is %s by %s.',
                    CarbonImmutable::create($previousReport->year, $previousReport->month, 1)->format('F Y'),
                    $delta >= 0 ? 'up' : 'down',
                    abs($delta).'%',
                ),
            ]);
        }

        return response()->json([
            'current_period' => [
                'month' => $now->month,
                'year' => $now->year,
                'deadline' => $deadline->toIso8601String(),
                'status' => $currentPeriodStatus,
            ],
            'current_report' => $currentReport ? [
                'id' => $currentReport->id,
                'month' => $currentReport->month,
                'year' => $currentReport->year,
                'total_percentage' => (float) $currentReport->total_percentage,
                'status' => $currentReport->status,
                'submitted_at' => optional($currentReport->submitted_at)?->toIso8601String(),
                'reviewed_at' => optional($currentReport->reviewed_at)?->toIso8601String(),
                'review_notes' => $currentReport->review_notes,
                'reviewer_name' => $currentReport->reviewer?->name,
                'is_locked' => LoePeriod::isClosed($currentReport->month, $currentReport->year, $user),
                'warnings' => LoeInsights::reportWarnings($currentReport, $user),
                'entries' => $currentReport->entries->map(fn ($entry) => $this->transformEntry($entry))->values(),
                'feedback' => $currentReport->feedback->map(fn ($feedback) => [
                    'id' => $feedback->id,
                    'message' => $feedback->message,
                    'created_at' => optional($feedback->created_at)?->toIso8601String(),
                    'author' => [
                        'id' => $feedback->user->id,
                        'name' => $feedback->user->name,
                        'roles' => $feedback->user->roles->pluck('name')->values()->all(),
                    ],
                ])->values(),
            ] : null,
            'reports' => $reports,
            'metrics' => [
                'current_total' => $currentTotal,
                'remaining_percentage' => round(max(100 - $currentTotal, 0), 2),
                'project_percentage' => $currentProjectTotal,
                'time_off_percentage' => $currentTimeOffTotal,
                'allocation_variance' => $variance,
                'projects_logged' => $currentEntries->where('entry_type', LoeEntry::ENTRY_TYPE_PROJECT)->count(),
                'time_off_entries' => $currentEntries->where('entry_type', LoeEntry::ENTRY_TYPE_TIME_OFF)->count(),
            ],
            'charts' => [
                'current_breakdown' => [
                    ['label' => 'Project Work', 'value' => $currentProjectTotal],
                    ['label' => 'Time Off', 'value' => $currentTimeOffTotal],
                ],
                'six_month_trend' => $trend,
                'allocation_vs_actual' => [
                    ['label' => 'Allocated', 'value' => $allocationTotal],
                    ['label' => 'Actual', 'value' => $currentTotal],
                ],
            ],
            'insights' => $insights->values(),
            'allocations' => $user->allocations->map(fn ($allocation) => [
                'id' => $allocation->id,
                'project_id' => $allocation->project_id,
                'project_name' => $allocation->project?->name,
                'percentage' => (float) $allocation->percentage,
            ])->values(),
            'prefill_entries' => $user->allocations->map(fn ($allocation) => [
                'entry_type' => LoeEntry::ENTRY_TYPE_PROJECT,
                'project_id' => $allocation->project_id,
                'time_off_type' => null,
                'project_name' => $allocation->project?->name,
                'entry_label' => $allocation->project?->name,
                'percentage' => (float) $allocation->percentage,
            ])->values(),
            'time_off_types' => collect(LoeEntry::TIME_OFF_TYPES)->map(fn ($type) => [
                'value' => $type,
                'label' => str($type)->replace('_', ' ')->title()->value(),
            ])->values(),
            'projects' => Project::query()
                ->where('status', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'engagement' => $project->engagement,
                    'engagement_type' => $project->engagement_type,
                    'engagement_type_label' => $project->engagement_type_label,
                ]),
        ]);
    }

    protected function transformEntry(LoeEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entry_type' => $entry->entry_type,
            'entry_type_label' => $entry->entryTypeLabel(),
            'project_id' => $entry->project_id,
            'project_name' => $entry->project?->name,
            'time_off_type' => $entry->time_off_type,
            'time_off_type_label' => $entry->timeOffTypeLabel(),
            'entry_label' => $entry->displayName(),
            'engagement_type' => $entry->entry_type === LoeEntry::ENTRY_TYPE_TIME_OFF ? 'time_off' : $entry->project?->engagement_type,
            'engagement_type_label' => $entry->entry_type === LoeEntry::ENTRY_TYPE_TIME_OFF ? 'Time Off' : $entry->project?->engagement_type_label,
            'percentage' => (float) $entry->percentage,
        ];
    }
}
