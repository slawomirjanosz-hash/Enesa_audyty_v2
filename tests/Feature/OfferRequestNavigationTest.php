<?php

use App\Models\Company;
use App\Models\OfferRequest;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('offer request preview and editor return directly to company requests tab', function () {
    Role::findOrCreate('superadmin');
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');
    $company = Company::create([
        'name' => 'Klient z zapytaniem',
        'company_type' => 'client',
        'status' => 'active',
    ]);
    $offerRequest = OfferRequest::create([
        'company_id' => $company->id,
        'created_by_id' => $superadmin->id,
        'title' => 'Zapytanie testowe',
        'status' => 'nowe',
        'form_responses' => [],
    ]);
    $requestsTabUrl = route('companies.show', $company).'#zapytania';

    $this->actingAs($superadmin)
        ->get(route('offer-requests.show', $offerRequest))
        ->assertOk()
        ->assertSee('Wróć do zapytań')
        ->assertSee('href="'.$requestsTabUrl.'"', false)
        ->assertDontSee('javascript:history.back()', false);

    $this->actingAs($superadmin)
        ->get(route('offer-requests.edit', $offerRequest))
        ->assertOk()
        ->assertSee('Wróć do zapytań')
        ->assertSee('href="'.$requestsTabUrl.'"', false);

    $this->actingAs($superadmin)
        ->put(route('offer-requests.update', $offerRequest), [
            'title' => 'Zapytanie po edycji',
        ])
        ->assertRedirect($requestsTabUrl);
});
