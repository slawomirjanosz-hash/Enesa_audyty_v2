<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_finance_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        Schema::table('project_financial_entries', function (Blueprint $table) {
            $table->foreignId('finance_group_id')->nullable()->after('project_id')->constrained('project_finance_groups')->nullOnDelete();
            $table->string('supplier')->nullable()->after('document_number');
            $table->date('payment_date')->nullable()->after('entry_date');
            $table->string('source', 30)->default('manual')->after('status');
            $table->unsignedInteger('import_row_order')->nullable()->after('source');
            $table->string('import_fingerprint', 64)->nullable()->after('import_row_order');
            $table->unique(['project_id', 'import_fingerprint'], 'project_finance_import_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::table('project_financial_entries', function (Blueprint $table) {
            $table->dropUnique('project_finance_import_fingerprint_unique');
            $table->dropConstrainedForeignId('finance_group_id');
            $table->dropColumn(['supplier', 'payment_date', 'source', 'import_row_order', 'import_fingerprint']);
        });

        Schema::dropIfExists('project_finance_groups');
    }
};
