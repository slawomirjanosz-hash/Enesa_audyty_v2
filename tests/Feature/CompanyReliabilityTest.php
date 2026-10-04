<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyReliabilityReport;
use App\Models\User;
use App\Services\CompanyRegistryLookup;
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
