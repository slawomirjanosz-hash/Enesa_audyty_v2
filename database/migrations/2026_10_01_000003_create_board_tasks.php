<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('board_tasks')) {
            return;
        }
        Schema::create('board_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('audit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('stage_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo');
            $table->date('due_date')->nullable();
            $table->integer('stage_offset_days')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assigned_to', 'status', 'due_date']);
            $table->index(['project_id', 'status']);
            $table->index(['audit_id', 'status']);
        });
    }

    public function down(): void
    {
        // Preserve cards and their history on rollback.
    }
};
