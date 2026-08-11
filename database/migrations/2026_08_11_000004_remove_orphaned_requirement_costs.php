<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_financial_entries')
            ->where('source', 'requirement')
            ->where(function ($query) {
                $query->whereNull('project_requirement_id')
                    ->orWhereNotExists(function ($requirements) {
                        $requirements->selectRaw('1')
                            ->from('project_requirements')
                            ->whereColumn('project_requirements.id', 'project_financial_entries.project_requirement_id')
                            ->where('project_requirements.status', 'purchased');
                    });
            })
            ->delete();
    }

    public function down(): void
    {
        // Nie odtwarzamy nieprawidłowych automatycznych kosztów.
    }
};
