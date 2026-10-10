<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['weight_kg', 'working_pressure_bar'] as $column) {
            if (! Schema::hasColumn('cylinder_inspections', $column)) {
                Schema::table('cylinder_inspections', fn (Blueprint $table) => $table->decimal($column, 10, 3)->nullable());
            }
        }
        if (! Schema::hasColumn('cylinder_photos', 'cylinder_inspection_id')) {
            Schema::table('cylinder_photos', fn (Blueprint $table) => $table->foreignId('cylinder_inspection_id')->nullable()->constrained('cylinder_inspections')->restrictOnDelete());
        }
        if (! Schema::hasIndex('cylinder_photos', 'cylinder_photos_device_index')) {
            Schema::table('cylinder_photos', fn (Blueprint $table) => $table->index('cylinder_id', 'cylinder_photos_device_index'));
        }
        if (Schema::hasIndex('cylinder_photos', 'cylinder_photos_cylinder_id_unique')) {
            Schema::table('cylinder_photos', fn (Blueprint $table) => $table->dropUnique('cylinder_photos_cylinder_id_unique'));
        }
    }

    public function down(): void
    {
        if (DB::table('cylinder_photos')->whereNotNull('cylinder_inspection_id')->exists()) {
            throw new RuntimeException('Inspection photos must be preserved. Cannot roll back this migration.');
        }
        Schema::table('cylinder_photos', function (Blueprint $table) {
            $table->unique('cylinder_id');
            $table->dropIndex('cylinder_photos_device_index');
            $table->dropConstrainedForeignId('cylinder_inspection_id');
        });
        Schema::table('cylinder_inspections', fn (Blueprint $table) => $table->dropColumn(['weight_kg', 'working_pressure_bar']));
    }
};
