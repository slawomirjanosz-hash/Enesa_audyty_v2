<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunity_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crm_opportunity_id')->constrained('crm_opportunities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['crm_opportunity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunity_user');
    }
};
