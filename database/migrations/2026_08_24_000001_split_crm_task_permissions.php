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

        $own = Permission::findOrCreate('crm.tasks.own.manage', 'web');
        $team = Permission::findOrCreate('crm.tasks.team.manage', 'web');

        Role::query()
            ->whereHas('permissions', fn ($permissions) => $permissions
                ->whereIn('name', ['crm.tasks.manage', 'system.full_access']))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$own, $team]));

        Role::findOrCreate('superadmin', 'web')->givePermissionTo([$own, $team]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Uprawnienia pozostają, aby rollback nie odebrał użytkownikom dostępu do zadań.
    }
};
