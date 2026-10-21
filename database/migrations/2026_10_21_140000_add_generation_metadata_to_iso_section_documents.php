<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iso_section_documents', fn (Blueprint $table) => $table->json('generation_metadata')->nullable());
    }

    public function down(): void
    {
        Schema::table('iso_section_documents', fn (Blueprint $table) => $table->dropColumn('generation_metadata'));
    }
};
