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
        Schema::table('offers', function (Blueprint $table) {
            $table->string('offer_title')->nullable()->after('offer_full_number');
            $table->text('content_subject')->nullable()->after('offer_title');
            $table->text('content_scope')->nullable()->after('content_subject');
            $table->text('content_deadline')->nullable()->after('content_scope');
            $table->text('content_payment')->nullable()->after('content_deadline');
            $table->boolean('show_unit_prices')->default(false)->after('content_payment');
            $table->json('price_sections')->nullable()->after('show_unit_prices');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn([
                'offer_title',
                'content_subject',
                'content_scope',
                'content_deadline',
                'content_payment',
                'show_unit_prices',
                'price_sections',
            ]);
        });
    }
};
