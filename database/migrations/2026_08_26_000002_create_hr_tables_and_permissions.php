<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_settings')->whereNotNull('enabled_modules')->get(['id', 'enabled_modules'])->each(function (object $settings): void {
            $modules = json_decode((string) $settings->enabled_modules, true);
            if (is_array($modules) && ! in_array('hr', $modules, true)) {
                $modules[] = 'hr';
                DB::table('company_settings')->where('id', $settings->id)->update(['enabled_modules' => json_encode(array_values($modules))]);
            }
        });

        Schema::create('hr_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('private');
            $table->string('name');
            $table->string('registration_number', 30);
            $table->string('make_model')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['registration_number', 'user_id']);
        });

        Schema::create('hr_business_trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('hr_vehicles')->nullOnDelete();
            $table->string('purpose');
            $table->dateTime('departure_at');
            $table->dateTime('return_at')->nullable();
            $table->unsignedSmallInteger('days')->default(1);
            $table->decimal('travel_hours', 6, 2)->nullable();
            $table->string('origin');
            $table->string('destination');
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->string('distance_source', 20)->default('manual');
            $table->string('vehicle_type', 20);
            $table->string('vehicle_name')->nullable();
            $table->string('registration_number', 30)->nullable();
            $table->decimal('toll_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('started_at')->nullable();
            $table->time('finished_at')->nullable();
            $table->string('status', 30)->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'work_date']);
        });

        $permissions = collect(['hr.delegations.view', 'hr.attendance.view', 'hr.team.view'])
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::query()->whereIn('name', ['superadmin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));
    }

    public function down(): void
    {
        DB::table('company_settings')->whereNotNull('enabled_modules')->get(['id', 'enabled_modules'])->each(function (object $settings): void {
            $modules = array_values(array_filter(json_decode((string) $settings->enabled_modules, true) ?: [], fn (string $module): bool => $module !== 'hr'));
            DB::table('company_settings')->where('id', $settings->id)->update(['enabled_modules' => json_encode($modules)]);
        });
        Schema::dropIfExists('hr_attendances');
        Schema::dropIfExists('hr_business_trips');
        Schema::dropIfExists('hr_vehicles');
        Permission::query()->whereIn('name', ['hr.delegations.view', 'hr.attendance.view', 'hr.team.view'])->delete();
    }
};
