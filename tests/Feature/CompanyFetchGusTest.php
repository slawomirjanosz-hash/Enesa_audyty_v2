<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

test('staff can fetch editable company data including postcode by nip', function () {
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Http::fake([
        'wl-api.mf.gov.pl/*' => Http::response([
            'result' => [
                'subject' => [
                    'name' => 'PRZYKŁADOWA SPÓŁKA Z OGRANICZONĄ ODPOWIEDZIALNOŚCIĄ',
                    'residenceAddress' => 'ul. Testowa 12, 00-123 Warszawa',
                ],
            ],
        ]),
    ]);

    $this->actingAs($admin)
        ->postJson(route('companies.fetchGus'), ['nip' => '  NIP: PL 123-456-78-90 / skopiowany z KRS  '])
        ->assertOk()
        ->assertJson([
            'address' => 'ul. Testowa 12',
            'city' => 'Warszawa',
            'postcode' => '00-123',
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/search/nip/1234567890'));
});

test('company form keeps an untrimmed NIP and normalizes it when saved', function () {
    Mail::fake();
    Role::findOrCreate('superadmin');
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    $dashboard = $this->actingAs($superadmin)->get(route('dashboard'))->assertOk();
    preg_match('/<input[^>]+id="nip-input"[^>]*>/i', $dashboard->getContent(), $nipInput);

    expect($nipInput[0] ?? '')->not->toContain('maxlength=')
        ->and($nipInput[0] ?? '')->not->toContain('oninput=');

    $this->actingAs($superadmin)
        ->post(route('companies.store'), [
            'company_type' => 'client',
            'name' => 'Firma z kopiowanym NIP',
            'nip' => '  NIP: PL 123-456-78-90 / skopiowany z KRS  ',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('companies', [
        'name' => 'Firma z kopiowanym NIP',
        'nip' => '1234567890',
    ]);
});
