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
            if (! Schema::hasColumn('offers', 'offer_title')) {
                $table->string('offer_title')->nullable()->after('offer_full_number');
            }
            if (! Schema::hasColumn('offers', 'valid_until')) {
                $table->date('valid_until')->nullable()->after('offer_title');
            }
            if (! Schema::hasColumn('offers', 'content_subject')) {
                $table->longText('content_subject')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('offers', 'content_scope')) {
                $table->longText('content_scope')->nullable()->after('content_subject');
            }
            if (! Schema::hasColumn('offers', 'content_deadline')) {
                $table->longText('content_deadline')->nullable()->after('content_scope');
            }
            if (! Schema::hasColumn('offers', 'content_payment')) {
                $table->longText('content_payment')->nullable()->after('content_deadline');
            }
            if (! Schema::hasColumn('offers', 'price_sections')) {
                $table->json('price_sections')->nullable()->after('content_payment');
            }
            if (! Schema::hasColumn('offers', 'show_unit_prices')) {
                $table->boolean('show_unit_prices')->default(false)->after('price_sections');
            }
        });

        Schema::table('offer_delegations', function (Blueprint $table) {
            if (! Schema::hasColumn('offer_delegations', 'stawka_km')) {
                $table->decimal('stawka_km', 8, 2)->default(1.10)->after('km_do_klienta');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('offers', 'offer_title')       ? 'offer_title'       : null,
                Schema::hasColumn('offers', 'valid_until')       ? 'valid_until'       : null,
                Schema::hasColumn('offers', 'content_subject')   ? 'content_subject'   : null,
                Schema::hasColumn('offers', 'content_scope')     ? 'content_scope'     : null,
                Schema::hasColumn('offers', 'content_deadline')  ? 'content_deadline'  : null,
                Schema::hasColumn('offers', 'content_payment')   ? 'content_payment'   : null,
                Schema::hasColumn('offers', 'price_sections')    ? 'price_sections'    : null,
                Schema::hasColumn('offers', 'show_unit_prices')  ? 'show_unit_prices'  : null,
            ]));
        });

        Schema::table('offer_delegations', function (Blueprint $table) {
            if (Schema::hasColumn('offer_delegations', 'stawka_km')) {
                $table->dropColumn('stawka_km');
            }
        });
    }
};
