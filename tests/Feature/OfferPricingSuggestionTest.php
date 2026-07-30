<?php

use App\Models\Company;
use App\Models\OfferFormTemplate;
use App\Models\OfferRequest;
use App\Models\PriceCatalogItem;
use App\Services\OfferPricingSuggestionService;

test('pricing suggestion adds only catalog items selected in the offer request', function () {
    $company = Company::create(['name' => 'Firma testowa']);
    $localVisit = PriceCatalogItem::create([
        'name' => 'Wizja lokalna',
        'unit' => 'usługa',
        'net_unit_price' => 800,
        'is_active' => true,
    ]);
    $analysis = PriceCatalogItem::create([
        'name' => 'Analiza modelu',
        'unit' => 'usługa',
        'net_unit_price' => 6000,
        'is_active' => true,
    ]);

    $template = OfferFormTemplate::create([
        'name' => 'Testowa ankieta',
        'fields' => [],
        'pricing_rules' => [
            ['question_key' => 'wizja', 'answer' => 'Tak', 'price_catalog_item_id' => $localVisit->id, 'quantity' => 2],
            ['question_key' => 'analiza', 'answer' => 'Tak', 'price_catalog_item_id' => $analysis->id, 'quantity' => 1],
        ],
        'is_active' => true,
    ]);
    $request = OfferRequest::create([
        'company_id' => $company->id,
        'offer_form_template_id' => $template->id,
        'form_responses' => ['wizja' => 'Tak', 'analiza' => 'Nie'],
    ]);

    $suggestion = app(OfferPricingSuggestionService::class)->forOfferRequest($request);

    expect($suggestion['matched_rules'])->toBe(1)
        ->and($suggestion['net_total'])->toBe(1600.0)
        ->and($suggestion['rows'][0]['opis'])->toBe('Wizja lokalna')
        ->and($suggestion['rows'][0]['ilosc'])->toBe(2.0);
});

test('pricing suggestion ignores inactive catalog items', function () {
    $company = Company::create(['name' => 'Firma testowa']);
    $inactiveItem = PriceCatalogItem::create([
        'name' => 'Nieaktywna usługa',
        'unit' => 'usługa',
        'net_unit_price' => 500,
        'is_active' => false,
    ]);
    $template = OfferFormTemplate::create([
        'name' => 'Testowa ankieta',
        'fields' => [],
        'pricing_rules' => [
            ['question_key' => 'opcje', 'answer' => 'Tak', 'price_catalog_item_id' => $inactiveItem->id, 'quantity' => 1],
        ],
        'is_active' => true,
    ]);
    $request = OfferRequest::create([
        'company_id' => $company->id,
        'offer_form_template_id' => $template->id,
        'form_responses' => ['opcje' => 'Tak'],
    ]);

    expect(app(OfferPricingSuggestionService::class)->forOfferRequest($request))
        ->toMatchArray(['rows' => [], 'net_total' => 0.0, 'matched_rules' => 0]);
});

test('pricing suggestion uses a number answer as the quantity and prepares travel from an address', function () {
    $company = Company::create(['name' => 'Firma testowa']);
    $perBuilding = PriceCatalogItem::create([
        'name' => 'Audyt obiektu',
        'unit' => 'obiekt',
        'net_unit_price' => 250,
        'is_active' => true,
    ]);
    $template = OfferFormTemplate::create([
        'name' => 'Ankieta z ilością i dojazdem',
        'fields' => [[
            'type' => 'section',
            'title' => 'Zakres',
            'fields' => [
                [
                    'key' => 'liczba_obiektow',
                    'label' => 'Liczba obiektów',
                    'type' => 'number',
                    'pricing' => ['type' => 'quantity', 'price_catalog_item_id' => $perBuilding->id, 'multiplier' => 1],
                ],
                [
                    'key' => 'adres_audytu',
                    'label' => 'Adres audytu',
                    'type' => 'address',
                    'pricing' => ['type' => 'travel', 'trips' => 2, 'people' => 1, 'rate_per_km' => 1.25],
                ],
            ],
        ]],
        'is_active' => true,
    ]);
    $request = OfferRequest::create([
        'company_id' => $company->id,
        'offer_form_template_id' => $template->id,
        'form_responses' => [
            'liczba_obiektow' => 3,
            'adres_audytu' => ['zip' => '00-001', 'city' => 'Warszawa', 'street' => 'Marszałkowska', 'no' => '1'],
        ],
    ]);

    $suggestion = app(OfferPricingSuggestionService::class)->forOfferRequest($request);

    expect($suggestion['rows'])->toHaveCount(1)
        ->and($suggestion['rows'][0]['ilosc'])->toBe(3.0)
        ->and($suggestion['net_total'])->toBe(750.0)
        ->and($suggestion['delegations'])->toHaveCount(1)
        ->and($suggestion['delegations'][0]['wyjazdy'])->toBe(2)
        ->and($suggestion['delegations'][0]['stawka_km'])->toBe(1.25)
        ->and($suggestion['delegations'][0]['adres'])->toContain('Warszawa');
});
