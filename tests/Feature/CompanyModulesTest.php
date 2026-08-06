<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['superadmin', 'admin', 'client_admin'] as $role) {
        Role::findOrCreate($role);
    }
});

test('superadmin can configure modules visible to a company', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');
    $company = Company::create(['name' => 'Firma instalatorska']);

    $this->actingAs($superadmin)
        ->patch(route('companies.modules.update', $company), [
            'enabled_modules' => ['offers', 'documents'],
        ])
        ->assertSessionHas('success');

    expect($company->refresh()->enabled_modules)->toBe(['offers', 'documents'])
        ->and($company->moduleEnabled('audits'))->toBeFalse()
        ->and($company->moduleEnabled('offers'))->toBeTrue();
});

test('non superadmin cannot configure company modules', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = Company::create(['name' => 'Firma testowa']);

    $this->actingAs($admin)
        ->patch(route('companies.modules.update', $company), ['enabled_modules' => []])
        ->assertForbidden();
});

test('disabled module is hidden and blocked in client zone', function () {
    $client = User::factory()->create();
    $client->assignRole('client_admin');
    $company = Company::create([
        'name' => 'Firma instalatorska',
        'enabled_modules' => ['offers', 'documents'],
    ]);
    $company->users()->attach($client, ['is_admin' => true]);

    $this->actingAs($client)
        ->get(route('client.dashboard'))
        ->assertOk()
        ->assertDontSee('Moje audyty')
        ->assertSee('Oferty');

    $this->actingAs($client)
        ->get(route('client.audits'))
        ->assertForbidden();
});
