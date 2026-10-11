<?php

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Offer;
use App\Models\User;

test('offer exports use a half strength company color and natural Polish headings', function () {
    $settings = CompanySettings::create(['name' => 'Firma testowa', 'primary_color' => '#204060']);
    expect($settings->documentHeaderColor())->toBe('#90A0B0');
    expect((new CompanySettings(['primary_color' => 'invalid']))->documentHeaderColor())->toBe('#8DA69D');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Klient', 'company_type' => 'client', 'status' => 'active']);
    $offer = Offer::create([
        'company_id' => $company->id, 'offer_number' => 'OF_STYLE_001', 'status' => 'w_toku',
        'offer_title' => 'Przegląd urządzeń', 'valid_until' => '2026-12-31', 'show_unit_prices' => true,
        'price_sections' => [['name' => 'Pomiary', 'rows' => [['opis' => 'Usługa', 'ilosc' => 1, 'jedn' => 'szt.', 'cena_jedn' => 100, 'z_narzutem' => 100]]]],
    ]);
    $html = view('offers.pdf', ['offer' => $offer->load(['company', 'assignedUser', 'offerDelegation']), 'companySettings' => $settings, 'logoBase64' => null, 'sectionsOnly' => false])->render();
    expect($html)->toContain('background: #90A0B0;', 'border-spacing: 0;', 'Termin ważności oferty')
        ->not->toContain('text-transform: uppercase', 'border-collapse:separate', '#1A4D3A');
    $response = $this->actingAs($admin)->get(route('offers.download-word', $offer))->assertOk();
    $zip = new ZipArchive;
    expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    expect($xml)->toContain('w:fill="90A0B0"', 'Termin ważności oferty', 'w:tblCellSpacing w:w="0"')
        ->not->toContain('TERMIN WAżNOśCI', 'w:fill="1A4D3A"');
});
