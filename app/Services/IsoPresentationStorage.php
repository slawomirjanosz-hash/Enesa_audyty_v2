<?php

namespace App\Services;

use App\Models\IsoPresentation;
use Illuminate\Support\Facades\DB;

class IsoPresentationStorage
{
    /** @param list<string> $slides */
    public function save(array $data, array $slides, ?IsoPresentation $presentation = null): IsoPresentation
    {
        return DB::transaction(function () use ($data, $slides, $presentation) {
            if ($presentation) {
                $presentation = IsoPresentation::lockForUpdate()->findOrFail($presentation->id);
                $presentation->update($data + ['slide_count' => count($slides)]);
                DB::table('iso_presentation_slides')->where('iso_presentation_id', $presentation->id)->delete();
            } else {
                $presentation = IsoPresentation::create($data + ['slide_count' => count($slides)]);
            }
            foreach ($slides as $index => $bytes) {
                DB::table('iso_presentation_slides')->insert(['iso_presentation_id' => $presentation->id, 'position' => $index + 1, 'image_data' => base64_encode($bytes)]);
            }

            return $presentation;
        });
    }
}
