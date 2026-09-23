<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iso_plant_profiles', fn (Blueprint $table) => $table->json('client_changes')->nullable());
    }

    public function down(): void
    {
        Schema::table('iso_plant_profiles', fn (Blueprint $table) => $table->dropColumn('client_changes'));
    }
};
