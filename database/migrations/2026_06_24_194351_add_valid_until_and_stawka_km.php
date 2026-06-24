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
            $table->date('valid_until')->nullable()->after('kwota_netto');
        });

        Schema::table('offer_delegations', function (Blueprint $table) {
            $table->decimal('stawka_km', 5, 2)->default(1.10)->after('km_do_klienta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('valid_until');
        });

        Schema::table('offer_delegations', function (Blueprint $table) {
            $table->dropColumn('stawka_km');
        });
    }
};
