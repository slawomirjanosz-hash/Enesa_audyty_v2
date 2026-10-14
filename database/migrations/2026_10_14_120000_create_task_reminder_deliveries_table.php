<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->date('reminder_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('mailer', 50);
            $table->unsignedInteger('task_count');
            $table->string('error_type')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['reminder_date', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reminder_deliveries');
    }
};
