<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
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
        ->postJson(route('companies.fetchGus'), ['nip' => '123-456-78-90'])
        ->assertOk()
        ->assertJson([
            'address' => 'ul. Testowa 12',
            'city' => 'Warszawa',
            'postcode' => '00-123',
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/search/nip/1234567890'));
});
