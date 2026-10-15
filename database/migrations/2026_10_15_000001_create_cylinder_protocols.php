<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cylinder_protocols')) {
            Schema::create('cylinder_protocols', function (Blueprint $table) {
                $table->id();
                $table->foreignId('series_id')->unique()->constrained('cylinder_inspection_series')->restrictOnDelete();
                $table->string('number')->unique();
                $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('issued_at');
                $table->string('template_version', 30);
                $table->longText('snapshot');
                $table->string('stored_path');
                $table->string('sha256', 64);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinder_protocols');
    }
};
