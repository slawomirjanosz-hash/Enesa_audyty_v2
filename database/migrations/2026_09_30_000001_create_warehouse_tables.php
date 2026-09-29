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
        Schema::create('warehouse_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 80)->unique();
            $table->string('name', 200)->index();
            $table->string('category', 100)->nullable()->index();
            $table->string('location', 100)->nullable()->index();
            $table->string('unit', 20);
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('minimum_stock', 14, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('warehouse_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->string('type', 20)->index();
            $table->date('document_date')->index();
            $table->uuid('submission_token')->unique();
            $table->string('payload_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('project_label')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->string('reference', 200)->nullable();
            $table->text('notes');
            $table->timestamps();
        });
        Schema::create('warehouse_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_item_id')->constrained()->restrictOnDelete();
            $table->string('sku', 80);
            $table->string('name', 200);
            $table->string('unit', 20);
            $table->decimal('quantity_before', 14, 3);
            $table->decimal('quantity_change', 14, 3);
            $table->decimal('quantity_after', 14, 3);
            $table->decimal('unit_cost', 12, 2);
            $table->unique(['warehouse_document_id', 'warehouse_item_id'], 'warehouse_document_item_unique');
        });
        foreach (['view', 'manage', 'receive', 'issue', 'adjust'] as $ability) {
            $permission = Permission::findOrCreate('warehouse.'.$ability, 'web');
            foreach (Role::whereIn('name', ['admin', 'superadmin'])->get() as $role) {
                $role->givePermissionTo($permission);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_document_lines');
        Schema::dropIfExists('warehouse_documents');
        Schema::dropIfExists('warehouse_items');
        Permission::where('name', 'like', 'warehouse.%')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
