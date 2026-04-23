<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('roles')
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
