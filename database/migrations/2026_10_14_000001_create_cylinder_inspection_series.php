<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cylinder_inspection_series')) {
            Schema::create('cylinder_inspection_series', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('responsible_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->string('status', 20)->default('draft')->index();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('cylinder_inspection_series_items')) {
            Schema::create('cylinder_inspection_series_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('series_id')->constrained('cylinder_inspection_series')->restrictOnDelete();
                $table->foreignId('cylinder_id')->constrained()->restrictOnDelete();
                $table->foreignId('inspection_id')->nullable()->unique()->constrained('cylinder_inspections')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['series_id', 'cylinder_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinder_inspection_series_items');
        Schema::dropIfExists('cylinder_inspection_series');
    }
};
