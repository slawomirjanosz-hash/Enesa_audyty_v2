<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_requirements', function (Blueprint $table) {
            $table->enum('status', ['planned', 'requested', 'ordered', 'in_progress', 'purchased', 'completed', 'cancelled'])
                ->default('requested')->change();
        });
    }

    public function down(): void
    {
        DB::table('project_requirements')->where('status', 'completed')->update(['status' => 'purchased']);
        Schema::table('project_requirements', function (Blueprint $table) {
            $table->enum('status', ['planned', 'requested', 'ordered', 'in_progress', 'purchased', 'cancelled'])
                ->default('requested')->change();
        });
    }
};
