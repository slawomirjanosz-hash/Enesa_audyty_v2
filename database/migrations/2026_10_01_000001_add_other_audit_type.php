<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('audit_types')->insertOrIgnore([
            'slug' => 'audyt-inny', 'name' => 'Audyt Inny',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep the type: existing audits may already reference it.
    }
};
