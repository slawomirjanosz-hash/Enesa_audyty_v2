<?php

use App\Models\Company;
use App\Models\Offer;
use App\Models\User;
use Smalot\PdfParser\Parser;

test('offer validity follows the issue date by default and prints one saved deadline', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $company = Company::create(['name' => 'Klient ważności', 'company_type' => 'client']);
    $data = ['company_id' => $company->id, 'offer_number' => 'OF-VALIDITY', 'status' => 'w_toku', 'created_at' => '2026-11-12', 'liczba_wyjazdow' => 1, 'liczba_noc' => 0, 'liczba_osob' => 1, 'stawka_noc' => 0];
    $this->actingAs($user)->post(route('offers.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $offer = Offer::where('offer_number', 'OF-VALIDITY')->firstOrFail();
    expect($offer->valid_until->toDateString())->toBe('2026-12-12');

    $offer->update(['valid_until' => '2026-10-07']); // Existing invalid date from a previous version.
    $this->get(route('offers.edit', $offer))->assertOk()->assertSee('value="2026-12-12"', false);
    $pdf = $this->get(route('offers.pdf', $offer))->assertOk()->getContent();
    $default = (new Parser)->parseContent($pdf)->getText();
    expect($default)->toContain('Oferta ważna 30 dni od daty wystawienia (do 12.12.2026).')->not->toContain('07.10.2026', 'Termin ważności oferty');

    $this->put(route('offers.update', $offer), $data + ['valid_until' => '2026-12-31'])->assertSessionHasNoErrors()->assertRedirect();
    expect($offer->fresh()->valid_until->toDateString())->toBe('2026-12-31');
    $customPdf = $this->get(route('offers.pdf', $offer))->assertOk()->getContent();
    $custom = (new Parser)->parseContent($customPdf)->getText();
    expect($custom)->toContain('Oferta ważna do dnia 31.12.2026.')->not->toContain('30 dni', 'Termin ważności oferty');
    $this->put(route('offers.update', $offer), $data + ['valid_until' => '2026-10-07'])->assertSessionHasErrors('valid_until');
    expect($offer->fresh()->valid_until->toDateString())->toBe('2026-12-31');

    if ($dir = getenv('OFFER_VALIDITY_PREVIEW_DIR')) {
        file_put_contents($dir.'/default.pdf', $pdf);
        file_put_contents($dir.'/custom.pdf', $customPdf);
    }
});
