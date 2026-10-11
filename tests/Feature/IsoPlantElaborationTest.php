<?php

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Models\User;
use App\Services\IsoPlantElaboration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function elaborationFixture(): array
{
    $company = Company::create(['name' => 'Zakład opracowania', 'company_type' => 'client', 'status' => 'active']);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'OPR/001', 'title' => 'ISO', 'status' => 'draft']);
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $audit->surveys()->create(['audit_type_id' => $type->id, 'title' => 'ISO', 'status' => 'draft']);
    $site = DB::table('iso_plant_sites')->insertGetId(['company_id' => $company->id, 'name' => 'Zakład A', 'created_at' => now(), 'updated_at' => now()]);
    $staff = User::factory()->create();
    $staff->assignRole('superadmin');
    $client = User::factory()->create();
    $client->assignRole(Role::findOrCreate('client_admin'));
    $client->companies()->attach($company);
    $answers = [];
    foreach (['organization.name' => $company->name, 'organization.tax_id' => '1234567890', 'site.name' => 'Zakład A', 'site.address' => 'Katowice', 'profile.contact_person' => 'Anna Testowa', 'FAKT_BRANZA' => 'Obróbka metalu', 'FAKT_PRACOWNICY' => 0, 'FAKT_ROK_ODNIESIENIA' => 2025, 'FAKT_SYSTEM_BAZOWY' => 'brak', 'FAKT_ZGODA_INTEGRACJA' => 'tak', 'FAKT_ENERGY_MANAGER' => 'nie', 'FAKT_PODLICZNIKI' => 'brak', 'FAKT_ANALIZA_DANYCH' => 'nie', 'FAKT_NOSNIKI' => [['c0' => 'energia elektryczna', 'c1' => 1000, 'c2' => 'MWh', 'c3' => 0]]] as $key => $value) {
        $answers[$key] = ['value' => $value, 'unknown' => false, 'source' => 'Źródło testowe'];
    }
    $profile = IsoPlantProfile::create(['audit_id' => $audit->id, 'site_id' => $site, 'revision' => 1, 'lock_version' => 2, 'status' => 'approved', 'as_of_date' => '2026-10-11', 'definition' => json_decode(file_get_contents(resource_path('iso50001/plant-profile-v2.json')), true), 'answers' => $answers, 'client_approval' => ['name' => $client->name, 'user_id' => $client->id, 'at' => now()->toIso8601String()], 'auditor_approval' => ['name' => $staff->name, 'user_id' => $staff->id, 'at' => now()->toIso8601String()], 'issuer' => ['name' => 'ENESA', 'color' => '#204060'], 'review_note' => 'Potwierdzono źródła danych; braki do uzupełnienia w dalszych etapach.']);

    return [$audit, $profile, $staff, $client];
}

test('plant elaboration synthesizes facts and preserves uncertainty and inactive answers', function () {
    [, $profile] = elaborationFixture();
    $report = app(IsoPlantElaboration::class)->build($profile);
    $text = json_encode($report, JSON_UNESCAPED_UNICODE);
    expect($text)->toContain('Obróbka metalu', '0 osób', '0,00 zł', '3,6 TJ', 'roku 2025', 'nie średnia z trzech lat', 'Dostępny jest tylko licznik główny')
        ->not->toContain('Zadeklarowano wykorzystanie istniejącej dokumentacji');
    expect($report['gaps'])->not->toBeEmpty()->not->toHaveKey('FAKT_ODCZYT');
    expect(array_column($report['evidence'], 'key'))->not->toContain('FAKT_ZGODA_INTEGRACJA');
    $answers = $profile->answers;
    $answers['FAKT_NOSNIKI'] = ['unknown' => true, 'value' => [['c0' => 'gaz ziemny', 'c1' => 100, 'c2' => 'MWh']]];
    $profile->answers = $answers;
    expect(json_encode(app(IsoPlantElaboration::class)->build($profile), JSON_UNESCAPED_UNICODE))->not->toContain('0,00 zł', '3,6 TJ');
});

test('auditor saves a separate reusable elaboration while retaining questionnaire PDF and access scopes', function () {
    [$audit, $profile, $staff, $client] = elaborationFixture();
    $url = route('audits.plant-profile.pdf', [$audit, $profile]);
    $payload = ['kind' => 'elaboration', 'lock_version' => 2];
    $reader = User::factory()->create();
    $reader->assignRole(Role::findOrCreate('employee'));
    $reader->givePermissionTo(Permission::findOrCreate('audits.view'));
    $this->actingAs($reader)->get(route('audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertDontSee('Opracuj ankietę');
    $this->post($url, $payload)->assertForbidden();
    $this->actingAs($client)->post(route('client.audits.plant-profile.pdf', [$audit, $profile]), $payload)->assertForbidden();
    $this->actingAs($staff)->get(route('audits.plant-profile.show', [$audit, $profile]))->assertOk()->assertSee('Opracuj ankietę');
    $this->post($url, $payload + ['preview' => 1])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(IsoSectionDocument::count())->toBe(0);
    $this->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
    $id = $profile->fresh()->elaboration_document_id;
    expect($id)->not->toBeNull()->and($profile->fresh()->document_id)->toBeNull();
    $this->get(route('audits.plant-profile.show', [$audit, $profile]))->assertSee('data-questionnaire-status="3" data-reached="true"', false);
    expect(IsoSectionDocument::find($id)->contents())->toStartWith('%PDF');
    $this->post($url, $payload)->assertRedirect();
    expect(IsoSectionDocument::count())->toBe(1);
    $this->post($url, ['lock_version' => 2])->assertRedirect();
    expect($profile->fresh()->document_id)->not->toBeNull()->not->toBe($id);
    $this->actingAs($client)->get(route('client.audits.iso-documents.download', [$audit, $id]))->assertOk();
    $outsider = User::factory()->create();
    $outsider->assignRole('client_admin');
    $this->actingAs($outsider)->get(route('client.audits.iso-documents.download', [$audit, $id]))->assertNotFound();
    $this->actingAs($staff)->post($url, ['kind' => 'elaboration', 'lock_version' => 1])->assertStatus(409);
    $answers = $profile->answers;
    $answers['site.name']['value'] = 'Zakład po korekcie';
    $this->post(route('audits.plant-profile.update', [$audit, $profile]), ['operation' => 'save', 'complete_form' => 1, 'lock_version' => 2, 'as_of_date' => '2026-10-11', 'answers' => $answers])->assertRedirect()->assertSessionHasNoErrors();
    expect($profile->fresh()->elaboration_document_id)->toBeNull()->and(IsoSectionDocument::find($id)->description)->toContain('historyczne');
    $this->post($url, ['kind' => 'elaboration', 'lock_version' => 3])->assertForbidden();
});

test('elaboration rejects older approved profiles and survives deletion without dangling references', function () {
    [$audit, $profile, $staff] = elaborationFixture();
    $url = route('audits.plant-profile.pdf', [$audit, $profile]);
    $this->actingAs($staff)->post($url, ['kind' => 'elaboration', 'lock_version' => 2])->assertRedirect();
    IsoSectionDocument::findOrFail($profile->fresh()->elaboration_document_id)->delete();
    expect($profile->fresh()->elaboration_document_id)->toBeNull();
    $next = $profile->replicate();
    $next->revision = 2;
    $next->save();
    $this->post($url, ['kind' => 'elaboration', 'lock_version' => 2])->assertStatus(409);
});
