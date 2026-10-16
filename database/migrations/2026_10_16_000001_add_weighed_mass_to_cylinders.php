<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['weighed_empty_mass_kg', 'mass_loss_kg'] as $column) {
            if (! Schema::hasColumn('cylinders', $column)) {
                Schema::table('cylinders', fn (Blueprint $table) => $table->decimal($column, 10, 3)->nullable());
            }
        }
    }

    public function down(): void
    {
        Schema::table('cylinders', fn (Blueprint $table) => $table->dropColumn(['weighed_empty_mass_kg', 'mass_loss_kg']));
    }
};
