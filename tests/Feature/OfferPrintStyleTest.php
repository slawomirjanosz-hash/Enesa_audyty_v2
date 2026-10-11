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
    expect($html)->toContain('<th class="c" style="width:10%">Ilość</th>', '<th class="c" style="width:12%">Jedn.</th>', '<td class="c">1</td>', '<td class="c">szt.</td>', '<td class="r"><strong>100,00 zł</strong></td>');
    $response = $this->actingAs($admin)->get(route('offers.download-word', $offer))->assertOk();
    $zip = new ZipArchive;
    expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    expect($xml)->toContain('w:fill="90A0B0"', 'Termin ważności oferty', 'w:tblCellSpacing w:w="0"')
        ->not->toContain('TERMIN WAżNOśCI', 'w:fill="1A4D3A"');
    $document = new DOMDocument;
    $document->loadXML($xml);
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    foreach (['Ilość' => 'center', 'Jedn.' => 'center', 'szt.' => 'center', 'Cena jedn. (zł)' => 'right', 'Razem (zł)' => 'right', '100,00' => 'right'] as $text => $alignment) {
        $paragraphs = $xpath->query('//w:p[w:r/w:t="'.$text.'"]');
        expect($paragraphs->length)->toBeGreaterThan(0);
        foreach ($paragraphs as $paragraph) {
            expect($xpath->evaluate('string(w:pPr/w:jc/@w:val)', $paragraph))->toBe($alignment);
        }
    }
});
