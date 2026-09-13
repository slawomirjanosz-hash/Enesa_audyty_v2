<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('security_activity_at')->nullable()->index();
            $table->string('security_block_reason')->nullable();
            $table->timestamp('security_blocked_at')->nullable();
            $table->timestamp('login_locked_until')->nullable();
            $table->unsignedInteger('failed_login_count')->default(0);
            $table->timestamp('failed_login_at')->nullable();
            $table->unsignedInteger('session_version')->default(0);
            $table->unsignedBigInteger('document_limit_bytes')->default(209715200);
        });
        // Start the inactivity period at deployment: old last_seen values were not reliable.
        DB::table('users')->update(['security_activity_at' => now()]);
        foreach (['documents', 'iso_section_documents'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreignId('storage_owner_id')->nullable()->constrained('users')->nullOnDelete());
            DB::table($name)->whereNotNull('uploaded_by')->update(['storage_owner_id' => DB::raw('uploaded_by')]);
        }
    }

    public function down(): void
    {
        foreach (['documents', 'iso_section_documents'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('storage_owner_id'));
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['security_activity_at', 'security_block_reason', 'security_blocked_at', 'login_locked_until', 'failed_login_count', 'failed_login_at', 'session_version', 'document_limit_bytes']));
    }
};
