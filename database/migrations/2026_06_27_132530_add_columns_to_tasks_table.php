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
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->after('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('assigned_to');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete()->after('created_by');
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete()->after('company_id');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['company_id']);
            $table->dropForeign(['offer_id']);
            $table->dropColumn(['description', 'assigned_to', 'created_by', 'company_id', 'offer_id', 'priority']);
        });
    }
};
