<?php

use App\Models\AuditorCompanyAccess;
use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferStatus;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('staff can add a reusable status and archive offers with their previous outcome', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin'));
    $this->actingAs($user);
    $company = Company::create(['name' => 'Klient ofert']);
    $active = Offer::create(['company_id' => $company->id, 'offer_number' => 'OF-ACTIVE', 'status' => 'w_toku']);
    $url = route('offers.index');

    $this->from($url)->patch(route('offers.status', $active), ['status' => 'w_toku', 'new_status' => 'Do akceptacji'])
        ->assertSessionHasNoErrors()->assertRedirect($url);
    expect($active->fresh()->status)->toBe('Do akceptacji');
    $this->from($url)->patch(route('offers.status', $active), ['new_status' => 'DO AKCEPTACJI'])
        ->assertSessionHasNoErrors();
    expect(OfferStatus::count())->toBe(1);
    $this->from($url)->patch(route('offers.status', $active), ['new_status' => 'Wygrana'])
        ->assertSessionHasErrors('new_status');
    $this->from($url)->patch(route('offers.status', $active), ['status' => 'nieistniejący'])
        ->assertSessionHasErrors('status');

    foreach (['wygrana' => ['Wygrana', 'green'], 'przegrana' => ['Przegrana', 'red'], 'anulowana' => ['Anulowana', 'gray']] as $status => [$label, $color]) {
        $offer = Offer::create(['company_id' => $company->id, 'offer_number' => 'OF-'.$status, 'status' => 'w_toku']);
        $this->from($url)->patch(route('offers.status', $offer), ['status' => $status])->assertSessionHasNoErrors();
        $this->from($url)->patch(route('offers.status', $offer), ['status' => 'zarchiwizowana'])->assertSessionHasNoErrors();
        expect($offer->fresh()->archived_from_status)->toBe($status);
        $response = $this->get($url)->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($document);
        $select = $xpath->query('//*[@id="zarchiwizowane"]//select[@aria-label="Status oferty OF-'.$status.'"]')->item(0);
        expect($select?->getAttribute('class'))->toContain('badge-'.$color);
        expect($xpath->evaluate('string(option[@selected])', $select))->toBe('Zarchiwizowana · '.$label);
    }

    $this->get($url)->assertOk()->assertSeeInOrder(['Wszystkie oferty', 'OF-ACTIVE', 'id="zarchiwizowane"', 'Zarchiwizowana ·'], false)
        ->assertSee('+ Dodaj status')->assertSee('value="Do akceptacji"', false)
        ->assertViewHas('offers', fn ($page) => $page->total() === 1)
        ->assertViewHas('archivedOffers', fn ($page) => $page->total() === 3);

    $offer = Offer::where('offer_number', 'OF-wygrana')->firstOrFail();
    $this->from($url)->patch(route('offers.status', $offer), ['status' => 'Do akceptacji'])->assertSessionHasNoErrors();
    expect($offer->fresh()->archived_from_status)->toBeNull();
});

test('archive sorting and pagination preserve filters and company and price access', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole(Role::findOrCreate('auditor'));
    $allowed = Company::create(['name' => 'Widoczny klient']);
    $other = Company::create(['name' => 'Ukryty klient']);
    AuditorCompanyAccess::create(['auditor_id' => $auditor->id, 'company_id' => $allowed->id, 'can_view_offers' => true]);
    foreach (range(1, 22) as $i) {
        Offer::create([
            'company_id' => $allowed->id, 'offer_number' => sprintf('OF-%02d', $i), 'offer_full_number' => sprintf('OF-%02d', $i),
            'offer_title' => 'Kotłownia', 'status' => 'zarchiwizowana', 'kwota_netto' => 98765,
        ]);
    }
    $hidden = Offer::create(['company_id' => $other->id, 'offer_number' => 'OF-HIDDEN', 'offer_title' => 'Kotłownia', 'status' => 'zarchiwizowana']);
    Offer::create(['company_id' => $allowed->id, 'offer_number' => 'OF-UNMATCHED', 'status' => 'zarchiwizowana']);

    $this->actingAs($auditor)->get(route('offers.index', ['q' => 'Kotłownia', 'status' => 'zarchiwizowana', 'archive_sort' => 'number', 'archive_direction' => 'desc', 'archive_page' => 2]))
        ->assertOk()->assertDontSee('OF-HIDDEN')->assertDontSee('OF-UNMATCHED')->assertDontSee('aria-label="Status oferty OF-', false)
        ->assertSee('data-sort-prefix="archive"', false)->assertSee('data-page-parameter="archive_page"', false)
        ->assertViewHas('archivedOffers', fn ($page) => $page->total() === 22 && $page->first()->offer_number === 'OF-02' && $page->first()->kwota_netto === null);
    $this->get(route('offers.index', ['q' => 'Kotłownia', 'archive_sort' => 'number', 'archive_direction' => 'asc']))
        ->assertOk()->assertViewHas('archivedOffers', fn ($page) => $page->first()->offer_number === 'OF-01');

    $auditor->givePermissionTo(Permission::findOrCreate('offers.edit'));
    $this->patch(route('offers.status', $hidden), ['new_status' => 'Niedostępny'])->assertForbidden();
    expect(OfferStatus::count())->toBe(0);
});
