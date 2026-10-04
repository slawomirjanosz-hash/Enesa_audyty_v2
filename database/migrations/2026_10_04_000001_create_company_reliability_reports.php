<?php

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
        Schema::create('company_reliability_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->json('snapshot');
            $table->string('stored_path');
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('storage_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'id']);
        });
        foreach (['view', 'create', 'delete'] as $action) {
            $permission = Permission::findOrCreate('company_reliability.'.$action, 'web');
            // Opt-in for employees, including roles already holding broad operational access.
            foreach (['admin', 'superadmin'] as $role) {
                Role::findOrCreate($role, 'web')->givePermissionTo($permission);
            }
            Role::whereNotIn('name', ['admin', 'superadmin'])->get()->each(fn ($role) => $role->revokePermissionTo($permission));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('company_reliability_reports');
    }
};
