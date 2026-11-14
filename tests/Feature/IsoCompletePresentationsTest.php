<?php

use App\Models\Audit;
use App\Models\AuditType;
use App\Models\Company;
use App\Models\IsoPresentation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function completePresentationUser(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(Role::findOrCreate($role));

    return $user;
}

test('every ISO node has a complete presentation with the exact shipped slide bytes', function () {
    $expected = collect(config('iso50001.chapters'))->flatMap(fn ($chapter) => [$chapter['id'], ...collect($chapter['items'] ?? [])->pluck('id')->all()])->sort()->values()->all();
    expect(IsoPresentation::pluck('section_id')->sort()->values()->all())->toBe($expected);
    $directory = resource_path('training/iso50001-complete');
    $catalogue = json_decode(file_get_contents($directory.'/catalogue.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($catalogue)->toHaveCount(44);
    foreach ($catalogue as $entry) {
        $deck = IsoPresentation::where('section_id', $entry['section_id'])->firstOrFail();
        expect($deck->title)->toBe($entry['title'])->and($deck->slide_count)->toBe($entry['slide_count']);
        $slides = DB::table('iso_presentation_slides')->where('iso_presentation_id', $deck->id)->orderBy('position')->get();
        expect($slides)->toHaveCount($entry['slide_count']);
        foreach ($slides as $i => $slide) {
            $bytes = base64_decode($slide->image_data, true);
            $size = getimagesizefromstring($bytes);
            expect((int) $slide->position)->toBe($i + 1)
                ->and([$size[0], $size[1], $size[2]])->toBe([1280, 720, IMAGETYPE_PNG])
                ->and(hash('sha256', $bytes))->toBe(hash_file('sha256', $directory.'/sections/'.$entry['section_id'].'/slide-'.($i + 1).'.png'));
        }
    }
});

test('all chapter and clause cards appear in the correct panel for each full page', function (string $view) {
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $user = completePresentationUser($view === 'client' ? 'client_user' : 'superadmin');
    if ($view === 'catalogue') {
        $url = route('audit-types.show', ['auditType' => $type, 'section' => 'planning']);
    } else {
        $company = Company::create(['name' => 'Prezentacje całego ISO']);
        $user->companies()->attach($company);
        $audit = Audit::create(['company_id' => $company->id, 'number' => 'PRES/COMPLETE', 'title' => 'ISO', 'status' => 'draft']);
        $audit->surveys()->create(['audit_type_id' => $type->id, 'title' => 'ISO', 'status' => 'draft']);
        $url = route($view === 'client' ? 'client.audits.show' : 'audits.show', ['audit' => $audit, 'tab' => 'iso50001', 'section' => 'planning']);
    }
    $response = $this->actingAs($user)->get($url)->assertOk();
    $dom = new DOMDocument;
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    foreach (IsoPresentation::all() as $deck) {
        $cards = $xpath->query('//section[@data-iso-panel="'.$deck->section_id.'"]//article[@data-presentation-title]');
        expect($cards->length)->toBe(1, $view.': '.$deck->section_id);
        expect($cards->item(0)->getAttribute('data-presentation-title'))->toBe($deck->title)
            ->and($cards->item(0)->getAttribute('data-slide-count'))->toBe((string) $deck->slide_count);
        $slideUrl = $view === 'catalogue' ? route('audit-types.presentations.slide', [$type, $deck, 1])
            : route($view === 'client' ? 'client.audits.presentations.slide' : 'audits.presentations.slide', [$audit, $deck, 1]);
        expect($xpath->query('.//img', $cards->item(0))->item(0)->getAttribute('src'))->toBe($slideUrl);
    }
    expect($xpath->query('//dialog[@data-slide-dialog]')->length)->toBe(1);
    $response->assertSee('Slajdy do sekcji 6 Planowanie')->assertDontSee('Slajdy do sekcji planning');
    if ($view !== 'catalogue') {
        $response->assertDontSee('Dodaj prezentację PowerPoint')->assertDontSee('Edytuj lub podmień');
    }
})->with(['catalogue', 'auditor', 'client']);

test('new chapter and clause slides keep audit ownership and ISO assignment boundaries', function (string $section) {
    $type = AuditType::firstOrCreate(['slug' => 'iso50001'], ['name' => 'ISO 50001']);
    $company = Company::create(['name' => 'Dostęp do materiałów']);
    $client = completePresentationUser('client_user');
    $client->companies()->attach($company);
    $audit = Audit::create(['company_id' => $company->id, 'number' => 'PRES/ACCESS', 'title' => 'ISO', 'status' => 'draft']);
    $deck = IsoPresentation::where('section_id', $section)->firstOrFail();
    $url = route('client.audits.presentations.slide', [$audit, $deck, 1]);
    $this->actingAs($client)->get($url)->assertNotFound();
    $audit->surveys()->create(['audit_type_id' => $type->id, 'title' => 'ISO', 'status' => 'draft']);
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('client.audits.presentations.slide', [$audit, $deck, $deck->slide_count + 1]))->assertNotFound();
    $this->get(route('audit-types.presentations.slide', [$type, $deck, 1]))->assertForbidden();
    $this->actingAs(completePresentationUser('client_user'))->get($url)->assertNotFound();
})->with(['intro', 'planning', '6-4', '9-1', '9-1-1', '11-4']);

test('complete training migration preserves existing decks and administrator edits on rerun and rollback', function () {
    $deck = IsoPresentation::where('section_id', '6-4')->firstOrFail();
    $deck->update(['title' => 'Tytuł administratora']);
    $custom = $deck->replicate();
    $custom->original_filename = 'wlasny-material.pptx';
    $custom->save();
    $before = IsoPresentation::orderBy('id')->get()->toArray();
    $count = DB::table('iso_presentation_slides')->count();
    $migration = require database_path('migrations/2026_11_14_120000_add_remaining_iso_training_presentations.php');
    $migration->up();
    $migration->down();
    expect(IsoPresentation::orderBy('id')->get()->toArray())->toBe($before)
        ->and(DB::table('iso_presentation_slides')->count())->toBe($count);
});
