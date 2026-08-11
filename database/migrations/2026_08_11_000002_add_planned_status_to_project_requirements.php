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
            $table->enum('status', ['planned', 'requested', 'ordered', 'in_progress', 'purchased', 'cancelled'])
                ->default('requested')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('project_requirements')->where('status', 'planned')->update(['status' => 'requested']);

        Schema::table('project_requirements', function (Blueprint $table) {
            $table->enum('status', ['requested', 'ordered', 'in_progress', 'purchased', 'cancelled'])
                ->default('requested')
                ->change();
        });
    }
};
