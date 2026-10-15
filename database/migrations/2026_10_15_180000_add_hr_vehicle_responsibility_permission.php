<?php

use App\Models\HrVehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('hr.vehicles.responsible', 'web');
        // Fresh installations seed system roles from the current catalog. This
        // notification subscription must instead be selected explicitly.
        $permission->roles()->detach();
        // Preserve individual recipients previously selected in HR without granting
        // responsibility to everyone sharing their existing roles.
        $ids = json_decode((string) DB::table('company_settings')->value('hr_vehicle_manager_ids'), true) ?? [];
        if ($ids !== []) {
            $role = Role::findOrCreate('hr_vehicle_responsible', 'web');
            $role->givePermissionTo($permission);
            $users = HrVehicle::eligibleResponsibleUsers()->whereIn('users.id', $ids)->get();
            foreach ($users as $user) {
                $user->assignRole($role);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'hr.vehicles.responsible')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
