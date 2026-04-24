<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoeFeedbackRequest;
use App\Models\LoeFeedback;
use App\Models\LoeReport;
use App\Models\User;
use App\Notifications\LoeFeedbackNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class LoeFeedbackController extends Controller
{
    public function index(Request $request, LoeReport $loeReport): JsonResponse
    {
        $this->authorizeAccess($request->user(), $loeReport);

        $loeReport->load(['feedback.user.roles']);

        return response()->json([
            'report_id' => $loeReport->id,
            'feedback' => $loeReport->feedback->map(fn (LoeFeedback $feedback) => $this->serializeFeedback($feedback))->values(),
        ]);
    }

    public function store(LoeFeedbackRequest $request, LoeReport $loeReport): JsonResponse
    {
        $user = $request->user()->loadMissing('roles');

        $this->authorizeAccess($user, $loeReport);

        $feedback = $loeReport->feedback()->create([
            'user_id' => $user->id,
            'message' => $request->validated('message'),
        ]);

        $feedback->load(['user.roles', 'loeReport.user.roles']);

        Notification::send($this->resolveRecipients($feedback), new LoeFeedbackNotification($feedback));

        return response()->json($this->serializeFeedback($feedback), 201);
    }

    protected function authorizeAccess(User $user, LoeReport $loeReport): void
    {
        abort_unless(
            $loeReport->user_id === $user->id || $user->roles()->where('name', 'admin')->exists(),
            403
        );
    }

    protected function resolveRecipients(LoeFeedback $feedback)
    {
        $feedback->loadMissing(['user.roles', 'loeReport.user.roles']);
        $author = $feedback->user;
        $reportOwner = $feedback->loeReport->user;

        if ($author->roles->contains('name', 'admin')) {
            return collect([$reportOwner])->filter(fn ($user) => $user->id !== $author->id);
        }

        return User::query()
            ->where('status', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->whereKeyNot($author->id)
            ->get();
    }

    protected function serializeFeedback(LoeFeedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'message' => $feedback->message,
            'created_at' => optional($feedback->created_at)?->toIso8601String(),
            'author' => [
                'id' => $feedback->user->id,
                'name' => $feedback->user->name,
                'email' => $feedback->user->email,
                'roles' => $feedback->user->roles->pluck('name')->values()->all(),
            ],
        ];
    }
}
