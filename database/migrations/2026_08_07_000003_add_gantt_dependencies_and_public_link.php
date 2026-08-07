<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('public_gantt_token', 64)->nullable()->unique()->after('description');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('depends_on_task_id')->nullable()->after('project_id')->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depends_on_task_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['public_gantt_token']);
            $table->dropColumn('public_gantt_token');
        });
    }
};
