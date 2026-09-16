<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cylinder_videos', function (Blueprint $table) {
            $table->foreignId('cylinder_inspection_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('cylinder_inspections', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
        });
        Schema::create('cylinder_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cylinder_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('storage_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stored_path');
            $table->string('thumbnail_path');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinder_photos');
        Schema::table('cylinder_inspections', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('cylinder_videos', fn (Blueprint $table) => $table->dropConstrainedForeignId('cylinder_inspection_id'));
    }
};
