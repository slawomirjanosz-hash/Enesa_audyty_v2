<?php

use App\Services\EnergyPassportImportService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('energy_passport_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 80)->nullable();
            $table->string('scope', 20)->default('device');
            $table->string('category', 80)->nullable();
            $table->string('version', 30)->default('1.0');
            $table->string('source_filename')->unique();
            $table->json('sections');
            $table->boolean('is_builtin')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('energy_passports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('energy_passport_templates')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('asset_identifier', 120)->nullable();
            $table->string('location')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->json('responses')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $view = Permission::findOrCreate('audits.passports.view', 'web');
        $manage = Permission::findOrCreate('audits.passports.manage', 'web');
        Role::query()->whereHas('permissions', fn ($query) => $query->where('name', 'audits.view'))
            ->get()->each(fn (Role $role) => $role->givePermissionTo($view));
        Role::query()->whereHas('permissions', fn ($query) => $query->where('name', 'audits.manage'))
            ->get()->each(fn (Role $role) => $role->givePermissionTo($manage));
        Role::query()->whereIn('name', ['superadmin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$view, $manage]));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $importer = app(EnergyPassportImportService::class);
        foreach (glob(resource_path('energy-passports/templates/*.xlsx')) ?: [] as $path) {
            $importer->import($path, null, true);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('energy_passports');
        Schema::dropIfExists('energy_passport_templates');
        Permission::query()->whereIn('name', ['audits.passports.view', 'audits.passports.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
