<?php

use App\Models\Document;
use App\Models\Offer;

test('offer document filename contains its number and title', function () {
    $offer = new Offer([
        'offer_number' => 'OF/INTROL/2026/086',
        'offer_full_number' => 'OF/INTROL/2026/086',
        'offer_title' => 'Odzysk Ciepła ze spalin',
    ]);

    expect($offer->documentFilename('pdf'))
        ->toBe('oferta_OF_INTROL_2026_086_Odzysk_Ciepla_ze_spalin.pdf');

    $document = new Document([
        'type' => 'offer_pdf',
        'original_filename' => 'stara_nazwa.pdf',
    ]);
    $document->setRelation('offer', $offer);

    expect($document->displayFilename())
        ->toBe('oferta_OF_INTROL_2026_086_Odzysk_Ciepla_ze_spalin.pdf');
});
