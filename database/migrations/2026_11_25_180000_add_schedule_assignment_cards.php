<?php

use App\Models\Task;
use App\Services\ScheduleTaskAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('source_stage_id')->nullable()->unique();
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('schedule_card_created')->default(false);
        });
        Task::whereNotNull('assigned_to')->where(fn ($query) => $query->whereNotNull('project_id')->orWhereNotNull('audit_id'))
            ->chunkById(100, function ($tasks) {
                foreach ($tasks as $task) {
                    app(ScheduleTaskAssignment::class)->sync($task);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('schedule_card_created'));
        Schema::table('board_tasks', function (Blueprint $table) {
            $table->dropUnique(['source_stage_id']);
            $table->dropColumn('source_stage_id');
        });
    }
};
