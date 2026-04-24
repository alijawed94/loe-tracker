<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->when($request->filled('log_name'), fn ($query) => $query->where('log_name', $request->string('log_name')->value()))
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->string('event')->value()))
            ->when($request->filled('subject'), fn ($query) => $query->where('subject_type', 'like', '%'.$request->string('subject')->value()))
            ->when($request->filled('causer_id'), fn ($query) => $query->where('causer_id', $request->string('causer_id')->value()))
            ->latest()
            ->paginate(20)
            ->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'created_at' => optional($activity->created_at)?->toIso8601String(),
                'causer' => $activity->causer ? [
                    'id' => $activity->causer->id,
                    'name' => $activity->causer->name,
                    'email' => $activity->causer->email,
                ] : null,
                'subject' => $activity->subject ? [
                    'id' => $activity->subject->getKey(),
                    'type' => class_basename($activity->subject_type),
                ] : null,
                'properties' => $activity->properties,
            ]);

        $actors = Activity::query()
            ->with('causer')
            ->whereNotNull('causer_id')
            ->get()
            ->map(fn (Activity $activity) => $activity->causer ? [
                'value' => $activity->causer->id,
                'label' => $activity->causer->name.' ('.$activity->causer->email.')',
            ] : null)
            ->filter()
            ->unique('value')
            ->values();

        return response()->json([
            ...$activities->toArray(),
            'filter_options' => [
                'actors' => $actors,
            ],
        ]);
    }
}
