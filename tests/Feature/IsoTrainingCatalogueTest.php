<?php

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\IsoPresentation;
use App\Models\IsoTrainingVideo;
use Illuminate\Support\Facades\DB;

test('ISO training catalogue maps every section and media to scoped viewers for all roles', function (string $role) {
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $staff = presentationUser('superadmin');
    $client = presentationUser('client_user');
    $company = Company::create(['name' => 'Materiały szkoleniowe QA']);
    $client->companies()->attach($company);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'QA/TRAINING', 'title' => 'Materiały szkoleniowe', 'status' => 'draft']);
    $audit->surveys()->create(['audit_type_id' => $type->id, 'title' => 'ISO', 'status' => 'draft']);
    IsoTrainingVideo::create(['topic' => 'Szkolenie ogólne', 'description' => 'Materiał nieprzypisany do punktu', 'youtube_url' => 'https://youtu.be/General123']);
    IsoTrainingVideo::create(['section_id' => '4-1', 'topic' => 'Kontekst — film A', 'youtube_url' => 'https://youtu.be/Context001']);
    IsoTrainingVideo::create(['section_id' => '4-1', 'topic' => 'Kontekst — film B', 'youtube_url' => 'https://www.youtube.com/watch?v=Context002']);
    $custom = IsoPresentation::where('section_id', '4-1')->firstOrFail()->replicate();
    $custom->title = 'Kontekst — dodatkowa prezentacja';
    $custom->save();
    $slide = DB::table('iso_presentation_slides')->where('iso_presentation_id', IsoPresentation::where('section_id', '4-1')->first()->id)->where('position', 1)->value('image_data');
    DB::table('iso_presentation_slides')->insert(['iso_presentation_id' => $custom->id, 'position' => 1, 'image_data' => $slide]);
    $custom->update(['slide_count' => 1]);
    IsoPresentation::where('section_id', '11-4')->delete();
    $url = $role === 'catalogue' ? route('audit-types.show', [$type, 'section' => 'training'])
        : route($role === 'client' ? 'client.audits.show' : 'audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'training']);
    $response = $this->actingAs($role === 'client' ? $client : $staff)->get($url)->assertOk();
    $dom = new DOMDocument;
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    $sections = collect(config('iso50001.chapters'))->flatMap(fn ($chapter) => [$chapter, ...($chapter['items'] ?? [])]);
    expect($xpath->query('//tr[@data-training-section]')->length)->toBe($sections->count());
    foreach ($sections as $section) {
        $row = $xpath->query('//tr[@data-training-section="'.$section['id'].'"]')->item(0);
        expect($row)->not->toBeNull();
        $cells = $xpath->query('./td', $row);
        expect($cells->length)->toBe(4)->and(trim($cells->item(0)->textContent))->toBe($section['number'])
            ->and($cells->item(1)->textContent)->toContain($section['title'], $section['description']);
        foreach (IsoPresentation::where('section_id', $section['id'])->get() as $deck) {
            $slideUrl = $role === 'catalogue' ? route('audit-types.presentations.slide', [$type, $deck, 1])
                : route($role === 'client' ? 'client.audits.presentations.slide' : 'audits.presentations.slide', [$audit, $deck, 1]);
            expect($xpath->query('.//*[@data-slide-base="'.substr($slideUrl, 0, -1).'"]', $row)->length)->toBe(1);
        }
    }
    expect($xpath->query('//tr[@data-training-section="4-1"]//button[@data-video-preview]')->length)->toBe(2)
        ->and($xpath->query('//tr[@data-training-section="training"]//button[@data-video-preview]')->length)->toBe(1)
        ->and($xpath->query('//tr[@data-training-section="4-2"]//button[@data-video-preview]')->length)->toBe(0)
        ->and($xpath->query('//tr[@data-training-section="11-4"]')->item(0)->textContent)->toContain('Brak materiału')
        ->and($xpath->query('//dialog[@data-slide-dialog]')->length)->toBe(1)
        ->and($xpath->query('//dialog[@data-iso-video-modal]')->length)->toBe(1)
        ->and($xpath->query('//table[contains(@class,"iso-training-table")]//th[@data-sortable="false"]')->length)->toBe(2);
    if ($role !== 'catalogue') {
        expect($xpath->query('//button[@data-video-edit]')->length)->toBe(0);
        $response->assertDontSee('name="youtube_url"', false)->assertDontSee('Dodaj prezentację PowerPoint');
    } else {
        $response->assertSee('data-section="4-1"', false)->assertSee('Zarządzaj prezentacjami punktu 2')->assertSee('id="iso-video-section"', false);
    }
    if (getenv('ISO_TRAINING_BROWSER_QA') && $role === 'client') {
        enmsBrowserExport('iso-training-browser', [$audit, $client, $staff, (object) ['id' => $type->id]]);
    }
})->with(['client', 'auditor', 'catalogue']);

test('training media retain company ownership and client cannot manage the library', function () {
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $company = Company::create(['name' => 'Dostęp do szkoleń']);
    $client = presentationUser('client_user');
    $client->companies()->attach($company);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'QA/TRAINING/ACCESS', 'title' => 'ISO', 'status' => 'draft']);
    $audit->surveys()->create(['audit_type_id' => $type->id, 'title' => 'ISO', 'status' => 'draft']);
    $deck = IsoPresentation::where('section_id', '4-2')->firstOrFail();
    $video = IsoTrainingVideo::create(['section_id' => '4-2', 'topic' => 'Strony zainteresowane', 'youtube_url' => 'https://youtu.be/Training123']);
    $this->actingAs($client)->get(route('client.audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'training']))->assertOk()->assertSee('data-training-section="4-2"', false);
    $this->get(route('client.audits.presentations.slide', [$audit, $deck, 1]))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->post(route('audit-types.training-videos.store', $type), ['topic' => 'Zmiana', 'youtube_url' => 'https://youtu.be/Training123'])->assertForbidden();
    $this->put(route('audit-types.training-videos.update', [$type, $video]), ['section_id' => '4-3', 'topic' => 'Zmiana', 'youtube_url' => 'https://youtu.be/Training123'])->assertForbidden();
    $this->delete(route('audit-types.training-videos.destroy', [$type, $video]))->assertForbidden();
    expect($video->fresh()->section_id)->toBe('4-2');
    $outsider = presentationUser('client_user');
    $this->actingAs($outsider)->get(route('client.audits.show', [$audit, 'tab' => 'iso50001', 'section' => 'training']))->assertNotFound();
    $this->get(route('client.audits.presentations.slide', [$audit, $deck, 1]))->assertNotFound();
});
