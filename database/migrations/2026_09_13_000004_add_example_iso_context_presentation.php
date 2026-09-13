<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $id = DB::table('iso_presentations')->insertGetId([
                'section_id' => '4-1', 'title' => '4.1 Kontekst organizacji — część A',
                'description' => 'Zrozumienie wymagania ISO 50001, czynniki zewnętrzne i wewnętrzne oraz przykłady analizy kontekstu organizacji.',
                'original_filename' => '4.1_ENESA_ISO50001_4_1a_Kontekst_organizacji_POPRAWIONE.pptx',
                'source_size' => 2681631, 'slide_count' => 16, 'created_at' => now(), 'updated_at' => now(),
            ]);
            for ($position = 1; $position <= 16; $position++) {
                DB::table('iso_presentation_slides')->insert([
                    'iso_presentation_id' => $id, 'position' => $position,
                    'image_data' => base64_encode(file_get_contents(resource_path('training/iso50001-4-1/slide-'.$position.'.png'))),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Do not remove a presentation that administrators may have since edited.
    }
};
