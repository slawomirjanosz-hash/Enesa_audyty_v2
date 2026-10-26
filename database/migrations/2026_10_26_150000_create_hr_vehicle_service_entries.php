<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_vehicle_service_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_vehicle_id')->constrained('hr_vehicles')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('performed_on');
            $table->unsignedInteger('mileage');
            $table->string('type', 40);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['hr_vehicle_id', 'performed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_vehicle_service_entries');
    }
};
