<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iso_system_reviews', function (Blueprint $table) {
            $table->json('consultant')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('iso_system_reviews', fn (Blueprint $table) => $table->dropColumn('consultant'));
    }
};
