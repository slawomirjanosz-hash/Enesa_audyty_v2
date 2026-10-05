<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_pdf_reads', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 64)->unique();
            $table->json('result')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        // Retain paid extraction results and attempt limits on rollback.
    }
};
