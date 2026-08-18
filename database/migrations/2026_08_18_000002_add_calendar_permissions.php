<?php

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

        $calendarView = Permission::findOrCreate('calendar.view', 'web');
        $calendarTeamView = Permission::findOrCreate('calendar.team.view', 'web');

        Role::query()
            ->whereHas('permissions', fn ($permissions) => $permissions->where('name', 'crm.view'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($calendarView));

        Role::query()
            ->whereHas('permissions', fn ($permissions) => $permissions->whereIn('name', ['system.full_access', 'crm.tasks.manage']))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($calendarTeamView));

        Role::findOrCreate('superadmin', 'web')->givePermissionTo([$calendarView, $calendarTeamView]);

        DB::table('company_settings')->whereNotNull('enabled_modules')->get(['id', 'enabled_modules'])
            ->each(function (object $settings): void {
                $modules = json_decode($settings->enabled_modules, true);
                if (is_array($modules) && ! in_array('calendar', $modules, true)) {
                    $modules[] = 'calendar';
                    DB::table('company_settings')->where('id', $settings->id)->update([
                        'enabled_modules' => json_encode(array_values($modules)),
                    ]);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Uprawnienia pozostają, aby rollback nie odebrał użytkownikom dostępu.
    }
};
