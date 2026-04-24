<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\ArrayReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewLoeReportRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\LoeReport;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LoeReviewStatusNotification;
use App\Support\LoeInsights;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search')->value());

        $users = User::query()
            ->with('roles')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $roles = Role::query()->whereIn('name', $payload['roles'])->pluck('id');

        $user = DB::transaction(function () use ($payload, $roles) {
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'employee_code' => $payload['employee_code'] ?? null,
                'designation' => $payload['designation'] ?? null,
                'stream' => $payload['stream'] ?? null,
                'timezone' => $payload['timezone'] ?? config('app.timezone'),
                'status' => $payload['status'],
                'password' => $payload['password'],
            ]);

            $user->roles()->sync($roles);

            return $user->load('roles');
        });

        activity('users')
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties(['roles' => $payload['roles']])
            ->event('role_synced')
            ->log('User roles were assigned');

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load(['roles', 'allocations.project', 'loeReports']));
    }

    public function loeReports(User $user): JsonResponse
    {
        $user->load('roles');

        $reports = $user->loeReports()
            ->with(['entries.project', 'feedback.user.roles', 'reviewer'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('submitted_at')
            ->get()
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
                'warnings' => LoeInsights::reportWarnings($report, $user),
                'entries' => $report->entries->map(fn ($entry) => [
                    'id' => $entry->id,
                    'project_name' => $entry->project?->name,
                    'engagement_type' => $entry->project?->engagement_type,
                    'engagement_type_label' => $entry->project?->engagement_type_label,
                    'percentage' => (float) $entry->percentage,
                ])->values()->all(),
                'feedback' => $report->feedback->map(fn ($feedback) => [
                    'id' => $feedback->id,
                    'message' => $feedback->message,
                    'created_at' => optional($feedback->created_at)?->toIso8601String(),
                    'author' => [
                        'id' => $feedback->user->id,
                        'name' => $feedback->user->name,
                        'roles' => $feedback->user->roles->pluck('name')->values()->all(),
                    ],
                ])->values()->all(),
            ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_code' => $user->employee_code,
                'designation' => $user->designation,
                'stream' => $user->stream,
                'stream_label' => $user->stream_label,
                'roles' => $user->roles->pluck('name')->values()->all(),
            ],
            'reports' => $reports,
        ]);
    }

    public function reviewLoeReport(ReviewLoeReportRequest $request, User $user, LoeReport $loeReport): JsonResponse
    {
        abort_unless($loeReport->user_id === $user->id, 404);

        $loeReport->update([
            'status' => $request->validated('status'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $request->validated('review_notes'),
        ]);

        $loeReport->refresh()->load('reviewer');
        $user->notify(new LoeReviewStatusNotification($loeReport));

        return response()->json([
            'message' => 'LOE review updated successfully.',
            'report' => $loeReport,
        ]);
    }

    public function exportLoeReports(Request $request, User $user): Response
    {
        $user->load('roles');

        $reports = $user->loeReports()
            ->with('entries.project')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('submitted_at')
            ->get();

        $rows = $reports->flatMap(fn ($report) => $report->entries->map(fn ($entry) => [
            'Month / Year' => sprintf('%s %04d', now()->setMonth($report->month)->format('F'), $report->year),
            'Total' => number_format((float) $report->total_percentage, fmod((float) $report->total_percentage, 1.0) === 0.0 ? 0 : 2).'%',
            'Project' => $entry->project?->name,
            'Engagement Type' => $entry->project?->engagement_type_label,
            'Percentage' => number_format((float) $entry->percentage, fmod((float) $entry->percentage, 1.0) === 0.0 ? 0 : 2).'%',
            'Submitted At' => optional($report->submitted_at)?->format('d M Y, h:i A'),
        ]));

        $headings = array_keys($rows->first() ?? [
            'Month / Year' => '',
            'Total' => '',
            'Project' => '',
            'Engagement Type' => '',
            'Percentage' => '',
            'Submitted At' => '',
        ]);

        $format = $request->string('format', 'pdf')->value();

        if ($format === 'xlsx') {
            activity('exports')
                ->causedBy($request->user())
                ->withProperties([
                    'scope' => 'admin-user-loes',
                    'format' => 'xlsx',
                    'user_id' => $user->id,
                ])
                ->event('exported')
                ->log('Admin exported user LOE report');

            return Excel::download(new ArrayReportExport($headings, $rows), str($user->name.' loe report')->slug().'.xlsx');
        }

        $pdf = Pdf::loadView('exports.report', [
            'title' => 'User LOE Report',
            'subtitle' => $user->name.' ('.$user->employee_code.')',
            'headings' => $headings,
            'rows' => $rows,
        ]);

        activity('exports')
            ->causedBy($request->user())
            ->withProperties([
                'scope' => 'admin-user-loes',
                'format' => 'pdf',
                'user_id' => $user->id,
            ])
            ->event('exported')
            ->log('Admin exported user LOE report');

        return $pdf->download(str($user->name.' loe report')->slug().'.pdf');
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $payload = $request->validated();
        $roles = Role::query()->whereIn('name', $payload['roles'])->pluck('id');

        DB::transaction(function () use ($payload, $roles, $user) {
            $user->update([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'employee_code' => $payload['employee_code'] ?? null,
                'designation' => $payload['designation'] ?? null,
                'stream' => $payload['stream'] ?? null,
                'timezone' => $payload['timezone'] ?? config('app.timezone'),
                'status' => $payload['status'],
                ...($payload['password'] ? ['password' => $payload['password']] : []),
            ]);

            $user->roles()->sync($roles);
        });

        activity('users')
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties(['roles' => $payload['roles']])
            ->event('role_synced')
            ->log('User roles were synced');

        return response()->json($user->fresh()->load('roles'));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => 'User archived successfully.']);
    }
}
