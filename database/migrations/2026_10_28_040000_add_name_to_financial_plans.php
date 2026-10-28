<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_plans', fn (Blueprint $table) => $table->string('name', 150)->nullable());
    }

    public function down(): void
    {
        Schema::table('financial_plans', fn (Blueprint $table) => $table->dropColumn('name'));
    }
};
