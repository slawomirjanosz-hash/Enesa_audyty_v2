<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cylinder_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cylinder_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->string('stored_path');
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinder_videos');
    }
};
