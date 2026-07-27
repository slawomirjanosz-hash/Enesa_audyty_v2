<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditor_company_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auditor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view_dashboard')->default(false);
            $table->boolean('can_view_audits')->default(false);
            $table->boolean('can_view_offer_requests')->default(false);
            $table->boolean('can_view_offers')->default(false);
            $table->boolean('can_view_offer_prices')->default(false);
            $table->boolean('can_view_documents')->default(false);
            $table->boolean('can_view_chat')->default(false);
            $table->timestamps();

            $table->unique(['auditor_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditor_company_accesses');
    }
};