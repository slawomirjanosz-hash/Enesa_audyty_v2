<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_financial_entries')
            ->where('source', 'requirement')
            ->whereNotNull('project_requirement_id')
            ->whereIn('project_requirement_id', function ($query) {
                $query->select('id')
                    ->from('project_requirements')
                    ->where('status', '!=', 'purchased');
            })
            ->delete();
    }

    public function down(): void
    {
        // Automatycznych kosztów wycofanych zakupów nie należy odtwarzać.
    }
};
