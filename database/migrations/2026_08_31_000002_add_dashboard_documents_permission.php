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
        $permission = Permission::findOrCreate('dashboard.documents.view', 'web');

        Role::query()->get()->each(function (Role $role) use ($permission): void {
            if ($role->hasPermissionTo('dashboard.view') && $role->hasPermissionTo('documents.view')) {
                $role->givePermissionTo($permission);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->where('name', 'dashboard.documents.view')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
