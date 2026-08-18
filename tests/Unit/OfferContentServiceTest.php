<?php

use App\Models\Offer;
use App\Services\OfferContentService;

test('it sanitizes offer html and preserves supported formatting', function () {
    $service = new OfferContentService;

    $html = '<p class="ql-align-center" onclick="alert(1)"><strong>Treść</strong><script>alert(1)</script></p>';

    expect($service->cleanHtml($html))
        ->toBe('<p><strong>Treść</strong>alert(1)</p>');
});

test('it normalizes text section names content and placement', function () {
    $service = new OfferContentService;

    $sections = $service->normalizeTextSections([
        ['name' => '<b>Zakres</b>', 'content' => '<p class="x">Opis</p>', 'placement' => 'invalid'],
        'invalid section',
    ]);

    expect($sections)->toBe([
        ['name' => 'Zakres', 'content' => '<p>Opis</p>', 'placement' => 'before_price'],
    ]);
});

test('it preserves safe table column widths and removes other table styles', function () {
    $service = new OfferContentService;

    $html = '<table><tr><th style="width: 180px; color:red">Nazwa</th><td style="width:99999px;background:url(x)" onclick="alert(1)">Opis</td></tr></table>';

    expect($service->cleanHtml($html))
        ->toBe('<table><tr><th style="width: 180px">Nazwa</th><td style="width: 2000px">Opis</td></tr></table>');
});

test('it removes price data from an in-memory offer', function () {
    $offer = new Offer([
        'kwota_netto' => 100,
        'price_sections' => [['name' => 'Test']],
        'show_unit_prices' => true,
        'delegations' => [['km' => 10]],
        'content_payment' => 'Gotówka',
    ]);

    (new OfferContentService)->hidePrices($offer);

    expect($offer->kwota_netto)->toBeNull()
        ->and($offer->price_sections)->toBeNull()
        ->and($offer->show_unit_prices)->toBeFalse()
        ->and($offer->delegations)->toBeNull()
        ->and($offer->content_payment)->toBeNull();
});
