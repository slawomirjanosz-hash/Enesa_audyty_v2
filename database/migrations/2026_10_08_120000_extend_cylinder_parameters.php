<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existingColumns = Schema::getColumnListing('cylinders');
        Schema::table('cylinders', function (Blueprint $table) use ($existingColumns) {
            $table->string('device_type', 30)->default('butla');
            $table->string('name', 160)->nullable();
            // Legacy manufacturer names are not necessarily manufacturer marks.
            $table->string('manufacturer_mark', 160)->nullable();
            $table->string('inventory_number', 160)->nullable();
            $table->string('working_medium', 160)->nullable();
            $table->decimal('temperature_min_c', 7, 2)->nullable();
            $table->decimal('temperature_max_c', 7, 2)->nullable();
            $table->decimal('test_pressure_bar', 10, 3)->nullable();
            $table->decimal('tare_or_gross_mass_kg', 10, 3)->nullable();
            $table->decimal('net_mass_kg', 10, 3)->nullable();
            $table->decimal('stamped_empty_mass_kg', 10, 3)->nullable();
            $table->string('filling_mass_symbol', 160)->nullable();
            $table->string('equipment_type', 160)->nullable();
            $table->string('equipment_mark', 160)->nullable();
            // MySQL DDL is not transactional: resume after a partially applied migration.
            foreach ($table->getColumns() as $column) {
                if (in_array($column->name, $existingColumns, true)) {
                    $table->removeColumn($column->name);
                }
            }
        });
        // The old composite unique index can also support the company foreign key.
        if (! Schema::hasIndex('cylinders', 'cylinders_company_lookup_index')) {
            Schema::table('cylinders', fn (Blueprint $table) => $table->index('company_id', 'cylinders_company_lookup_index'));
        }
        if (! Schema::hasIndex('cylinders', 'cylinders_mark_serial_unique')) {
            Schema::table('cylinders', fn (Blueprint $table) => $table->unique(['manufacturer_mark', 'serial_number'], 'cylinders_mark_serial_unique'));
        }
        if (Schema::hasIndex('cylinders', 'cylinders_company_id_serial_number_unique')) {
            Schema::table('cylinders', fn (Blueprint $table) => $table->dropUnique(['company_id', 'serial_number']));
        }
        DB::table('cylinders')->whereNull('name')->update(['name' => DB::raw('type')]);
    }

    public function down(): void
    {
        // Do not partially roll back when new valid records violate the old identity rule.
        if (DB::table('cylinders')->select('company_id', 'serial_number')->groupBy('company_id', 'serial_number')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore legacy cylinder uniqueness: multiple manufacturer marks share a serial number for one client.');
        }
        Schema::table('cylinders', function (Blueprint $table) {
            $table->unique(['company_id', 'serial_number']);
            $table->dropIndex('cylinders_company_lookup_index');
            $table->dropUnique('cylinders_mark_serial_unique');
            $table->dropColumn(['device_type', 'name', 'manufacturer_mark', 'inventory_number', 'working_medium', 'temperature_min_c', 'temperature_max_c', 'test_pressure_bar', 'tare_or_gross_mass_kg', 'net_mass_kg', 'stamped_empty_mass_kg', 'filling_mass_symbol', 'equipment_type', 'equipment_mark']);
        });
    }
};
