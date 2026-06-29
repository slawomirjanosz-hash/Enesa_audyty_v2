<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE offers MODIFY status ENUM('w_toku', 'wygrana', 'przegrana', 'w_negocjacji', 'zarchiwizowana') NOT NULL DEFAULT 'w_toku'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE offers MODIFY status ENUM('w_toku', 'wygrana', 'przegrana', 'zarchiwizowana') NOT NULL DEFAULT 'w_toku'");
    }
};
