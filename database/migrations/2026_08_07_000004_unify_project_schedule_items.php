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
            $table->unsignedInteger('project_position')->default(0)->after('progress');
        });

        if (Schema::hasTable('project_stages')) {
            $projects = DB::table('projects')->select(['id', 'company_id', 'created_by'])->get()->keyBy('id');
            foreach (DB::table('project_stages')->orderBy('position')->orderBy('id')->get() as $stage) {
                $project = $projects->get($stage->project_id);
                DB::table('tasks')->insert([
                    'title' => $stage->name,
                    'description' => 'Pozycja przeniesiona z etapów projektu.',
                    'start_date' => $stage->start_date,
                    'due_date' => $stage->end_date,
                    'status' => $stage->progress >= 100 ? 'done' : ($stage->progress > 0 ? 'in_progress' : 'todo'),
                    'priority' => 'medium',
                    'progress' => $stage->progress,
                    'project_position' => $stage->position,
                    'company_id' => $project?->company_id,
                    'created_by' => $project?->created_by,
                    'project_id' => $stage->project_id,
                    'created_at' => $stage->created_at,
                    'updated_at' => $stage->updated_at,
                ]);
            }
        }

        foreach (DB::table('tasks')->whereNotNull('project_id')->select('project_id')->distinct()->pluck('project_id') as $projectId) {
            $ids = DB::table('tasks')->where('project_id', $projectId)->orderBy('start_date')->orderBy('id')->pluck('id');
            foreach ($ids as $position => $taskId) {
                DB::table('tasks')->where('id', $taskId)->update(['project_position' => $position]);
            }
        }

        Schema::dropIfExists('project_stages');
    }

    public function down(): void
    {
        Schema::create('project_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('color', 7)->default('#2563EB');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('project_position');
        });
    }
};
