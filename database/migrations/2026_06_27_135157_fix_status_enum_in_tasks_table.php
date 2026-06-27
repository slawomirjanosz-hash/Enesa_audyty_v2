<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("UPDATE tasks SET status = 'todo' WHERE status = 'pending'");
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('todo','in_progress','done') NOT NULL DEFAULT 'todo'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE tasks SET status = 'pending' WHERE status = 'todo'");
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending'");
    }
};
