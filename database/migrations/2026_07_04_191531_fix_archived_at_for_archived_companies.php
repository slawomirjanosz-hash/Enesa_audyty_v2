<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix companies that were archived (status='archived') but archived_at was never set.
     * This happened because archiveCompany() only set status without setting archived_at.
     */
    public function up(): void
    {
        DB::table('companies')
            ->where('status', 'archived')
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);
    }

    public function down(): void
    {
        // Not reversible — original archived_at values are unknown.
    }
};
