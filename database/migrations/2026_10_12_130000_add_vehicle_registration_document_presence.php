<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_vehicles', fn (Blueprint $table) => $table->boolean('registration_document_present')->nullable());
    }

    public function down(): void
    {
        Schema::table('hr_vehicles', fn (Blueprint $table) => $table->dropColumn('registration_document_present'));
    }
};
