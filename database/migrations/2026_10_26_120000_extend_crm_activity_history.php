<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->text('description')->change();
            $table->date('occurred_on')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Preserve recorded history and its attribution on rollback.
    }
};
