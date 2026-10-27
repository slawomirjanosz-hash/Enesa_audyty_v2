<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_plan_lines', fn (Blueprint $table) => $table->boolean('enabled')->default(true));
    }

    public function down(): void
    {
        Schema::table('financial_plan_lines', fn (Blueprint $table) => $table->dropColumn('enabled'));
    }
};
