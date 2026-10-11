<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iso_energy_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('iso_plant_sites')->cascadeOnDelete();
            $table->json('cards');
            $table->timestamps();
            $table->unique(['audit_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iso_energy_cards');
    }
};
