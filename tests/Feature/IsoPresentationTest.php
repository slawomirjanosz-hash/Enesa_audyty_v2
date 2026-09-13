<?php

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\IsoPresentation;
use App\Models\User;
use App\Services\PresentationRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

function presentationUser(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(Role::findOrCreate($role));

    return $user;
}

function presentationFixture(array $extra = []): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'pptx-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $files = [
        '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>',
        'ppt/presentation.xml' => '<?xml version="1.0"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:sldIdLst><p:sldId id="256" r:id="rId1"/></p:sldIdLst><p:sldSz cx="12192000" cy="6858000"/><p:notesSz cx="6858000" cy="9144000"/></p:presentation>',
        'ppt/_rels/presentation.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/></Relationships>',
        'ppt/slides/slide1.xml' => '<?xml version="1.0"?><p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/><p:sp><p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="500000" y="500000"/><a:ext cx="9000000" cy="1000000"/></a:xfrm></p:spPr><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="pl-PL" sz="3000"/><a:t>ISO 50001 - test</a:t></a:r></a:p></p:txBody></p:sp></p:spTree></p:cSld></p:sld>',
    ];
    foreach (array_replace($files, $extra) as $name => $bytes) {
        $zip->addFromString($name, $bytes);
    }
    $zip->close();
    $upload = UploadedFile::fake()->createWithContent('szkolenie.pptx', file_get_contents($path));
    unlink($path);

    return $upload;
}

test('presentation library includes sixteen example slides but never a source download', function () {
    $example = IsoPresentation::firstOrFail();
    expect($example->slide_count)->toBe(16)->and(DB::table('iso_presentation_slides')->where('iso_presentation_id', $example->id)->count())->toBe(16);
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $this->actingAs(presentationUser('superadmin'))->get(route('audit-types.show', $type))->assertOk()
        ->assertSee('Dodaj prezentację PowerPoint')->assertSee('Otwórz prezentację')->assertSee('Duże okno')
        ->assertSee('data-slide-dialog', false)->assertDontSee('download="', false);
});

test('client can view slides only through an owned audit with ISO assigned', function () {
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $company = Company::create(['name' => 'Firma klienta']);
    $client = presentationUser('client_user');
    $client->companies()->attach($company);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'PRES/1', 'title' => 'Audyt ISO', 'status' => 'draft']);
    $presentation = IsoPresentation::firstOrFail();
    $url = route('client.audits.presentations.slide', [$audit, $presentation, 1]);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($client)->get($url)->assertNotFound();
    $audit->surveys()->create(['audit_type_id' => $type->id, 'title' => 'ISO', 'status' => 'draft']);
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('client.audits.show', $audit))->assertOk()->assertSee('Otwórz prezentację')->assertDontSee('Dodaj prezentację PowerPoint')->assertDontSee('Edytuj lub podmień');
    $this->get(route('client.audits.presentations.slide', [$audit, $presentation, 17]))->assertNotFound();
    $outsider = presentationUser('client_user');
    $this->actingAs($outsider)->get($url)->assertNotFound();
    $this->actingAs($client)->get(route('audit-types.presentations.slide', [$type, $presentation, 1]))->assertForbidden();
    $this->post(route('audit-types.presentations.store', $type), ['section_id' => '4-1', 'title' => 'Nie', 'presentation_file' => presentationFixture()])->assertForbidden();
    $this->delete(route('audit-types.presentations.destroy', [$type, $presentation]))->assertForbidden();
});

test('authorized manager uploads edits and replaces presentation and failed conversion keeps previous slides', function () {
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $bytes = file_get_contents(resource_path('training/iso50001-4-1/slide-1.png'));
    $this->mock(PresentationRenderer::class, fn ($mock) => $mock->shouldReceive('render')->once()->andReturn([$bytes, $bytes]));
    $this->actingAs(presentationUser('superadmin'))->post(route('audit-types.presentations.store', $type), [
        'section_id' => '4-1', 'title' => 'Nowa prezentacja', 'description' => 'Przykładowy opis', 'presentation_file' => presentationFixture(),
    ])->assertRedirect();
    $presentation = IsoPresentation::where('title', 'Nowa prezentacja')->firstOrFail();
    expect($presentation->slide_count)->toBe(2);
    $this->put(route('audit-types.presentations.update', [$type, $presentation]), ['title' => 'Zmieniony tytuł', 'description' => 'Nowy opis'])->assertRedirect();
    expect($presentation->fresh()->title)->toBe('Zmieniony tytuł');
    $this->mock(PresentationRenderer::class, fn ($mock) => $mock->shouldReceive('render')->once()->andThrow(ValidationException::withMessages(['presentation_file' => 'Nie można przekonwertować.'])));
    $this->put(route('audit-types.presentations.update', [$type, $presentation]), ['title' => 'Nie zapisuj', 'presentation_file' => presentationFixture()])->assertSessionHasErrors('presentation_file');
    expect($presentation->fresh()->title)->toBe('Zmieniony tytuł')->and(DB::table('iso_presentation_slides')->where('iso_presentation_id', $presentation->id)->count())->toBe(2);
    // Bypass only the per-minute HTTP limiter after testing three writes.
    $this->travel(61)->seconds();
    $this->mock(PresentationRenderer::class, fn ($mock) => $mock->shouldReceive('render')->once()->andReturn([$bytes]));
    $this->put(route('audit-types.presentations.update', [$type, $presentation]), ['title' => 'Podmieniona', 'presentation_file' => presentationFixture()])->assertRedirect();
    expect($presentation->fresh()->slide_count)->toBe(1)->and(DB::table('iso_presentation_slides')->where('iso_presentation_id', $presentation->id)->count())->toBe(1);
    $this->delete(route('audit-types.presentations.destroy', [$type, $presentation]))->assertRedirect();
    expect(IsoPresentation::find($presentation->id))->toBeNull()->and(DB::table('iso_presentation_slides')->where('iso_presentation_id', $presentation->id)->count())->toBe(0);
});

test('presentation validation rejects external links XML entities active content and too many slides', function () {
    $renderer = app(PresentationRenderer::class);
    $valid = presentationFixture();
    expect($renderer->validatePptx($valid->getRealPath()))->toBe(1);
    foreach ([
        ['ppt/slides/_rels/slide1.xml.rels' => '<Relationships><Relationship TargetMode="External" Target="http://169.254.169.254/latest/meta-data/"/></Relationships>'],
        ['ppt/slides/slide1.xml' => '<!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/passwd">]><a>&x;</a>'],
        ['ppt/vbaProject.bin' => 'macro'],
        ['ppt/slides/_rels/slide1.xml.rels' => '<Relationships><Relationship Target="../../../etc/passwd"/></Relationships>'],
        ['../escape.xml' => '<a/>'],
    ] as $extra) {
        $invalid = presentationFixture($extra);
        expect(fn () => $renderer->validatePptx($invalid->getRealPath()))->toThrow(ValidationException::class);
    }
    config(['presentations.max_slides' => 1]);
    $tooMany = presentationFixture(['ppt/slides/slide2.xml' => '<slide/>']);
    expect(fn () => $renderer->validatePptx($tooMany->getRealPath()))->toThrow(ValidationException::class);
});

test('missing presentation converter returns actionable validation error', function () {
    config(['presentations.office_binary' => '/missing/office']);
    $upload = presentationFixture();
    expect(fn () => app(PresentationRenderer::class)->render($upload->getRealPath()))->toThrow(ValidationException::class);
});

test('real server converter renders a PowerPoint to an image and cleans scratch files', function () {
    if (! is_executable(config('presentations.office_binary')) || ! is_executable(config('presentations.raster_binary'))) {
        $this->markTestSkipped('LibreOffice/Poppler not installed in this local runtime; exercised in CI.');
    }
    $upload = presentationFixture();
    $slides = app(PresentationRenderer::class)->render($upload->getRealPath());
    expect($slides)->toHaveCount(1)->and(getimagesizefromstring($slides[0])['mime'])->toBe('image/jpeg');
    expect(File::directories(storage_path('app/private/presentation-conversion')))->toBe([]);
});
