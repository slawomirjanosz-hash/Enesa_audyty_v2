<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::getColumnType('activity_logs', 'search_text') !== 'longtext') {
            Schema::table('activity_logs', fn (Blueprint $table) => $table->longText('search_text')->nullable()->change());
        }
    }

    public function down(): void
    {
        // Do not shrink a search index containing historical entries over 64 KB.
    }
};
