<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $filename = 'ENESA_ISO50001_4_4_presentation_2026-11-13.html';
            if (DB::table('iso_presentations')->where('section_id', '4-4')->where('original_filename', $filename)->exists()) {
                return;
            }

            $directory = resource_path('training/iso50001-4-4');
            $id = DB::table('iso_presentations')->insertGetId([
                'section_id' => '4-4',
                'title' => '4.4 System zarządzania energią i jego procesy',
                'description' => 'Procesy, obieg informacji, ciągłość działania i poprawa wyniku energetycznego. Przykłady oraz praca w ENESA na podstawie książki Bronisława Pytla „Energia pod kontrolą”, tom 1 i 2.',
                'original_filename' => $filename,
                'source_size' => filesize($directory.'/presentation.html'),
                'slide_count' => 16,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            for ($position = 1; $position <= 16; $position++) {
                $bytes = file_get_contents($directory.'/slide-'.$position.'.png');
                if ($bytes === false) {
                    throw new RuntimeException('Missing ISO 4.4 presentation slide: '.$position);
                }
                DB::table('iso_presentation_slides')->insert([
                    'iso_presentation_id' => $id,
                    'position' => $position,
                    'image_data' => base64_encode($bytes),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Preserve training material that administrators may have edited after deployment.
    }
};
