<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('status');
        });

        DB::statement("ALTER TABLE companies MODIFY status ENUM('pending', 'active', 'inactive', 'archived') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE companies MODIFY status ENUM('pending', 'active', 'inactive') NOT NULL DEFAULT 'pending'");

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
