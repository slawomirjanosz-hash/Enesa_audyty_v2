<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::findOrCreate('hr.leaves.view', 'web');

        Role::query()->whereIn('name', ['superadmin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        Role::query()->whereHas('permissions', fn ($query) => $query->whereIn('name', ['hr.delegations.view', 'hr.attendance.view']))
            ->get()->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'hr.leaves.view')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
