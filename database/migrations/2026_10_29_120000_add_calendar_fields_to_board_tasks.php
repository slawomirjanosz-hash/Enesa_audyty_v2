<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_tasks', function (Blueprint $table) {
            $table->string('calendar_kind', 20)->nullable();
            $table->time('scheduled_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('board_tasks', fn (Blueprint $table) => $table->dropColumn(['calendar_kind', 'scheduled_time']));
    }
};
