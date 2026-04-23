<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAllocationRequest;
use App\Http\Requests\Admin\UpdateAllocationRequest;
use App\Models\Allocation;
use Illuminate\Http\JsonResponse;

class AllocationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Allocation::query()->with(['user', 'project'])->orderByDesc('created_at')->get()
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
