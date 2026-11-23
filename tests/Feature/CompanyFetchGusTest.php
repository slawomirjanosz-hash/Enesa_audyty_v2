<?php

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyDataLookup;
use App\Services\GusRegistryClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['services.gus.key' => null]);
    Http::preventStrayRequests();
});

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
            'source' => 'mf',
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
            'postcode' => '27-200',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('companies', [
        'name' => 'Firma z kopiowanym NIP',
        'nip' => '1234567890',
        'postcode' => '27-200',
    ]);

    $duplicate = $this->actingAs($superadmin)
        ->from(route('dashboard'))
        ->post(route('companies.store'), [
            'company_type' => 'client',
            'name' => 'Druga firma z tym samym NIP',
            'nip' => '123-456-78-90',
        ]);

    $duplicate->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors(['nip' => 'Firma o podanym NIP już istnieje.']);
});

test('MF addresses preserve locality and postcode without placeholder building numbers', function ($residence, $working, $address, $postcode, $city) {
    Role::findOrCreate('admin');
    $admin = User::factory()->create()->assignRole('admin');
    Http::fake(['wl-api.mf.gov.pl/*' => Http::response(['result' => ['subject' => [
        'nip' => '6640000816', 'name' => 'Firma', 'residenceAddress' => $residence, 'workingAddress' => $working,
    ]]])]);
    $this->actingAs($admin)->postJson(route('companies.fetchGus'), ['nip' => '6640000816'])
        ->assertOk()->assertJson(compact('address', 'postcode', 'city'));
})->with([
    ['ZĘBIEC -, 27-200 ZĘBIEC', null, 'ZĘBIEC', '27-200', 'ZĘBIEC'],
    ['', 'ul. Jana Pawła II 7/2, 00-001 Bielsko-Biała', 'ul. Jana Pawła II 7/2', '00-001', 'Bielsko-Biała'],
    ['27-200 ZĘBIEC', null, '', '27-200', 'ZĘBIEC'],
    ['ul. Długa 2 00-001 Warszawa', null, 'ul. Długa 2', '00-001', 'Warszawa'],
    ['ul. Długa 2, Warszawa', null, 'ul. Długa 2', '', 'Warszawa'],
]);

function companyGusSoapResult(string $operation, string $result): string
{
    return '<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body><'.$operation.'Response xmlns="http://CIS/BIR/PUBL/2014/07"><'.$operation.'Result>'.htmlspecialchars($result, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</'.$operation.'Result></'.$operation.'Response></s:Body></s:Envelope>';
}

test('configured GUS lookup reads structured address from MTOM and closes its session', function () {
    config(['services.gus.key' => 'fake-key-only-for-test']);
    $sid = '01234567890123456789';
    $rows = '<root><dane><Nip>6640000816</Nip><Regon>123456789</Regon><Nazwa>Firma</Nazwa><Miejscowosc>Zębiec</Miejscowosc><KodPocztowy>27-200</KodPocztowy><Ulica>Przemysłowa</Ulica><NrNieruchomosci>1A</NrNieruchomosci><NrLokalu>2</NrLokalu></dane></root>';
    Http::fakeSequence()
        ->push(companyGusSoapResult('Zaloguj', $sid))
        ->push('--boundary\r\nContent-Type: application/xop+xml\r\n\r\n'.companyGusSoapResult('DaneSzukajPodmioty', $rows).'\r\n--boundary--')
        ->push(companyGusSoapResult('Wyloguj', 'true'));

    expect(app(CompanyDataLookup::class)->lookup('6640000816'))->toMatchArray([
        'address' => 'Przemysłowa 1A/2', 'postcode' => '27-200', 'city' => 'Zębiec', 'source' => 'gus',
    ]);
    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => str_contains($request->body(), '<d:Nip>6640000816</d:Nip>') && $request->hasHeader('sid', $sid));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'wl-api'));
});

test('GUS failure falls back to explicitly labelled MF data without exposing credentials', function () {
    config(['services.gus.key' => 'fake-secret-test']);
    Http::fake([
        'wyszukiwarkaregon.stat.gov.pl/*' => Http::response('Bad response containing fake-secret-test', 503),
        'wl-api.mf.gov.pl/*' => Http::response(['result' => ['subject' => ['name' => 'Firma', 'nip' => '6640000816', 'workingAddress' => 'ul. Testowa 1, 27-200 Zębiec']]]),
    ]);
    Role::findOrCreate('admin');
    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin)->postJson(route('companies.fetchGus'), ['nip' => '6640000816'])->assertOk()
        ->assertJsonPath('source', 'mf')->assertJsonPath('city', 'Zębiec')->assertDontSee('fake-secret-test');
});

test('GUS rejects ambiguous companies and XML entities instead of filling incorrect data', function ($rows) {
    config(['services.gus.key' => 'fake-key-test']);
    Http::fakeSequence()
        ->push(companyGusSoapResult('Zaloguj', '01234567890123456789'))
        ->push(companyGusSoapResult('DaneSzukajPodmioty', $rows))
        ->push(companyGusSoapResult('Wyloguj', 'true'));
    expect(fn () => app(GusRegistryClient::class)->lookup('6640000816'))->toThrow(RuntimeException::class);
    Http::assertSentCount(3);
})->with([
    ['<root><dane><Nip>1234567890</Nip><Regon>123456789</Regon><Nazwa>Wrong company</Nazwa></dane></root>'],
    ['<root><dane><Nip>6640000816</Nip><Regon>123456789</Regon><Nazwa>Company one</Nazwa></dane><dane><Nip>6640000816</Nip><Regon>987654321</Regon><Nazwa>Company two</Nazwa></dane></root>'],
    ['<!DOCTYPE root [<!ENTITY test "unexpected">]><root><dane><Nazwa>&test;</Nazwa></dane></root>'],
]);

test('lookup rejects a different returned NIP and remains protected from client access', function () {
    Role::findOrCreate('admin');
    $admin = User::factory()->create()->assignRole('admin');
    Http::fake(['wl-api.mf.gov.pl/*' => Http::response(['result' => ['subject' => ['name' => 'Wrong company', 'nip' => '1234567890']]])]);
    $this->actingAs($admin)->postJson(route('companies.fetchGus'), ['nip' => '6640000816'])->assertStatus(503)->assertDontSee('Wrong company');
    Role::findOrCreate('client');
    $client = User::factory()->create()->assignRole('client');
    $this->actingAs($client)->postJson(route('companies.fetchGus'), ['nip' => '6640000816'])->assertForbidden();
    Http::assertSentCount(1);
});

test('company postcode can be edited and is shown on its card', function () {
    Role::findOrCreate('superadmin');
    $admin = User::factory()->create()->assignRole('superadmin');
    $company = Company::create(['name' => 'Firma testowa', 'company_type' => 'client', 'status' => 'active', 'city' => 'Zębiec', 'address' => 'Przemysłowa 1']);
    $this->actingAs($admin)->put(route('companies.update', $company), ['name' => $company->name, 'company_type' => 'client', 'city' => 'Zębiec', 'postcode' => '27-200'])->assertSessionHasNoErrors();
    expect($company->fresh()->postcode)->toBe('27-200');
    $this->get(route('companies.show', $company))->assertOk()->assertSee('27-200');
});
