<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_form_id')->constrained('offer_forms')->cascadeOnDelete();
            $table->integer('version_number');
            $table->longText('html_content')->nullable();
            $table->boolean('is_current')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_form_versions');
    }
};
