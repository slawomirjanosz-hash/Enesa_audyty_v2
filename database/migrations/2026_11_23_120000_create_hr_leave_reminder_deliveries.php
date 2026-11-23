<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->date('reminder_date');
            $table->unsignedBigInteger('approver_id');
            $table->string('status', 20);
            $table->unsignedInteger('request_count');
            $table->string('error_type')->nullable();
            $table->timestamps();
            $table->unique(['reminder_date', 'approver_id'], 'hr_leave_digest_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_reminder_deliveries');
    }
};
