<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\Cylinder;
use App\Models\CylinderInspection;
use App\Models\User;
use App\Services\DocumentQuotaService;
use App\Support\CylinderVideoLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function cylinderStaff(): User
{
    Role::findOrCreate('superadmin', 'web');
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    return $user;
}

function enableCylinders(): void
{
    CompanySettings::create(['name' => 'Firma przeglądów', 'enabled_modules' => ['dashboard', 'crm', 'client_zone', 'cylinders']]);
}

function registeredCylinder(string $serial = 'ABC-123'): Cylinder
{
    $company = Company::create(['name' => 'Właściciel '.$serial, 'company_type' => 'client', 'status' => 'active']);

    return Cylinder::create(['company_id' => $company->id, 'serial_number' => $serial, 'type' => 'Butla testowa']);
}

test('cylinders are opt in for missing null and existing module settings', function () {
    $user = cylinderStaff();
    expect(CompanySettings::moduleIsEnabled('cylinders'))->toBeFalse();
    $this->actingAs($user)->get(route('cylinders.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('cylinders.index'), false);
    $settings = CompanySettings::create(['name' => 'Starsze wdrożenie']);
    expect($settings->moduleEnabled('cylinders'))->toBeFalse()->and($settings->moduleEnabled('audits'))->toBeTrue();
    $this->get(route('cylinders.create'))->assertForbidden();
    $this->post(route('cylinders.store'), [])->assertForbidden();
    $this->get(route('settings.company'))->assertOk()->assertSee('Inspektor UDT');
    $settings->update(['enabled_modules' => ['dashboard', 'audits']]);
    expect($settings->moduleEnabled('cylinders'))->toBeFalse();
});

test('superadmin can explicitly enable cylinders in company settings and disable them again', function () {
    $this->actingAs(cylinderStaff())->post(route('settings.company.update'), [
        'name' => 'Firma przeglądów', 'primary_color' => '#123456', 'welcome_page_mode' => 'general', 'enabled_modules' => ['cylinders'],
    ])->assertRedirect();
    expect(CompanySettings::moduleIsEnabled('cylinders'))->toBeTrue();
    $this->get(route('cylinders.index'))->assertOk()->assertSee('Rejestr butli');
    CompanySettings::first()->update(['enabled_modules' => []]);
    $this->get(route('cylinders.index'))->assertForbidden();
});

test('staff registers edits archives a cylinder and keeps immutable inspection history', function () {
    enableCylinders();
    $staff = cylinderStaff();
    $company = Company::create(['name' => 'Zakład', 'company_type' => 'client', 'status' => 'active']);
    $this->actingAs($staff)->get(route('cylinders.create'))->assertOk();
    $this->post(route('cylinders.store'), ['company_id' => $company->id, 'serial_number' => 'SN-55', 'type' => 'Test'])->assertRedirect();
    $cylinder = Cylinder::firstOrFail();
    $this->get(route('cylinders.edit', $cylinder))->assertOk();
    $this->put(route('cylinders.update', $cylinder), ['serial_number' => 'SN-55', 'type' => 'Nowy opis'])->assertRedirect();
    $payload = ['inspected_at' => '2026-01-02', 'next_due_at' => '2027-01-02', 'result' => 'further_review', 'observations' => 'Oględziny — uwaga do sprawdzenia'];
    $this->post(route('cylinders.inspections.store', $cylinder), $payload + ['inspector_id' => 999, 'inspector_name' => 'Fałszywy'])->assertRedirect();
    expect($cylinder->inspections()->first()->inspector_id)->toBe($staff->id)
        ->and($cylinder->inspections()->first()->inspector_name)->toBe($staff->name);
    $this->get(route('cylinders.show', $cylinder))->assertOk()->assertSee('Oględziny');
    $this->get(route('cylinders.index', ['q' => 'SN-55']))->assertOk()->assertSee('02.01.2027');
    $this->patch(route('cylinders.archive', $cylinder), ['archived' => 1])->assertRedirect();
    $this->post(route('cylinders.inspections.store', $cylinder), $payload)->assertStatus(409);
    $this->get(route('cylinders.index'))->assertOk()->assertDontSee('SN-55');
    $this->get(route('cylinders.index', ['archived' => 1]))->assertOk()->assertSee('SN-55');
    $this->patch(route('cylinders.archive', $cylinder), ['archived' => 0])->assertRedirect();
    expect($cylinder->inspections()->count())->toBe(1)
        ->and(ActivityLog::where('auditable_type', Cylinder::class)->where('action', 'updated')->exists())->toBeTrue();
});

test('client and staff preview see only their selected company and cannot write', function () {
    enableCylinders();
    $own = registeredCylinder('OWN');
    $other = registeredCylinder('OTHER');
    Role::findOrCreate('client_user', 'web');
    $client = User::factory()->create();
    $client->assignRole('client_user');
    $client->companies()->attach($own->company_id);
    $this->actingAs($client)->get(route('client.cylinders.index'))->assertOk()->assertSee('OWN')->assertDontSee('OTHER');
    $this->get(route('client.cylinders.show', $own))->assertOk()->assertDontSee('Zapisz przegląd');
    $this->get(route('client.cylinders.show', $other))->assertNotFound();
    $this->post(route('cylinders.store'), [])->assertForbidden();
    $this->post(route('cylinders.inspections.store', $own), [])->assertForbidden();
    $this->actingAs(cylinderStaff())->withSession(['client_zone_company_id' => $own->company_id, 'client_zone_company_name' => 'Zakład'])
        ->get(route('client-zone.cylinders.index'))->assertOk()->assertSee('OWN')->assertDontSee('OTHER');
    $this->get(route('client-zone.cylinders.show', $other))->assertNotFound();
    CompanySettings::first()->update(['enabled_modules' => ['client_zone']]);
    $this->actingAs($client)->get(route('client.cylinders.index'))->assertForbidden();
});

test('cylinder permissions distinguish read access from inspector write access', function () {
    enableCylinders();
    Role::findOrCreate('Inspektor', 'web');
    $staff = User::factory()->create();
    $staff->assignRole('Inspektor');
    $this->actingAs($staff)->get(route('cylinders.index'))->assertForbidden();
    $staff->givePermissionTo('cylinders.view');
    $this->get(route('cylinders.index'))->assertOk();
    $this->get(route('cylinders.create'))->assertForbidden();
    $staff->givePermissionTo('cylinders.manage');
    $this->get(route('cylinders.create'))->assertOk();
});

test('validation prevents moving history and invalid inspection dates', function () {
    enableCylinders();
    $cylinder = registeredCylinder();
    $this->actingAs(cylinderStaff())->put(route('cylinders.update', $cylinder), ['company_id' => 999, 'serial_number' => 'ABC', 'type' => 'Test'])->assertSessionHasErrors('company_id');
    $this->post(route('cylinders.store'), ['company_id' => $cylinder->company_id, 'serial_number' => 'ABC-123', 'type' => 'Test'])->assertSessionHasErrors('serial_number');
    $this->post(route('cylinders.inspections.store', $cylinder), ['inspected_at' => '2026-01-01', 'next_due_at' => '2025-01-01', 'result' => 'automatic_approval', 'observations' => 'Test'])->assertSessionHasErrors(['next_due_at', 'result']);
    expect($cylinder->inspections()->count())->toBe(0);
});

test('backdated inspection does not replace the latest actual inspection date', function () {
    enableCylinders();
    $cylinder = registeredCylinder();
    $this->actingAs(cylinderStaff());
    foreach (['2026-02-01' => '2027-02-01', '2026-01-01' => '2027-01-01'] as $date => $due) {
        $this->post(route('cylinders.inspections.store', $cylinder), ['inspected_at' => $date, 'next_due_at' => $due, 'result' => 'further_review', 'observations' => 'Test'])->assertRedirect();
    }
    $this->get(route('cylinders.index'))->assertOk()->assertSee('01.02.2027')->assertDontSee('01.01.2027');
});

test('cylinder colour priorities and calendar month boundaries are explicit', function () {
    $this->travelTo(now()->setDate(2026, 1, 31)->startOfDay());
    $cylinder = new Cylinder;
    $cylinder->setRelation('latestInspection', null);
    expect($cylinder->conditionStatus())->toBe('unknown');
    foreach ([
        ['defects_found', '2026-01-01', 'problem'],
        ['further_review', '2027-01-01', 'problem'],
        ['no_findings', '2026-01-30', 'overdue'],
        ['no_findings', '2026-01-31', 'soon'],
        ['no_findings', '2026-02-28', 'soon'],
        ['no_findings', '2026-03-01', 'ok'],
        ['no_findings', null, 'unknown'],
    ] as [$result, $due, $status]) {
        $cylinder->setRelation('latestInspection', new CylinderInspection(['result' => $result, 'next_due_at' => $due]));
        expect($cylinder->conditionStatus())->toBe($status);
    }
    $cylinder->archived_at = now();
    expect($cylinder->conditionStatus())->toBe('archived');
});

test('videos are private scoped to a cylinder and counted against uploader quota', function () {
    Storage::fake('local');
    enableCylinders();
    $cylinder = registeredCylinder('VIDEO-OWN');
    $other = registeredCylinder('VIDEO-OTHER');
    $user = cylinderStaff();
    $this->actingAs($user)->post(route('cylinders.videos.store', $cylinder), [
        'title' => 'Oględziny', 'file' => UploadedFile::fake()->create('film.mp4', 10, 'video/mp4'),
    ])->assertRedirect(route('cylinders.show', $cylinder));
    $video = $cylinder->videos()->firstOrFail();
    Storage::disk('local')->assertExists($video->stored_path);
    expect(app(DocumentQuotaService::class)->used($user->id))->toBe(10240)
        ->and(app(DocumentQuotaService::class)->usedMany([$user->id])->get($user->id))->toBe(10240);
    $this->get(route('cylinders.show', $cylinder))->assertOk()->assertSee('Oględziny')->assertSee('<video', false);
    $this->get(route('cylinders.videos.show', [$cylinder, $video]))->assertOk()->assertHeader('Content-Type', 'video/mp4');
    $this->get(route('cylinders.videos.show', [$other, $video]))->assertNotFound();
    Role::findOrCreate('client_user');
    $client = User::factory()->create();
    $client->assignRole('client_user');
    $client->companies()->attach($cylinder->company_id);
    $this->actingAs($client)->get(route('client.cylinders.videos.show', [$cylinder, $video]))->assertOk();
    $client->companies()->detach();
    $client->unsetRelation('companies');
    $this->get(route('client.cylinders.videos.show', [$cylinder, $video]))->assertNotFound();
    $this->post(route('cylinders.videos.store', $cylinder), [])->assertForbidden();
    CompanySettings::first()->update(['enabled_modules' => ['client_zone']]);
    $this->get(route('client.cylinders.videos.show', [$cylinder, $video]))->assertForbidden();
    $this->actingAs($user)->get(route('cylinders.videos.show', [$cylinder, $video]))->assertForbidden();
});

test('invalid video uploads and quota overflow leave no files or rows', function () {
    Storage::fake('local');
    enableCylinders();
    $cylinder = registeredCylinder();
    $user = cylinderStaff();
    $this->actingAs($user)->post(route('cylinders.videos.store', $cylinder), ['title' => 'Test', 'file' => UploadedFile::fake()->createWithContent('film.mp4', '<html>not video</html>')])->assertSessionHasErrors('file');
    $user->forceFill(['document_limit_bytes' => 100])->save();
    $this->post(route('cylinders.videos.store', $cylinder), ['title' => 'Test', 'file' => UploadedFile::fake()->create('film.mp4', 10, 'video/mp4')])->assertSessionHasErrors('file');
    expect($cylinder->videos()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('video link parser only embeds trusted providers and keeps other links external', function () {
    foreach ([
        'https://youtu.be/abcdefghijk?si=example',
        'https://www.youtube.com/watch?v=abcdefghijk&t=12',
        'https://m.youtube.com/shorts/abcdefghijk',
        'https://www.youtube.com/live/abcdefghijk',
        'https://www.youtube-nocookie.com/embed/abcdefghijk',
    ] as $url) {
        expect(CylinderVideoLink::parse($url))->toBe(['provider' => 'YouTube', 'embed' => 'https://www.youtube-nocookie.com/embed/abcdefghijk']);
    }
    foreach (['https://drive.google.com/file/d/exampleFile123/view?usp=sharing', 'https://drive.google.com/open?id=exampleFile123', 'https://drive.google.com/file/d/exampleFile123/preview'] as $url) {
        expect(CylinderVideoLink::parse($url)['embed'])->toBe('https://drive.google.com/file/d/exampleFile123/preview');
    }
    expect(CylinderVideoLink::parse('https://drive.google.com/file/d/exampleFile123/view?resourcekey=0-secretKey')['embed'])
        ->toBe('https://drive.google.com/file/d/exampleFile123/preview?resourcekey=0-secretKey');
    expect(CylinderVideoLink::parse('https://vimeo.com/12345678')['embed'])->toBeNull()
        ->and(CylinderVideoLink::parse('https://www.youtube.com.evil.example/watch?v=abcdefghijk')['embed'])->toBeNull();
    foreach (['javascript:alert(1)', 'http://youtu.be/abcdefghijk', '//youtube.com/watch?v=abcdefghijk', 'https://user:pass@youtube.com/watch?v=abcdefghijk', 'https://localhost/video', 'https://127.0.0.1/video', 'https://example.com:8080/video', 'https://youtube.com/watch?v[]=abcdefghijk', 'https://youtube.com/channel/123', 'https://drive.google.com/drive/folders/exampleFile123', 'https://drive.google.com/open?id[]=exampleFile123', '<iframe src="https://example.com"></iframe>'] as $url) {
        expect(CylinderVideoLink::parse($url))->toBeNull();
    }
});

test('staff saves external video links without upload quota and no server fetch', function () {
    Http::fake();
    Storage::fake('local');
    enableCylinders();
    $cylinder = registeredCylinder();
    $user = cylinderStaff();
    $user->forceFill(['document_limit_bytes' => 0])->save();
    $url = 'https://drive.google.com/file/d/exampleFile123/view?resourcekey=0-privateKey';
    $this->actingAs($user)->post(route('cylinders.videos.store', $cylinder), ['source' => 'link', 'title' => 'Film z Google', 'external_url' => $url])->assertRedirect();
    $video = $cylinder->videos()->firstOrFail();
    expect($video->external_url)->toBe($url)->and((int) $video->size)->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(app(DocumentQuotaService::class)->used($user->id))->toBe(0)
        ->and(ActivityLog::all()->toJson())->not->toContain('privateKey');
    $this->get(route('cylinders.show', $cylinder))->assertOk()->assertSee('Odtwórz tutaj')->assertSee('Otwórz film u źródła')
        ->assertSee('drive.google.com/file/d/exampleFile123/preview', false);
    $this->get(route('cylinders.videos.show', [$cylinder, $video]))->assertNotFound();
    Http::assertNothingSent();
});

test('video sources are mutually exclusive and external URLs are validated', function () {
    enableCylinders();
    $cylinder = registeredCylinder();
    $this->actingAs(cylinderStaff());
    $this->post(route('cylinders.videos.store', $cylinder), ['source' => 'link', 'title' => 'Test', 'external_url' => 'javascript:alert(1)'])->assertSessionHasErrors('external_url');
    $this->post(route('cylinders.videos.store', $cylinder), ['source' => 'link', 'title' => 'Test'])->assertSessionHasErrors('external_url');
    $this->post(route('cylinders.videos.store', $cylinder), ['source' => 'link', 'title' => 'Test', 'external_url' => 'https://youtu.be/abcdefghijk', 'file' => UploadedFile::fake()->create('film.mp4', 10, 'video/mp4')])->assertSessionHasErrors('file');
    expect($cylinder->videos()->count())->toBe(0);
});
