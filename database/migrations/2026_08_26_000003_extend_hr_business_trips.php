<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_business_trips', function (Blueprint $table): void {
            $table->dateTime('outbound_arrival_at')->nullable()->after('departure_at');
            $table->dateTime('return_departure_at')->nullable()->after('outbound_arrival_at');
            $table->decimal('outbound_travel_hours', 6, 2)->nullable()->after('travel_hours');
            $table->decimal('return_travel_hours', 6, 2)->nullable()->after('outbound_travel_hours');
            $table->decimal('km_rate', 8, 4)->default(0)->after('distance_source');
            $table->decimal('mileage_amount', 12, 2)->default(0)->after('km_rate');
            $table->decimal('diet_rate', 8, 2)->default(45)->after('mileage_amount');
            $table->decimal('diet_amount', 12, 2)->default(0)->after('diet_rate');
            $table->decimal('total_amount', 12, 2)->default(0)->after('toll_cost');
        });

        $permission = Permission::findOrCreate('hr.vehicles.all.view', 'web');
        Role::query()->whereIn('name', ['superadmin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
    }

    public function down(): void
    {
        Schema::table('hr_business_trips', function (Blueprint $table): void {
            $table->dropColumn(['outbound_arrival_at', 'return_departure_at', 'outbound_travel_hours', 'return_travel_hours', 'km_rate', 'mileage_amount', 'diet_rate', 'diet_amount', 'total_amount']);
        });
        Permission::query()->where('name', 'hr.vehicles.all.view')->delete();
    }
};
