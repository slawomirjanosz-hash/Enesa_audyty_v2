<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_form_templates', function (Blueprint $table) {
            $table->json('pricing_rules')->nullable()->after('fields');
        });
    }

    public function down(): void
    {
        Schema::table('offer_form_templates', function (Blueprint $table) {
            $table->dropColumn('pricing_rules');
        });
    }
};
