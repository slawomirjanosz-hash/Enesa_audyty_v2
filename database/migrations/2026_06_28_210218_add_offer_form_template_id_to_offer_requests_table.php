<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('offer_requests', function (Blueprint $table) {
            $table->foreignId('offer_form_template_id')->nullable()->after('offer_form_version_id')->constrained('offer_form_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offer_requests', function (Blueprint $table) {
            $table->dropForeign(['offer_form_template_id']);
            $table->dropColumn('offer_form_template_id');
        });
    }
};
