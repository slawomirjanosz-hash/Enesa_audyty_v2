<?php

use App\Models\Company;
use App\Models\Offer;
use App\Models\User;
use Smalot\PdfParser\Parser;

test('offer total visibility defaults on and persists independently of variant prices', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Klient wariantów', 'company_type' => 'client', 'status' => 'active']);
    $data = [
        'company_id' => $company->id, 'offer_number' => 'OF_VARIANTS_001', 'status' => 'w_toku',
        'show_unit_prices' => '1', 'delegations' => '[]',
        'liczba_wyjazdow' => 1, 'liczba_noc' => 0, 'liczba_osob' => 1, 'stawka_noc' => 0,
        'price_sections' => json_encode([
            ['id' => 'main', 'name' => 'Wariant 1', 'rows' => [['opis' => 'Pomiary', 'ilosc' => 1, 'jedn' => 'szt.', 'cena_jedn' => 100, 'z_narzutem' => 100]]],
            ['id' => 'second', 'name' => 'Wariant 2', 'rows' => [['opis' => 'Analiza', 'ilosc' => 1, 'jedn' => 'szt.', 'cena_jedn' => 200, 'z_narzutem' => 200]]],
        ]),
    ];
    $this->actingAs($admin)->get(route('offers.create'))->assertOk()
        ->assertSee('name="show_total" value="1" checked', false);
    $this->post(route('offers.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $offer = Offer::where('offer_number', $data['offer_number'])->firstOrFail();
    expect($offer->show_total)->toBeTrue();

    foreach ([false, true] as $visible) {
        $this->put(route('offers.update', $offer), $data + ['show_total' => $visible ? '1' : '0'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $offer->refresh();
        expect($offer->show_total)->toBe($visible)->and($offer->show_unit_prices)->toBeTrue();
        $this->get(route('offers.edit', $offer))->assertOk()
            ->assertSee('name="show_total" value="1"'.($visible ? ' checked' : ''), false);
        $show = $this->get(route('offers.show', $offer))->assertOk()->getContent();
        expect(str_contains($show, 'class="section-total"'))->toBe($visible);

        foreach ([false, true] as $sectionsOnly) {
            $response = $this->get(route('offers.pdf', ['offer' => $offer, 'sections_only' => (int) $sectionsOnly]))->assertOk();
            $text = (new Parser)->parseContent($response->getContent())->getText();
            if (getenv('OFFER_TOTAL_PREVIEW_DIR')) {
                file_put_contents(getenv('OFFER_TOTAL_PREVIEW_DIR').'/offer-'.(int) $visible.'-'.(int) $sectionsOnly.'.pdf', $response->getContent());
            }
            expect($text)->toContain('Wariant 1', 'Wariant 2');
            // With one item per section, its price occurs once as a line item
            // and once more as a subtotal only when totals are enabled.
            $priceOccurrences = $sectionsOnly ? (int) $visible : 1 + (int) $visible;
            expect(substr_count($text, '100,00'))->toBe($priceOccurrences);
            expect(substr_count($text, '200,00'))->toBe($priceOccurrences);
            expect(str_contains($text, 'Łącznie netto'))->toBe($visible);
            expect(str_contains($text, '300,00'))->toBe($visible);
        }
        $response = $this->get(route('offers.download-word', $offer))->assertOk();
        $zip = new ZipArchive;
        expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        expect($xml)->toContain('Wariant 1', 'Wariant 2', '100,00', '200,00');
        expect(str_contains($xml, 'Łącznie netto'))->toBe($visible);
        expect(str_contains($xml, '300,00'))->toBe($visible);
    }
});
