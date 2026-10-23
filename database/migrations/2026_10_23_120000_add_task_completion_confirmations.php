<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks' => 'task_participants', 'board_tasks' => 'board_task_participants'] as $table => $pivot) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->json('completion_confirmations')->nullable());
            // Do not invent historical confirmations; existing archives remain untouched.
            $foreign = $table === 'tasks' ? 'task_id' : 'board_task_id';
            $query = DB::table($table)->whereNull('deleted_at')->where('status', 'done');
            if ($table === 'tasks') {
                $query->whereNull('project_id')->whereNull('audit_id');
            }
            $query->orderBy('id')->chunkById(200, function ($tasks) use ($table, $pivot, $foreign) {
                foreach ($tasks as $task) {
                    $people = DB::table($pivot)->where($foreign, $task->id)->pluck('user_id')->push($task->assigned_to)->filter()->unique();
                    if ($people->count() > 1) {
                        $revision = $table === 'tasks' ? 'board_revision' : 'revision';
                        DB::table($table)->where('id', $task->id)->update(['status' => 'in_progress', $revision => DB::raw($revision.' + 1')]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'board_tasks'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('completion_confirmations'));
        }
    }
};
