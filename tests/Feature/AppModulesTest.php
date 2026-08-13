<?php

use App\Models\CompanySettings;
use App\Models\Offer;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['superadmin', 'admin'] as $role) {
        Role::findOrCreate($role);
    }
});

test('superadmin configures modules for the whole application in company settings', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    $this->actingAs($superadmin)
        ->post(route('settings.company.update'), [
            'name' => 'Firma instalatorska',
            'primary_color' => '#1A4D3A',
            'welcome_page_mode' => 'general',
            'enabled_modules' => ['dashboard', 'crm', 'offers'],
        ])
        ->assertRedirect(route('settings.company'));

    expect(CompanySettings::first()->enabled_modules)->toBe(['dashboard', 'crm', 'offers'])
        ->and(CompanySettings::first()->short_name)->toBe('FI');
});

test('disabled application modules disappear from navigation and reject direct access', function () {
    CompanySettings::create([
        'name' => 'Firma instalatorska',
        'primary_color' => '#1A4D3A',
        'welcome_page_mode' => 'general',
        'enabled_modules' => ['dashboard', 'crm', 'offers'],
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Widok kart klientów')
        ->assertDontSee('Widok audytora')
        ->assertSee('CRM')
        ->assertSee('Strefa Ofert')
        ->assertDontSee('System Audytów')
        ->assertDontSee('Strefa klienta');

    $this->actingAs($admin)->get(route('audit-types.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('client-zone.index'))->assertForbidden();
});

test('disabled audits module disappears from CRM and audits tab cannot be forced by URL', function () {
    CompanySettings::create([
        'name' => 'Firma instalatorska',
        'enabled_modules' => ['dashboard', 'crm', 'offers'],
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    \App\Models\Company::create([
        'name' => 'Klient CRM',
        'company_type' => 'client',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('crm.index', ['tab' => 'companies']))
        ->assertOk()
        ->assertDontSee(route('crm.index', ['tab' => 'audits']), false)
        ->assertDontSee('audits-tbody', false);

    $this->actingAs($admin)
        ->get(route('crm.index', ['tab' => 'audits']))
        ->assertOk()
        ->assertSee('Aktywne firmy')
        ->assertSee('Klient CRM')
        ->assertDontSee('audits-tbody', false);
});

test('offer numbers use the editable company short name', function () {
    CompanySettings::create([
        'name' => 'Prinż Cieszyn',
        'short_name' => 'PRINZ',
    ]);

    $firstNumber = Offer::generateNumber();
    Offer::create([
        'offer_number' => $firstNumber,
        'offer_full_number' => $firstNumber,
        'status' => 'w_toku',
    ]);

    expect($firstNumber)->toBe('OF_PRINZ_'.now()->format('Ymd').'_001')
        ->and(Offer::generateNumber())->toBe('OF_PRINZ_'.now()->format('Ymd').'_002');
});

test('staff login redirects to the first enabled module', function () {
    CompanySettings::create([
        'name' => 'Firma bez dashboardu',
        'primary_color' => '#1A4D3A',
        'welcome_page_mode' => 'general',
        'enabled_modules' => ['crm', 'offers'],
    ]);
    $admin = User::factory()->create(['password' => 'password']);
    $admin->assignRole('admin');

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('crm.index'));
});

test('dashboard shows company creation date in table and last change on card', function () {
    CompanySettings::create([
        'name' => 'Firma testowa',
        'enabled_modules' => ['dashboard', 'crm'],
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = \App\Models\Company::create([
        'name' => 'Klient datowany',
        'company_type' => 'client',
        'status' => 'active',
        'show_in_dashboard' => true,
    ]);
    $company->forceFill([
        'created_at' => '2026-07-01 08:15:00',
        'updated_at' => '2026-08-12 16:40:00',
    ])->saveQuietly();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Data dodania')
        ->assertSee('01.07.2026 08:15')
        ->assertSee('Ostatnia zmiana: 12.08.2026 16:40')
        ->assertSee('data-sort-value="2026-07-01 08:15:00"', false);
});
