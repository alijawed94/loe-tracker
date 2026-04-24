<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAllocationRequest;
use App\Http\Requests\Admin\UpdateAllocationRequest;
use App\Models\Allocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search')->value());
        $projectIds = collect($request->input('project_ids', []))
            ->filter()
            ->values()
            ->all();

        return response()->json(
            Allocation::query()
                ->with(['user', 'project'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($innerQuery) use ($search) {
                        $innerQuery
                            ->where('id', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('employee_code', 'like', "%{$search}%");
                            })
                            ->orWhereHas('project', function ($projectQuery) use ($search) {
                                $projectQuery
                                    ->where('id', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($projectIds !== [], fn ($query) => $query->whereIn('project_id', $projectIds))
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function store(StoreAllocationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $this->ensureAllocationTotalWithinLimit($payload['user_id'], (float) $payload['percentage']);

        $allocation = Allocation::query()->create($payload);

        return response()->json($allocation->load(['user', 'project']), 201);
    }

    public function show(Allocation $allocation): JsonResponse
    {
        return response()->json($allocation->load(['user', 'project']));
    }

    public function update(UpdateAllocationRequest $request, Allocation $allocation): JsonResponse
    {
        $payload = $request->validated();
        $this->ensureAllocationTotalWithinLimit($payload['user_id'], (float) $payload['percentage'], $allocation->id);

        $allocation->update($payload);

        return response()->json($allocation->fresh()->load(['user', 'project']));
    }

    public function destroy(Allocation $allocation): JsonResponse
    {
        $allocation->delete();

        return response()->json(['message' => 'Allocation removed successfully.']);
    }

    protected function ensureAllocationTotalWithinLimit(string $userId, float $percentage, ?string $ignoreId = null): void
    {
        $existingTotal = Allocation::query()
            ->where('user_id', $userId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->sum('percentage');

        abort_if(($existingTotal + $percentage) > 100, 422, 'Total allocation cannot exceed 100%.');
    }
}
