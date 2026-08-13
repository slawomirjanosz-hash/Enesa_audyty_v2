<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // Dodaj pole na opis dodatkowy jeśli nie istnieje
            if (! Schema::hasColumn('offers', 'additional_description')) {
                $table->text('additional_description')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'additional_description')) {
                $table->dropColumn('additional_description');
            }
        });
    }
};
