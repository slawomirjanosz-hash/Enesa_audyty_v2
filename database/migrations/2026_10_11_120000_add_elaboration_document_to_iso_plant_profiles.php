<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iso_plant_profiles', function (Blueprint $table) {
            $table->foreignId('elaboration_document_id')->nullable()->constrained('iso_section_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('iso_plant_profiles', fn (Blueprint $table) => $table->dropConstrainedForeignId('elaboration_document_id'));
    }
};
