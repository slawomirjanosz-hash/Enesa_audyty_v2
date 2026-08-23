<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $pairs = [
            'projects.schedule.manage' => 'projects.schedule.view',
            'projects.finances.manage' => 'projects.finances.view',
            'projects.requirements.manage' => 'projects.requirements.view',
            'projects.documents.manage' => 'projects.documents.view',
        ];

        foreach ($pairs as $manage => $view) {
            $viewPermission = Permission::findOrCreate($view, 'web');
            Role::query()
                ->whereHas('permissions', fn ($permissions) => $permissions->where('name', $manage))
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($viewPermission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', [
            'projects.schedule.view',
            'projects.finances.view',
            'projects.requirements.view',
            'projects.documents.view',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
