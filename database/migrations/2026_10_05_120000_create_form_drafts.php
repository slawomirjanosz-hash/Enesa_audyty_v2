<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('form_key', 64);
            $table->unsignedInteger('revision')->default(0);
            $table->longText('payload')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'form_key']);
        });
    }

    public function down(): void
    {
        // Preserve recoverable working copies during application rollback.
    }
};
