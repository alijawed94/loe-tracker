<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreLoeReportRequest;
use App\Http\Requests\Employee\UpdateLoeReportRequest;
use App\Models\LoeReport;
use App\Notifications\LoeSubmissionConfirmationNotification;
use App\Support\LoePeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class EmployeeLoeReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = $request->user()->loeReports()
            ->with('entries.project')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return response()->json($reports);
    }

    public function store(StoreLoeReportRequest $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->validated();
        $status = $payload['status'] ?? 'submitted';

        abort_if(
            LoeReport::query()->where('user_id', $user->id)->where('month', $payload['month'])->where('year', $payload['year'])->exists(),
            422,
            'LOE has already been submitted for this month.'
        );

        $report = DB::transaction(function () use ($user, $payload, $status) {
            $report = LoeReport::query()->create([
                'user_id' => $user->id,
                'month' => $payload['month'],
                'year' => $payload['year'],
                'total_percentage' => collect($payload['entries'])->sum('percentage'),
                'status' => $status,
                'submitted_at' => $status === 'submitted' ? now() : null,
            ]);

            $report->entries()->createMany($payload['entries']);

            return $report->load('entries.project', 'reviewer');
        });

        if ($status === 'submitted') {
            $user->notify(new LoeSubmissionConfirmationNotification($report, true));
        }

        return response()->json($report, 201);
    }

    public function show(Request $request, LoeReport $employeeLoeReport): JsonResponse
    {
        abort_unless($employeeLoeReport->user_id === $request->user()->id, 403);

        return response()->json($employeeLoeReport->load('entries.project'));
    }

    public function update(UpdateLoeReportRequest $request, LoeReport $employeeLoeReport): JsonResponse
    {
        abort_unless($employeeLoeReport->user_id === $request->user()->id, 403);
        abort_if(LoePeriod::isClosed($employeeLoeReport->month, $employeeLoeReport->year, $request->user()), 422, 'This LOE is now read-only.');

        $payload = $request->validated();
        $status = $payload['status'] ?? $employeeLoeReport->status;

        DB::transaction(function () use ($employeeLoeReport, $payload, $status) {
            $employeeLoeReport->update([
                'total_percentage' => collect($payload['entries'])->sum('percentage'),
                'status' => $status,
                'submitted_at' => $status === 'submitted' ? now() : null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]);

            $employeeLoeReport->entries()->delete();
            $employeeLoeReport->entries()->createMany($payload['entries']);
        });

        $employeeLoeReport->refresh()->load('entries.project', 'reviewer');

        if ($status === 'submitted') {
            $request->user()->notify(new LoeSubmissionConfirmationNotification($employeeLoeReport, false));
        }

        return response()->json($employeeLoeReport);
    }

    public function destroy(Request $request, LoeReport $employeeLoeReport): JsonResponse
    {
        abort_unless($employeeLoeReport->user_id === $request->user()->id, 403);

        $employeeLoeReport->delete();

        return response()->json([
            'message' => 'LOE deleted successfully.',
        ]);
    }

    public function export(Request $request): Response
    {
        $user = $request->user();
        $year = (int) $request->integer('year', now($user->timezone)->year);
        $month = (int) $request->integer('month', now($user->timezone)->month);
        $format = $request->string('format', 'pdf')->value();
        $reports = $user->loeReports()
            ->with('entries.project')
            ->where('year', $year)
            ->when($request->filled('month'), fn($query) => $query->where('month', $month))
            ->get();

        $rows = $reports->flatMap(fn($report) => $report->entries->map(fn($entry) => [
            'Month/Year' => sprintf('%02d/%04d', $report->month, $report->year),
            'Project' => $entry->project?->name,
            'Engagement Type' => $entry->project?->engagement_type_label,
            'Percentage' => (float) $entry->percentage,
        ]));

        if ($format === 'xlsx') {
            activity('exports')
                ->causedBy($user)
                ->withProperties([
                    'scope' => 'employee',
                    'format' => 'xlsx',
                    'month' => $month,
                    'year' => $year,
                ])
                ->event('exported')
                ->log('Employee exported LOE report');

            return Excel::download(
                new \App\Exports\ArrayReportExport(array_keys($rows->first() ?? ['Month' => '', 'Project' => '', 'Engagement Type' => '', 'Percentage' => '']), $rows),
                'employee-loe-report.xlsx'
            );
        }

        $pdf = Pdf::loadView('exports.report', [
            'title' => 'Employee LOE Report',
            'subtitle' => $user->name . ' (' . $user->employee_code . ')',
            'rows' => $rows,
            'headings' => array_keys($rows->first() ?? ['Month' => '', 'Project' => '', 'Engagement Type' => '', 'Percentage' => '']),
        ]);

        activity('exports')
            ->causedBy($user)
            ->withProperties([
                'scope' => 'employee',
                'format' => 'pdf',
                'month' => $month,
                'year' => $year,
            ])
            ->event('exported')
            ->log('Employee exported LOE report');

        return $pdf->download('employee-loe-report.pdf');
    }
}
