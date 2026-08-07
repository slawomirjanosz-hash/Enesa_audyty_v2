<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('company_type', 20)->default('client')->after('name')->index();
            $table->text('supplier_capabilities')->nullable()->after('notes');
            $table->text('supplier_materials')->nullable()->after('supplier_capabilities');
        });

        Schema::table('project_requirements', function (Blueprint $table) {
            $table->foreignId('supplier_company_id')->nullable()->after('supplier')->constrained('companies')->nullOnDelete();
        });

        Schema::table('project_financial_entries', function (Blueprint $table) {
            $table->foreignId('supplier_company_id')->nullable()->after('supplier')->constrained('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_financial_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_company_id');
        });
        Schema::table('project_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_company_id');
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['company_type']);
            $table->dropColumn(['company_type', 'supplier_capabilities', 'supplier_materials']);
        });
    }
};
