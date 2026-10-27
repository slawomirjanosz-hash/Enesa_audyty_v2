<?php

use App\Models\HrVehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_vehicle_column_responsibilities', function (Blueprint $table) {
            $table->id();
            $table->string('column_key')->unique();
            $table->json('user_ids');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        foreach (array_keys(HrVehicle::COLUMNS) as $column) {
            DB::table('hr_vehicle_column_responsibilities')->insert(['column_key' => $column, 'user_ids' => '[]', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_vehicle_column_responsibilities');
    }
};
