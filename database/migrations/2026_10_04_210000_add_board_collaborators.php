<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['board_task' => 'board_tasks', 'task' => 'tasks'] as $key => $table) {
            Schema::create($key.'_participants', function (Blueprint $blueprint) use ($key, $table) {
                $blueprint->foreignId($key.'_id')->constrained($table)->cascadeOnDelete();
                $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
                $blueprint->primary([$key.'_id', 'user_id']);
                $blueprint->index('user_id');
            });
        }
        Schema::table('tasks', fn (Blueprint $table) => $table->unsignedInteger('board_revision')->default(1));
        Permission::findOrCreate('board.team.view', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve assignments when rolling application code back.
    }
};
