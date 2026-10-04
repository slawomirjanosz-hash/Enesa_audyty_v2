<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyReliabilityFile;
use App\Models\CompanyReliabilityReport;
use App\Models\User;
use App\Services\CompanyRegistryLookup;
use App\Services\DocumentQuotaService;
use App\Support\FinancialAmount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'superadmin', 'client_user', 'client_admin', 'auditor', 'auditor_senior'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    Storage::fake('local');
    Http::preventStrayRequests();
    Cache::flush();
    $this->company = Company::create(['name' => 'Test Wiarygodności Sp. z o.o.', 'nip' => '5260250274', 'status' => 'active', 'company_type' => 'client', 'show_in_dashboard' => true]);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->payload = ['status' => 'unassessed', 'legal' => 'unknown', 'krz' => 'unknown', 'debt' => 'unknown',
        'notes' => 'Poufne zalecenia: wymagana dalsza weryfikacja.', 'verified_on' => now()->format('Y-m-d')];
});

test('automatic reports save without manual declarations and preserve unverified coverage', function () {
    $this->actingAs($this->admin)->post(route('companies.reliability.store', $this->company), ['status' => 'auto'])->assertSessionHasNoErrors()->assertRedirect();
    $report = CompanyReliabilityReport::firstOrFail();
    expect($report->status)->toBe('yellow');
    expect($report->snapshot['assessment_mode'])->toBe('automatic');
    expect($report->snapshot['assessment']['krz'])->toBe('unknown');
    expect($report->snapshot['automatic']['checks'][3]['state'])->toBe('unknown');
    $this->post(route('companies.reliability.store', $this->company), ['status' => 'auto', 'krz' => 'risk'])->assertSessionHasNoErrors();
    expect(CompanyReliabilityReport::latest('id')->first()->status)->toBe('red');
});

test('financial fields accept Polish formatted money and store canonical amounts', function () {
    $payload = array_replace($this->payload, ['finances' => [['year' => 2025, 'revenue' => '141 337 288,45 zł', 'profit' => '-9 871,86 zł', 'equity' => '0,00 zł', 'liabilities' => '', 'source' => 'Test XML']]]);
    $this->actingAs($this->admin)->post(route('companies.reliability.store', $this->company), $payload)->assertSessionHasNoErrors();
    expect(CompanyReliabilityReport::firstOrFail()->snapshot['assessment']['finances'][0])->toMatchArray(['revenue' => '141337288.45', 'profit' => '-9871.86', 'equity' => '0.00', 'liabilities' => null]);
    expect(FinancialAmount::display('141337288.45'))->toBe('141 337 288,45 zł');
    expect(FinancialAmount::display(null))->toBe('');
    $payload['finances'][0]['revenue'] = 'niepoprawna kwota';
    $this->post(route('companies.reliability.store', $this->company), $payload)->assertSessionHasErrors('finances.0.revenue');
});

test('KRS registration date is read from the header and invalid dates remain unknown', function ($raw, $expected) {
    Http::fake(['wl-api.mf.gov.pl/*' => Http::response([], 503), 'api-krs.ms.gov.pl/*' => Http::response(['odpis' => ['naglowekA' => ['dataRejestracjiWKRS' => $raw], 'dane' => ['dzial1' => ['danePodmiotu' => ['identyfikatory' => ['nip' => $this->company->nip]]]]]])]);
    $lookup = app(CompanyRegistryLookup::class)->lookup($this->company, '0000123456');
    expect($lookup['krs']['registered_on'])->toBe($expected);
})->with([['21.08.2002', '2002-08-21'], ['31.02.2002', null]]);

test('source files are private downloadable attachments with physical deletion and sortable listing', function () {
    $upload = UploadedFile::fake()->createWithContent('sprawozdanie.xml', '<?xml version="1.0"?><Sprawozdanie/>');
    $this->actingAs($this->admin)->post(route('companies.reliability.files.store', $this->company), ['file' => $upload])->assertSessionHasNoErrors()->assertRedirect();
    $file = CompanyReliabilityFile::firstOrFail();
    Storage::disk('local')->assertExists($file->stored_path);
    expect($file->storage_owner_id)->toBe($this->admin->id);
    expect(app(DocumentQuotaService::class)->used($this->admin->id))->toBe($file->size);
    $this->get(route('companies.reliability.show', $this->company))->assertOk()->assertSee('https://rdf-przegladarka.ms.gov.pl/')->assertSee('sprawozdanie.xml')->assertSee('data-sort-value', false);
    $this->get(route('companies.reliability.files.download', [$this->company, $file]))->assertDownload('sprawozdanie.xml')->assertHeader('Content-Type', 'application/octet-stream');
    $other = Company::create(['name' => 'Inna firma', 'status' => 'active']);
    $this->get(route('companies.reliability.files.download', [$other, $file]))->assertNotFound();
    $this->delete(route('companies.reliability.files.destroy', [$other, $file]))->assertNotFound();
    $client = User::factory()->create();
    $client->assignRole('client_admin');
    $client->givePermissionTo('company_reliability.view', 'company_reliability.create', 'company_reliability.delete');
    $this->actingAs($client)->get(route('companies.reliability.files.download', [$this->company, $file]))->assertForbidden();
    $this->delete(route('companies.reliability.files.destroy', [$this->company, $file]))->assertForbidden();
    $this->post(route('companies.reliability.files.store', $this->company), ['file' => $upload])->assertForbidden();
    $reader = User::factory()->create();
    $reader->assignRole('auditor_senior');
    $reader->givePermissionTo('company_reliability.view');
    $this->actingAs($reader)->get(route('companies.reliability.files.download', [$this->company, $file]))->assertOk();
    $this->delete(route('companies.reliability.files.destroy', [$this->company, $file]))->assertForbidden();
    $this->actingAs($this->admin)->delete(route('companies.reliability.files.destroy', [$this->company, $file]))->assertRedirect();
    Storage::disk('local')->assertMissing($file->stored_path);
    expect(CompanyReliabilityFile::count())->toBe(0);
});

test('deleting a company removes its private source files', function () {
    $this->actingAs($this->admin)->post(route('companies.reliability.files.store', $this->company), ['file' => UploadedFile::fake()->createWithContent('data.xml', '<Data/>')])->assertSessionHasNoErrors();
    $file = CompanyReliabilityFile::firstOrFail();
    $this->company->delete();
    Storage::disk('local')->assertMissing($file->stored_path);
    expect(CompanyReliabilityFile::count())->toBe(0);
});

test('source file uploads enforce quota type and reader permissions', function () {
    $reader = User::factory()->create();
    $reader->assignRole('auditor_senior');
    $reader->givePermissionTo('company_reliability.view');
    $this->actingAs($reader)->post(route('companies.reliability.files.store', $this->company))->assertForbidden();
    $this->actingAs($this->admin)->post(route('companies.reliability.files.store', $this->company), ['file' => UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;')])->assertSessionHasErrors('file');
    $this->admin->forceFill(['document_limit_bytes' => 1])->save();
    $this->post(route('companies.reliability.files.store', $this->company), ['file' => UploadedFile::fake()->createWithContent('data.xml', '<Data>test</Data>')])->assertSessionHasErrors('file');
    expect(CompanyReliabilityFile::count())->toBe(0);
    expect(Storage::disk('local')->allFiles('private-reliability-sources'))->toBe([]);
});

test('only opted in staff can access reliability even with broad document or system rights', function () {
    foreach (['client_user', 'client_admin', 'auditor_senior'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->givePermissionTo('documents.view', 'system.full_access');
        $this->actingAs($user)->get(route('companies.reliability.show', $this->company))->assertForbidden();
        $this->actingAs($user)->post(route('companies.reliability.store', $this->company), $this->payload)->assertForbidden();
        if (str_starts_with($role, 'client')) {
            $user->givePermissionTo('company_reliability.view', 'company_reliability.create');
            $this->actingAs($user)->get(route('companies.reliability.show', $this->company))->assertForbidden();
        }
    }
    $this->actingAs($this->admin)->get(route('companies.reliability.show', $this->company))->assertOk()->assertSee('Raport wiarygodności firmy');
});

test('report is stored privately and cannot leak through client document listings or shared document ids', function () {
    $this->actingAs($this->admin)->post(route('companies.reliability.store', $this->company), $this->payload)->assertSessionHasNoErrors()->assertRedirect();
    $report = CompanyReliabilityReport::firstOrFail();
    Storage::disk('local')->assertExists($report->stored_path);
    expect(substr(Storage::disk('local')->get($report->stored_path), 0, 4))->toBe('%PDF');
    expect($this->company->documents()->count())->toBe(0);
    expect(json_encode(ActivityLog::where('auditable_type', CompanyReliabilityReport::class)->pluck('changes')))->not->toContain('Poufne zalecenia');
    $this->actingAs($this->admin)->get(route('companies.reliability.download', [$this->company, $report]))->assertOk()->assertDownload();
    $client = User::factory()->create();
    $client->assignRole('client_admin');
    $this->company->users()->attach($client);
    $this->actingAs($client)->get(route('companies.reliability.download', [$this->company, $report]))->assertForbidden();
    $this->actingAs($client)->delete(route('companies.reliability.destroy', [$this->company, $report]))->assertForbidden();
});

test('view permission does not grant creation or deletion and foreign company report is rejected', function () {
    $this->actingAs($this->admin)->post(route('companies.reliability.store', $this->company), $this->payload)->assertRedirect();
    $report = CompanyReliabilityReport::firstOrFail();
    $reader = User::factory()->create();
    $reader->assignRole('auditor_senior');
    $reader->givePermissionTo('company_reliability.view');
    $this->actingAs($reader)->get(route('companies.reliability.show', $this->company))->assertOk()->assertDontSee('Stwórz raport i zapisz');
    $this->actingAs($reader)->post(route('companies.reliability.store', $this->company), $this->payload)->assertForbidden();
    $this->actingAs($reader)->delete(route('companies.reliability.destroy', [$this->company, $report]))->assertForbidden();
    $other = Company::create(['name' => 'Inna firma', 'status' => 'active']);
    $this->actingAs($this->admin)->get(route('companies.reliability.download', [$other, $report]))->assertNotFound();
});

test('deleting a report removes disk file and restores previous rating', function () {
    $this->actingAs($this->admin)->post(route('companies.reliability.store', $this->company), $this->payload)->assertRedirect();
    $this->actingAs($this->admin)->post(route('companies.reliability.store', $this->company), array_replace($this->payload, ['status' => 'red', 'krz' => 'risk']))->assertRedirect();
    $report = $this->company->latestReliabilityReport;
    expect($report->status)->toBe('red');
    $this->delete(route('companies.reliability.destroy', [$this->company, $report]))->assertRedirect();
    Storage::disk('local')->assertMissing($report->stored_path);
    expect($this->company->fresh()->latestReliabilityReport->status)->toBe('unassessed');
});

test('missing data and negative checks cannot produce a green rating', function () {
    $this->actingAs($this->admin)->post(route('companies.reliability.store', $this->company), array_replace($this->payload, ['status' => 'green']))->assertSessionHasErrors('status');
    $this->post(route('companies.reliability.store', $this->company), array_replace($this->payload, ['status' => 'yellow', 'debt' => 'risk']))->assertSessionHasErrors('status');
    $this->post(route('companies.reliability.store', $this->company), array_replace($this->payload, ['legal' => 'clear']))->assertSessionHasErrors('evidence');
    expect(CompanyReliabilityReport::count())->toBe(0);
});

test('registries check identity cache results and handle outages without a safe rating', function () {
    Http::fake([
        'wl-api.mf.gov.pl/*' => Http::response(['result' => ['subject' => ['nip' => $this->company->nip, 'name' => 'Test', 'statusVat' => 'Czynny', 'krs' => '0000123456'], 'requestId' => 'test-123']]),
        'api-krs.ms.gov.pl/*' => Http::response(['odpis' => ['dane' => ['dzial1' => ['danePodmiotu' => ['identyfikatory' => ['nip' => $this->company->nip], 'nazwa' => 'Test']], 'dzial6' => []]]]),
    ]);
    $service = app(CompanyRegistryLookup::class);
    expect($service->lookup($this->company, null)['krs']['state'])->toBe('checked');
    $service->lookup($this->company, null);
    Http::assertSentCount(2);
});

test('registry outage is unknown rather than confirmation of safety', function () {
    Http::fake(['*' => Http::response([], 503)]);
    expect(app(CompanyRegistryLookup::class)->lookup($this->company, null)['vat']['state'])->toBe('unavailable');
});

test('complete documented assessment can be green and expires on dashboard after thirty days', function () {
    $lookup = ['checked_at' => now()->toIso8601String(), 'nip' => $this->company->nip, 'vat' => ['state' => 'checked', 'status' => 'Czynny'], 'krs' => ['state' => 'unavailable']];
    $payload = array_replace($this->payload, ['status' => 'green', 'legal' => 'clear', 'krz' => 'clear', 'debt' => 'clear', 'evidence' => 'KRS, KRZ i zaświadczenia: kontrola dzisiaj.',
        'finances' => [['year' => now()->year - 1, 'revenue' => 100000, 'profit' => 1000, 'equity' => 5000, 'liabilities' => 500, 'source' => 'RDF, roczne sprawozdanie']]]);
    $this->actingAs($this->admin)->withSession(['reliability.'.$this->company->id => $lookup])->post(route('companies.reliability.store', $this->company), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $report = CompanyReliabilityReport::firstOrFail();
    expect($report->displayStatus())->toBe('green');
    $this->get(route('dashboard'))->assertOk()->assertSee('Wiarygodna');
    $this->travel(31)->days();
    expect($report->displayStatus())->toBe('yellow');
});

test('dashboard and company documents hide confidential tabs from unprivileged employees', function () {
    $reader = User::factory()->create();
    $reader->assignRole('auditor_senior');
    $this->actingAs($reader)->get(route('dashboard'))->assertOk()->assertDontSee('Raport wiarygodności firmy');
    $this->get(route('companies.show', $this->company))->assertOk()->assertDontSee('Poufne dokumenty');
    $this->actingAs($this->admin)->get(route('companies.show', $this->company))->assertOk()->assertSee('Poufne dokumenty');
    Http::assertNothingSent();
});

test('delegated auditors still require access to the particular company', function () {
    $user = User::factory()->create();
    $user->assignRole('auditor');
    $user->givePermissionTo('company_reliability.view', 'company_reliability.create');
    $this->actingAs($user)->get(route('companies.reliability.show', $this->company))->assertForbidden();
    $this->post(route('companies.reliability.lookup', $this->company))->assertForbidden();
    Http::assertNothingSent();
});

test('lookup does not accept a different company nip from KRS', function () {
    Http::fake([
        'wl-api.mf.gov.pl/*' => Http::response([], 503),
        'api-krs.ms.gov.pl/*' => Http::response(['odpis' => ['dane' => ['dzial1' => ['danePodmiotu' => ['identyfikatory' => ['nip' => '1111111111']]]]]]),
    ]);
    $this->actingAs($this->admin)->post(route('companies.reliability.lookup', $this->company), ['krs' => '0000123456'])->assertRedirect();
    expect(session('reliability.'.$this->company->id.'.krs.state'))->toBe('identity_mismatch');
    $this->get(route('companies.reliability.show', $this->company))->assertOk()->assertSee('NIP z KRS nie zgadza');
});

test('reports count toward storage quota and rejected creation leaves no file', function () {
    $this->admin->forceFill(['document_limit_bytes' => 1])->save();
    $this->actingAs($this->admin)->post(route('companies.reliability.store', $this->company), $this->payload)->assertSessionHasErrors('file');
    expect(CompanyReliabilityReport::count())->toBe(0);
    expect(Storage::disk('local')->allFiles('private-reliability'))->toBe([]);
});

test('report list has sortable data headings and non sortable actions', function () {
    $this->actingAs($this->admin)->post(route('companies.reliability.store', $this->company), $this->payload)->assertRedirect();
    $this->get(route('companies.reliability.show', $this->company))->assertOk()
        ->assertSeeInOrder(['<th>Dokument</th>', '<th>Data</th>', '<th>Ocena w raporcie</th>', '<th>Autor</th>', '<th data-sortable="false">Akcje</th>'], false)
        ->assertSee('table-sort.js');
});
