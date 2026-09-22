<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_plant_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('iso_plant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('iso_plant_sites')->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('status')->default('editing');
            $table->date('as_of_date');
            $table->json('definition');
            $table->json('answers');
            $table->json('client_approval')->nullable();
            $table->json('auditor_approval')->nullable();
            $table->json('issuer')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('iso_section_documents')->nullOnDelete();
            $table->timestamps();
            $table->unique(['audit_id', 'site_id', 'revision'], 'plant_profile_revision_unique');
        });
        Schema::create('iso_plant_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('iso_plant_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('action');
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_plant_events');
        Schema::dropIfExists('iso_plant_profiles');
        Schema::dropIfExists('iso_plant_sites');
    }
};
