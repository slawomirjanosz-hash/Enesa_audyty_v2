<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cylinders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('serial_number', 100);
            $table->string('manufacturer', 160)->nullable();
            $table->string('type', 160);
            $table->unsignedSmallInteger('manufactured_year')->nullable();
            $table->decimal('capacity_litres', 10, 3)->nullable();
            $table->decimal('working_pressure_bar', 10, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['company_id', 'serial_number']);
        });
        Schema::create('cylinder_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cylinder_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('inspector_name');
            $table->date('inspected_at')->index();
            $table->date('next_due_at')->nullable()->index();
            $table->string('result', 30);
            $table->text('observations');
            $table->timestamps();
        });
        foreach (['cylinders.view', 'cylinders.manage'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinder_inspections');
        Schema::dropIfExists('cylinders');
        Permission::whereIn('name', ['cylinders.view', 'cylinders.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
