<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\ArrayReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
    ) {
    }

    public function index(ReportFilterRequest $request): JsonResponse
    {
        [$month, $year] = $this->resolvePeriod($request);

        return response()->json([
            'employee_monthly' => $this->reportService->employeeMonthly($request->validated('user_id')),
            'employee_yearly' => $this->reportService->employeeYearly($year, $request->validated('user_id')),
            'project_summary' => $this->reportService->projectSummary($month, $year, $request->validated('project_id')),
            'missing_submissions' => $this->reportService->missingSubmissions($month, $year),
            'allocation_variance' => $this->reportService->allocationVariance($month, $year, $request->validated('user_id')),
            'compliance_scorecard' => $this->reportService->complianceScorecard($month, $year),
            'employee_consistency' => $this->reportService->employeeConsistency($month, $year),
            'time_off_impact' => $this->reportService->timeOffImpact($month, $year),
            'reviewer_effectiveness' => $this->reportService->reviewerEffectiveness($month, $year),
            'system_effectiveness_summary' => $this->reportService->systemEffectivenessSummary($month, $year),
        ]);
    }

    public function export(ReportFilterRequest $request): Response
    {
        [$month, $year] = $this->resolvePeriod($request);
        $type = $request->validated('type', 'employee-monthly');
        $format = $request->validated('format', 'pdf');

        [$title, $rows] = match ($type) {
            'employee-yearly' => ['Employee Yearly Summary', $this->flattenYearlyRows($this->reportService->employeeYearly($year, $request->validated('user_id')))],
            'project-summary' => ['Project Monthly Summary', collect($this->reportService->projectSummary($month, $year, $request->validated('project_id')))],
            'missing-submissions' => ['Missing Submissions', collect($this->reportService->missingSubmissions($month, $year))],
            'allocation-variance' => ['Allocation Variance', $this->flattenVarianceRows($this->reportService->allocationVariance($month, $year, $request->validated('user_id')))],
            'compliance-scorecard' => ['Compliance Scorecard', collect($this->reportService->complianceScorecard($month, $year))],
            'employee-consistency' => ['Employee Consistency', collect($this->reportService->employeeConsistency($month, $year))],
            'time-off-impact' => ['Time Off Impact', collect($this->reportService->timeOffImpact($month, $year))],
            'reviewer-effectiveness' => ['Reviewer Effectiveness', collect($this->reportService->reviewerEffectiveness($month, $year))],
            'system-effectiveness-summary' => ['System Effectiveness Summary', collect($this->reportService->systemEffectivenessSummary($month, $year))],
            default => ['Employee Monthly Summary', $this->flattenMonthlyRows($this->reportService->employeeMonthly($request->validated('user_id')))],
        };

        $headings = array_keys($rows->first() ?? ['No data' => '']);

        if ($format === 'xlsx') {
            activity('exports')
                ->causedBy($request->user())
                ->withProperties([
                    'scope' => 'admin',
                    'type' => $type,
                    'format' => 'xlsx',
                    'month' => $month,
                    'year' => $year,
                ])
                ->event('exported')
                ->log('Admin exported report');

            return Excel::download(new ArrayReportExport($headings, $rows), str($title)->slug().'.xlsx');
        }

        $pdf = Pdf::loadView('exports.report', [
            'title' => $title,
            'subtitle' => sprintf('Generated for %02d/%04d', $month, $year),
            'headings' => $headings,
            'rows' => $rows,
        ]);

        activity('exports')
            ->causedBy($request->user())
            ->withProperties([
                'scope' => 'admin',
                'type' => $type,
                'format' => 'pdf',
                'month' => $month,
                'year' => $year,
            ])
            ->event('exported')
            ->log('Admin exported report');

        return $pdf->download(str($title)->slug().'.pdf');
    }

    protected function resolvePeriod(ReportFilterRequest $request): array
    {
        return [
            (int) $request->validated('month', now()->month),
            (int) $request->validated('year', now()->year),
        ];
    }

    protected function flattenMonthlyRows($reports)
    {
        return collect($reports)->map(fn ($report) => [
            'Employee' => $report['employee'],
            'Employee Code' => $report['employee_code'],
            'Stream' => $report['stream_label'] ?? $report['stream'],
            'Month' => sprintf('%02d/%04d', $report['month'], $report['year']),
            'Total Percentage' => $report['total_percentage'],
            'Status' => $report['loe_status'],
            'Submitted At' => $report['submitted_at'],
        ]);
    }

    protected function flattenYearlyRows($rows)
    {
        return collect($rows)->flatMap(fn ($row) => collect($row['monthly_breakdown'])->map(fn ($month) => [
            'Employee' => $row['employee'],
            'Employee Code' => $row['employee_code'],
            'Month' => $month['month'],
            'Average Total Percentage' => $row['average_total_percentage'],
            'Monthly Total Percentage' => $month['total_percentage'],
        ]));
    }

    protected function flattenVarianceRows($rows)
    {
        return collect($rows)->flatMap(fn ($row) => collect($row['rows'])->map(fn ($variance) => [
            'Employee' => $row['employee'],
            'Employee Code' => $row['employee_code'],
            'Project' => $variance['project'],
            'Allocated Percentage' => $variance['allocated_percentage'],
            'Actual Percentage' => $variance['actual_percentage'],
            'Variance' => $variance['variance'],
        ]));
    }
}
