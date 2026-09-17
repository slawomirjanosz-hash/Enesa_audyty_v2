<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_context_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('library_version')->default('1.3');
            $table->string('status')->default('draft');
            $table->unsignedInteger('revision')->default(0);
            $table->json('answers')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['audit_id', 'year']);
        });
        Schema::create('iso_context_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iso_context_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('revision');
            $table->string('action');
            $table->string('status');
            $table->json('answers');
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_context_revisions');
        Schema::dropIfExists('iso_context_reviews');
    }
};
