<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_section_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('section_id', 40)->index();
            $table->enum('scope', ['template', 'client'])->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('document_year')->nullable()->index();
            $table->string('version_number', 40)->default('1.0');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['audit_id', 'section_id', 'scope'], 'iso_docs_audit_section_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_section_documents');
    }
};
