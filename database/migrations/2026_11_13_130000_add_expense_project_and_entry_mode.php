<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_invoices', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('simple_entry')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('expense_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('simple_entry');
        });
    }
};
