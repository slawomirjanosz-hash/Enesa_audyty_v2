<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->nullable()->constrained('offers')->cascadeOnDelete();
            $table->foreignId('offer_request_id')->nullable()->constrained('offer_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('tresc');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_messages');
    }
};
