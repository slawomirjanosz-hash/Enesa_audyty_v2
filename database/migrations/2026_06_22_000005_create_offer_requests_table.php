<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('offer_form_version_id')->nullable()->constrained('offer_form_versions')->nullOnDelete();
            $table->enum('status', ['nowe', 'w_toku', 'zamknięte'])->default('nowe');
            $table->json('form_responses')->nullable();
            $table->integer('completion_percent')->default(0);
            $table->text('tresc')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_requests');
    }
};
