<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_requests', function (Blueprint $table) {
            $table->string('end_client_name')->nullable()->after('company_id');
            $table->string('end_client_company')->nullable()->after('end_client_name');
            $table->string('end_client_email')->nullable()->after('end_client_company');
            $table->string('end_client_phone')->nullable()->after('end_client_email');
            $table->string('public_token', 64)->nullable()->unique()->after('end_client_phone');
            $table->timestamp('public_filled_at')->nullable()->after('public_token');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('offer_requests', function (Blueprint $table) {
            $table->dropColumn([
                'end_client_name', 'end_client_company', 'end_client_email',
                'end_client_phone', 'public_token', 'public_filled_at',
            ]);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
