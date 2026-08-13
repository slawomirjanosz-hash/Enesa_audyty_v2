<?php

use App\Support\RolePermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RolePermissionCatalog::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $all = RolePermissionCatalog::names();
        $staff = collect($all)->reject(fn (string $name) => $name === 'settings.company.manage')->values()->all();
        $auditor = [
            'dashboard.view', 'crm.view', 'offers.view', 'offers.prices.view',
            'projects.view', 'audits.view', 'documents.view', 'client_zone.view',
        ];

        Role::findOrCreate('superadmin', 'web')->syncPermissions($all);
        Role::findOrCreate('admin', 'web')->syncPermissions($staff);
        Role::findOrCreate('auditor_senior', 'web')->syncPermissions($staff);
        Role::findOrCreate('auditor', 'web')->syncPermissions($auditor);

        DB::table('users')
            ->where('name', 'Super Admin ENESA')
            ->update(['name' => 'Super Admin']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Uprawnienia pozostają, aby rollback nie odebrał użytkownikom dostępu.
    }
};
