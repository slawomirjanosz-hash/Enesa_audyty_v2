<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('enabled_modules')->nullable()->after('welcome_page_mode');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('enabled_modules');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('enabled_modules')->nullable()->after('show_in_dashboard');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('enabled_modules');
        });
    }
};
