<?php

use App\Models\CompanySettings;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

test('disabling client zone updates the active settings record and removes navigation even for superadmin', function () {
    Role::findOrCreate('superadmin');
    $user = User::factory()->create();
    $user->assignRole('superadmin');
    $settings = new CompanySettings([
        'name' => 'Firma', 'primary_color' => '#123456', 'welcome_page_mode' => 'general',
        'enabled_modules' => ['dashboard', 'client_zone', 'cylinders'],
    ]);
    $settings->id = 7;
    $settings->save();
    $this->actingAs($user)->get(route('cylinders.index'))
        ->assertOk()->assertSee('href="'.url('/client-zone').'"', false);
    $this->post(route('settings.company.update'), [
        'name' => 'Firma', 'primary_color' => '#123456', 'welcome_page_mode' => 'general',
        'enabled_modules' => ['dashboard', 'cylinders'],
    ])->assertSessionHasNoErrors()->assertRedirect(route('settings.company'));
    expect(CompanySettings::count())->toBe(1)
        ->and($settings->fresh()->moduleEnabled('client_zone'))->toBeFalse();
    $this->get(route('settings.company'))->assertOk()->assertDontSee('href="'.url('/client-zone').'"', false);
    $this->get(route('cylinders.index'))->assertOk()->assertDontSee('href="'.url('/client-zone').'"', false);
    $this->get(route('client-zone.index'))->assertForbidden();
    $this->post(route('settings.company.update'), [
        'name' => 'Firma', 'primary_color' => '#123456', 'welcome_page_mode' => 'general',
        'enabled_modules' => ['dashboard', 'client_zone', 'cylinders'],
    ])->assertSessionHasNoErrors();
    $this->get(route('cylinders.index'))->assertOk()->assertSee('href="'.url('/client-zone').'"', false);
});

test('uploaded application logo is persisted in database and served publicly', function () {
    Role::findOrCreate('superadmin');
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    $logo = UploadedFile::fake()->image('brand.png', 120, 60);

    $this->actingAs($superadmin)
        ->post(route('settings.company.update'), [
            'name' => 'Przykładowa firma',
            'primary_color' => '#123456',
            'welcome_page_mode' => 'general',
            'enabled_modules' => ['dashboard'],
            'logo' => $logo,
        ])
        ->assertRedirect(route('settings.company'));

    $settings = CompanySettings::firstOrFail();

    expect($settings->logo_data)->not->toBeNull()
        ->and($settings->logo_mime)->toBe('image/png')
        ->and($settings->logoUrl())->toContain('/branding/logo');

    $this->get(route('branding.logo'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('saving settings without a new logo keeps the persisted logo', function () {
    Role::findOrCreate('superadmin');
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');
    $settings = CompanySettings::create([
        'name' => 'Firma',
        'primary_color' => '#123456',
        'welcome_page_mode' => 'general',
        'logo_data' => base64_encode('existing-logo'),
        'logo_mime' => 'image/png',
    ]);

    $this->actingAs($superadmin)
        ->post(route('settings.company.update'), [
            'name' => 'Firma po zmianie',
            'primary_color' => '#654321',
            'welcome_page_mode' => 'general',
            'enabled_modules' => ['dashboard'],
        ])
        ->assertRedirect(route('settings.company'));

    expect($settings->refresh()->logo_data)->toBe(base64_encode('existing-logo'));
});

test('offer documents can embed the logo saved in company settings', function () {
    $settings = CompanySettings::create([
        'name' => 'Firma z własnym logo',
        'logo_data' => base64_encode('custom-logo-binary'),
        'logo_mime' => 'image/webp',
    ]);

    expect($settings->logoDataUri())
        ->toBe('data:image/webp;base64,'.base64_encode('custom-logo-binary'));
});

test('company can configure a separate tagline for offer documents', function () {
    $settings = CompanySettings::create([
        'name' => 'Firma z własnym hasłem',
        'offer_tagline' => "Pierwsza linia\nDruga linia",
    ]);

    expect($settings->offerTagline())->toBe("Pierwsza linia\nDruga linia");
});
