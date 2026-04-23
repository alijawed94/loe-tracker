<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;

trait InteractsWithRoles
{
    protected function ensureBaseRoles(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin'], ['label' => 'Admin']);
        Role::query()->firstOrCreate(['name' => 'employee'], ['label' => 'Employee']);
    }

    protected function createUserWithRoles(array $roles, array $attributes = []): User
    {
        $this->ensureBaseRoles();

        $user = User::factory()->create($attributes);
        $roleIds = Role::query()->whereIn('name', $roles)->pluck('id');
        $user->roles()->sync($roleIds);

        return $user->fresh('roles');
    }
}
