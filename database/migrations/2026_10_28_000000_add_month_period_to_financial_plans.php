<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('financial_plans', 'start_date')) {
            Schema::table('financial_plans', function (Blueprint $table) {
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
            });
        }
        DB::table('financial_plans')->whereNull('start_date')->orderBy('id')->each(function ($plan) {
            DB::table('financial_plans')->where('id', $plan->id)->update(['start_date' => $plan->year.'-01-01', 'end_date' => $plan->year.'-12-31']);
        });
        if (! Schema::hasIndex('financial_plans', 'finance_plan_period_unique')) {
            Schema::table('financial_plans', fn (Blueprint $table) => $table->unique(['financial_department_id', 'start_date', 'end_date'], 'finance_plan_period_unique'));
        }
        if (Schema::hasIndex('financial_plans', 'financial_plans_financial_department_id_year_unique')) {
            Schema::table('financial_plans', fn (Blueprint $table) => $table->dropUnique('financial_plans_financial_department_id_year_unique'));
        }
    }

    public function down(): void
    {
        // Retain periods: multiple plans may now exist in the same year.
    }
};
