<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect([
            'projects.requirements.material_prices.view',
            'projects.requirements.service_prices.view',
        ])->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::query()
            ->whereHas('permissions', fn ($query) => $query->whereIn('name', [
                'projects.requirements.view',
                'projects.requirements.manage',
            ]))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', [
            'projects.requirements.material_prices.view',
            'projects.requirements.service_prices.view',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
