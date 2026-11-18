<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('security_block_reason', 'inactivity')
            ->update(['security_block_reason' => null, 'security_blocked_at' => null]);
    }

    public function down(): void
    {
        // Automatic inactivity blocks must not be recreated on rollback.
    }
};
