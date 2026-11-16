<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_protocols', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_company_id')->nullable()->change();
            $table->string('direction')->default('supplier');
            $table->json('client_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('project_protocols')->where('direction', 'client')->exists()) {
            throw new RuntimeException('Cannot remove client acceptance support while client protocols exist.');
        }

        Schema::table('project_protocols', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_company_id')->nullable(false)->change();
            $table->dropColumn(['direction', 'client_snapshot']);
        });
    }
};
