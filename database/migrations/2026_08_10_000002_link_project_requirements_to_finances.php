<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_financial_entries', function (Blueprint $table) {
            $table->foreignId('project_requirement_id')
                ->nullable()
                ->unique()
                ->after('project_id')
                ->constrained('project_requirements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_financial_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_requirement_id');
        });
    }
};
