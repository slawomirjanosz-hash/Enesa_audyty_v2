<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('iso_section_documents')->where('section_id', 'intro')->where('scope', 'client')
            ->whereIn('id', DB::table('iso_plant_profiles')->whereNotNull('document_id')->select('document_id'))
            ->update(['title' => '1 Wstęp ISO Profil zakładu', 'original_filename' => '1 Wstęp ISO Profil zakładu.pdf']);
    }

    public function down(): void {}
};
