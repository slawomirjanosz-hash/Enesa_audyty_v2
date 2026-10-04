<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_system_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('iso_plant_sites')->cascadeOnDelete();
            $table->string('section', 3);
            $table->string('status')->default('editing');
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('source_hash', 64);
            foreach (['answers', 'source_snapshot', 'client_approval', 'auditor_approval', 'client_changes', 'auditor_changes', 'issuer'] as $field) {
                $table->json($field)->nullable();
            }
            $table->text('review_note')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('iso_section_documents')->nullOnDelete();
            $table->timestamps();
            $table->unique(['audit_id', 'site_id', 'section']);
        });
        Schema::create('iso_system_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('iso_system_reviews')->cascadeOnDelete();
            $table->string('user_name');
            $table->string('action');
            $table->json('snapshot');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_system_events');
        Schema::dropIfExists('iso_system_reviews');
    }
};
