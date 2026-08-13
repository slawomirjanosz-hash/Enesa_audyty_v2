<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE offers MODIFY status ENUM('w_toku', 'wygrana', 'przegrana', 'w_negocjacji', 'zarchiwizowana') NOT NULL DEFAULT 'w_toku'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE offers MODIFY status ENUM('w_toku', 'wygrana', 'przegrana', 'zarchiwizowana') NOT NULL DEFAULT 'w_toku'");
        }
    }
};
