<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $directory = resource_path('training/iso50001-complete');
        $catalogue = json_decode(file_get_contents($directory.'/catalogue.json'), true, 512, JSON_THROW_ON_ERROR);

        // Check the entire shipped package before adding any presentation.
        foreach ($catalogue as $deck) {
            $source = $directory.'/sections/'.$deck['section_id'].'/presentation.html';
            if (! is_file($source) || hash_file('sha256', $source) !== $deck['source_sha256']) {
                throw new RuntimeException('Invalid ISO presentation source: '.$deck['section_id']);
            }
            for ($position = 1; $position <= $deck['slide_count']; $position++) {
                $path = $directory.'/sections/'.$deck['section_id'].'/slide-'.$position.'.png';
                $size = is_file($path) ? getimagesize($path) : false;
                if (! $size || [$size[0], $size[1], $size[2]] !== [1280, 720, IMAGETYPE_PNG]) {
                    throw new RuntimeException('Invalid ISO presentation slide: '.$deck['section_id'].'/'.$position);
                }
            }
        }

        DB::transaction(function () use ($directory, $catalogue) {
            foreach ($catalogue as $deck) {
                if (DB::table('iso_presentations')->where('section_id', $deck['section_id'])
                    ->where('original_filename', $deck['original_filename'])->exists()) {
                    continue;
                }
                $folder = $directory.'/sections/'.$deck['section_id'];
                $id = DB::table('iso_presentations')->insertGetId([
                    'section_id' => $deck['section_id'],
                    'title' => $deck['title'],
                    'description' => $deck['description'],
                    'original_filename' => $deck['original_filename'],
                    'source_size' => filesize($folder.'/presentation.html'),
                    'slide_count' => $deck['slide_count'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                for ($position = 1; $position <= $deck['slide_count']; $position++) {
                    $bytes = file_get_contents($folder.'/slide-'.$position.'.png');
                    if ($bytes === false) {
                        throw new RuntimeException('Cannot read ISO slide: '.$deck['section_id'].'/'.$position);
                    }
                    DB::table('iso_presentation_slides')->insert([
                        'iso_presentation_id' => $id,
                        'position' => $position,
                        'image_data' => base64_encode($bytes),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Keep training material and any administrator edits; no audit data is changed.
    }
};
