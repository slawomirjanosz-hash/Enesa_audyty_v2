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
        Schema::create('project_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('number')->unique();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->boolean('is_template')->default(false);
            $table->date('issued_on')->nullable();
            $table->string('place')->nullable();
            $table->string('offer_reference')->nullable();
            $table->date('offer_date')->nullable();
            $table->string('our_contact')->nullable();
            $table->text('supplier_contact')->nullable();
            $table->json('text_sections');
            $table->json('price_sections');
            $table->json('stage_ids');
            $table->json('history');
            $table->json('document_ids');
            $table->json('attachment_snapshot');
            $table->json('issuer_snapshot');
            $table->json('supplier_snapshot')->nullable();
            $table->json('project_snapshot');
            $table->bigInteger('net_cents')->default(0);
            $table->bigInteger('vat_cents')->default(0);
            $table->unsignedTinyInteger('vat_rate')->default(23);
            $table->bigInteger('advance_cents')->default(0);
            $table->text('notes')->nullable();
            $table->text('confirmation_reference')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
        foreach (['view', 'manage'] as $ability) {
            Permission::findOrCreate('projects.work_orders.'.$ability, 'web');
        }
        Schema::table('project_protocols', fn (Blueprint $table) => $table->foreignId('work_order_id')->nullable()->constrained('project_work_orders')->restrictOnDelete());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('project_protocols', fn (Blueprint $table) => $table->dropConstrainedForeignId('work_order_id'));
        Schema::dropIfExists('project_work_orders');
        Permission::whereIn('name', ['projects.work_orders.view', 'projects.work_orders.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
