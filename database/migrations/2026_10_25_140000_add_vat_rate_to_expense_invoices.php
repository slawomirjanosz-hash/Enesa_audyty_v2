<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_invoices', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expense_invoices', fn (Blueprint $table) => $table->dropColumn('vat_rate'));
    }
};
