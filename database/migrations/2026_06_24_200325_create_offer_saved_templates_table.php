<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offer_saved_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->text('content_subject')->nullable();
            $table->text('content_scope')->nullable();
            $table->text('content_deadline')->nullable();
            $table->text('content_payment')->nullable();
            $table->json('price_sections')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_saved_templates');
    }
};
