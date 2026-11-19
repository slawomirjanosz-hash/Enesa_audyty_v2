<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_vehicles', function (Blueprint $table) {
            $table->unsignedInteger('location_revision')->default(0);
        });
        Schema::create('hr_vehicle_location_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_vehicle_id')->constrained('hr_vehicles')->cascadeOnDelete();
            $table->string('location');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['hr_vehicle_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_vehicle_location_periods');
        Schema::table('hr_vehicles', fn (Blueprint $table) => $table->dropColumn('location_revision'));
    }
};
