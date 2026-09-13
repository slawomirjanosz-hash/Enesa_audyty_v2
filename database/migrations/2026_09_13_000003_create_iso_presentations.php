<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_presentations', function (Blueprint $table) {
            $table->id();
            $table->string('section_id', 40)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->unsignedBigInteger('source_size');
            $table->unsignedInteger('slide_count');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('iso_presentation_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iso_presentation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->longText('image_data');
            $table->unique(['iso_presentation_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_presentation_slides');
        Schema::dropIfExists('iso_presentations');
    }
};
