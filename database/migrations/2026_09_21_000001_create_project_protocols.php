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
        Schema::create('project_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_company_id')->constrained('companies')->restrictOnDelete();
            $table->string('number')->unique();
            $table->date('acceptance_date');
            $table->string('place')->nullable();
            $table->string('reference')->nullable();
            $table->string('kind');
            $table->string('outcome');
            $table->string('invoice_decision');
            $table->text('description');
            $table->text('remarks')->nullable();
            $table->date('remedy_deadline')->nullable();
            $table->text('invoice_conditions')->nullable();
            $table->text('attachments')->nullable();
            $table->string('receiver_name');
            $table->string('supplier_representative');
            $table->json('items');
            $table->json('issuer_snapshot');
            $table->json('supplier_snapshot');
            $table->longText('signature_data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        foreach (['projects.protocols.view', 'projects.protocols.manage'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('project_protocols');
        Permission::whereIn('name', ['projects.protocols.view', 'projects.protocols.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
