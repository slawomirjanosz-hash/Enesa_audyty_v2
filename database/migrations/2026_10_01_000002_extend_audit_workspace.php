<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_finance_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['audit_id', 'name']);
        });
        Schema::table('audit_financial_entries', function (Blueprint $table) {
            $table->foreignId('finance_group_id')->nullable()->constrained('audit_finance_groups')->nullOnDelete();
            $table->string('supplier')->nullable();
            $table->foreignId('supplier_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->date('payment_date')->nullable();
            $table->string('source')->nullable();
            $table->unsignedInteger('import_row_order')->nullable();
            $table->string('import_fingerprint', 64)->nullable();
            $table->unique(['audit_id', 'import_fingerprint']);
        });
        Schema::create('audit_document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['audit_id', 'name']);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('audit_document_folder_id')->nullable()->constrained('audit_document_folders')->nullOnDelete();
        });
        Schema::create('document_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('url');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('audits', function (Blueprint $table) {
            $table->string('public_gantt_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        // Preserve financial records, folder assignments and shared links on rollback.
    }
};
