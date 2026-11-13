<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_invoices', function (Blueprint $table) {
            $table->string('workflow_status', 24)->nullable()->index();
            $table->json('workflow_history')->nullable();
            $table->string('settlement_method', 24)->nullable();
            $table->decimal('settlement_amount', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expense_invoices', function (Blueprint $table) {
            $table->dropIndex(['workflow_status']);
            $table->dropColumn(['workflow_status', 'workflow_history', 'settlement_method', 'settlement_amount']);
        });
    }
};
