<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('schedule_dependency_anchor')->nullable();
            $table->integer('schedule_dependency_shift')->default(0);
            $table->date('schedule_completed_on')->nullable();
        });
        // Already completed stages must not acquire a new, artificial completion date.
        DB::table('tasks')->whereNotNull('project_id')->where(fn ($q) => $q->where('progress', '>=', 100)->orWhere('status', 'done'))
            ->update(['schedule_completed_on' => DB::raw('due_date')]);
        DB::table('tasks')->whereNotNull('project_id')->whereNotNull('depends_on_task_id')->orderBy('id')->chunkById(500, function ($tasks) {
            $parents = DB::table('tasks')->whereIn('id', $tasks->pluck('depends_on_task_id'))->get()->keyBy('id');
            foreach ($tasks as $task) {
                $parent = $parents->get($task->depends_on_task_id);
                if ($parent && $parent->project_id === $task->project_id) {
                    DB::table('tasks')->where('id', $task->id)->update(['schedule_dependency_anchor' => $parent->due_date]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn(['schedule_dependency_anchor', 'schedule_dependency_shift', 'schedule_completed_on']));
    }
};
