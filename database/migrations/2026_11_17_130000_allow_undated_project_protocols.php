<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_protocols', fn (Blueprint $table) => $table->date('acceptance_date')->nullable()->change());
    }

    public function down(): void
    {
        if (DB::table('project_protocols')->whereNull('acceptance_date')->exists()) {
            throw new RuntimeException('Cannot require an acceptance date while undated protocols exist.');
        }

        Schema::table('project_protocols', fn (Blueprint $table) => $table->date('acceptance_date')->nullable(false)->change());
    }
};
