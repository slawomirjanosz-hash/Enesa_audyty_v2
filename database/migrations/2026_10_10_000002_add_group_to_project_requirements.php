<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('project_requirements', 'group_name')) {
            Schema::table('project_requirements', fn (Blueprint $table) => $table->string('group_name', 120)->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('project_requirements', fn (Blueprint $table) => $table->dropColumn('group_name'));
    }
};
