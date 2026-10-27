<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_plan_lines', fn (Blueprint $table) => $table->text('offer_schedule')->nullable());
    }

    public function down(): void
    {
        Schema::table('financial_plan_lines', fn (Blueprint $table) => $table->dropColumn('offer_schedule'));
    }
};
