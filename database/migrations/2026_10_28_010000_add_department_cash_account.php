<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('financial_plans', 'opening_balance')) {
            Schema::table('financial_plans', fn (Blueprint $table) => $table->text('opening_balance')->nullable());
        }
        if (! Schema::hasColumn('project_financial_entries', 'settled_on')) {
            Schema::table('project_financial_entries', function (Blueprint $table) {
                $table->date('settled_on')->nullable();
                $table->decimal('settled_amount', 15, 2)->nullable();
            });
        }
        if (! Schema::hasTable('financial_department_payments')) {
            Schema::create('financial_department_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('financial_department_id')->constrained()->cascadeOnDelete();
                $table->string('direction', 3);
                $table->date('paid_on');
                $table->text('amount');
                $table->string('description', 255);
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->index(['financial_department_id', 'paid_on'], 'department_payments_date_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_department_payments');
        Schema::table('financial_plans', fn (Blueprint $table) => $table->dropColumn('opening_balance'));
        Schema::table('project_financial_entries', fn (Blueprint $table) => $table->dropColumn(['settled_on', 'settled_amount']));
    }
};
