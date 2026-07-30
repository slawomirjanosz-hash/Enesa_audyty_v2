<?php

use App\Models\Company;
use App\Models\CrmOpportunity;
use App\Models\Offer;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate('admin');
});

test('admin can attach an unlinked offer to a lead of the same company', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Firma A']);
    $opportunity = CrmOpportunity::create([
        'title' => 'Audyt dla Firmy A',
        'company_id' => $company->id,
        'stage' => 'new_lead',
        'created_by' => $admin->id,
    ]);
    $offer = Offer::create([
        'offer_number' => 'OF_TEST_LINK_001',
        'offer_full_number' => 'OF_TEST_LINK_001',
        'company_id' => $company->id,
        'status' => 'w_toku',
        'created_by_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('crm.opportunities.attach-offer', $opportunity), ['offer_id' => $offer->id])
        ->assertRedirect(route('crm.index', ['tab' => 'pipeline']));

    expect($offer->refresh()->crm_opportunity_id)->toBe($opportunity->id)
        ->and($opportunity->refresh()->stage)->toBe('offer');

    $this->actingAs($admin)
        ->patch(route('offers.status', $offer), ['status' => 'wygrana'])
        ->assertSessionHas('success');

    expect($opportunity->refresh()->stage)->toBe('realization');
});

test('offer cannot be attached to a lead of another company', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $leadCompany = Company::create(['name' => 'Firma lead']);
    $otherCompany = Company::create(['name' => 'Inna firma']);
    $opportunity = CrmOpportunity::create([
        'title' => 'Lead Firmy',
        'company_id' => $leadCompany->id,
        'stage' => 'contact',
        'created_by' => $admin->id,
    ]);
    $offer = Offer::create([
        'offer_number' => 'OF_TEST_LINK_002',
        'offer_full_number' => 'OF_TEST_LINK_002',
        'company_id' => $otherCompany->id,
        'status' => 'w_toku',
        'created_by_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('crm.opportunities.attach-offer', $opportunity), ['offer_id' => $offer->id])
        ->assertSessionHas('error');

    expect($offer->refresh()->crm_opportunity_id)->toBeNull()
        ->and($opportunity->refresh()->stage)->toBe('contact');
});
