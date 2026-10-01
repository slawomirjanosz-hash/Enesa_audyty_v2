<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_stakeholder_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('iso_plant_sites')->cascadeOnDelete();
            $table->unsignedBigInteger('source_profile_id');
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('status')->default('editing');
            $table->string('source_hash', 64);
            foreach (['answers', 'basis', 'consultant', 'analysis', 'compliance_register', 'auditor_changes', 'client_changes', 'client_approval', 'auditor_approval', 'issuer'] as $field) {
                $table->json($field)->nullable();
            }
            $table->text('review_note')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('iso_section_documents')->nullOnDelete();
            $table->foreignId('compliance_document_id')->nullable()->constrained('iso_section_documents')->nullOnDelete();
            $table->timestamps();
            $table->unique(['audit_id', 'site_id']);
        });
        Schema::create('iso_stakeholder_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('iso_stakeholder_reviews')->cascadeOnDelete();
            $table->string('user_name');
            $table->string('action');
            $table->json('snapshot');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_stakeholder_events');
        Schema::dropIfExists('iso_stakeholder_reviews');
    }
};
