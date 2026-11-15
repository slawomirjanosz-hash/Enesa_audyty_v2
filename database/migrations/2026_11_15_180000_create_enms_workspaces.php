<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enms_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('audit_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('site_id');
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['audit_id', 'site_id']);
        });
        Schema::create('enms_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained('enms_workspaces')->restrictOnDelete();
            $table->string('kind', 40);
            $table->string('symbol', 100);
            $table->unsignedInteger('current_version')->default(0);
            $table->unique(['company_id', 'kind', 'symbol']);
        });
        Schema::create('enms_record_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('record_id');
            $table->foreign('record_id')->references('id')->on('enms_records')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('symbol', 100);
            $table->unsignedInteger('workspace_revision');
            $table->string('library_version', 80);
            $table->string('scope_version', 100);
            $table->json('payload');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['record_id', 'version']);
        });
        Schema::create('enms_journal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('enms_workspaces')->restrictOnDelete();
            $table->uuid('record_id')->nullable();
            $table->unsignedInteger('record_version')->nullable();
            $table->unsignedInteger('workspace_revision');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 80);
            $table->uuid('authorization_id')->nullable();
            $table->json('details');
            $table->timestamp('created_at');
            $table->index(['workspace_id', 'workspace_revision']);
        });
        Schema::create('enms_document_files', function (Blueprint $table) {
            $table->uuid('record_id')->primary();
            $table->foreign('record_id')->references('id')->on('enms_records')->restrictOnDelete();
            $table->longText('content_base64');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enms_document_files');
        Schema::dropIfExists('enms_journal');
        Schema::dropIfExists('enms_record_versions');
        Schema::dropIfExists('enms_records');
        Schema::dropIfExists('enms_workspaces');
    }
};
