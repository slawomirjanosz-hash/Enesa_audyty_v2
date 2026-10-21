<?php

use App\Models\IsoFactorReview;
use App\Models\IsoSectionDocument;
use App\Services\IsoFactorConclusions;
use App\Services\IsoFactorDocument;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoHtmlQuestionnaire;
use App\Services\IsoHtmlState;
use App\Services\IsoPlantQuestionnaire;
use App\Services\IsoQuestionnaireWorkbook;

function source21Review($audit, $profile, $client, $staff): IsoFactorReview
{
    $q = app(IsoFactorQuestionnaire::class);
    $answers = $q->normalize(factorInput($profile)['answers'], $q->facts($profile), true);

    return IsoFactorReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'status' => 'approved', 'lock_version' => 1,
        'source_profile_id' => $profile->id, 'source_hash' => $q->hash($profile), 'answers' => $answers, 'basis' => array_column($q->factors($q->facts($profile), $answers), 'basis', 'kod'),
        'client_approval' => ['user_id' => $client->id, 'name' => $client->name, 'at' => now()->toIso8601String()],
        'auditor_approval' => ['user_id' => $staff->id, 'name' => $staff->name, 'at' => now()->toIso8601String()],
        'swot' => array_replace(factorSwot(), ['strengths' => 'PRIVATE-SOURCE21-DRAFT']), 'issuer' => ['name' => 'ENESA']]);
}

test('source21 library matches every supplied HTML condition and wording', function () {
    preg_match('/var SCHEMA = (.*);\r?\n/', app(IsoHtmlQuestionnaire::class)->document('4-1'), $match);
    $source = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    expect(app(IsoFactorQuestionnaire::class)->schema())->toBe(array_intersect_key($source, array_flip(['meta', 'sekcje', 'czynniki'])));
    expect(app(IsoHtmlQuestionnaire::class)->document('4-1'))->toContain('WERSJA_DANYCH = "4.1/3"', 'Wersja 2.6');
});

test('source21 persists editable effects evidence stable custom ids and shared conclusions', function () {
    [$audit,$client,$staff,$profile] = factorFixture();
    $input = factorInput($profile);
    $state = app(IsoHtmlState::class)->fromAnswers('4-1', $input['answers']);
    $state['DEC']['KTX-WT-01']['istotnosc'] = 'W';
    $state['DEC']['KTX-WT-01']['skutek'] = 'Skutek właściwy dla zakładu';
    $state['DEC']['KTX-WT-01']['podstawa'] = ['rodzaj' => 'pomiar', 'zrodlo' => 'Raport P-4', 'okres' => '2025'];
    $state['WLASNE'] = [['id' => 'WL-3', 'tresc' => 'Czynnik własny', 'wymiar' => 'wewn. techniczny', 'wplyw' => '+', 'skutek' => 'Skutek własny', 'podstawa' => ['rodzaj' => 'dokument', 'zrodlo' => 'R-2', 'okres' => '2025']]];
    $state['WNIOSKI'] = [['id' => 'WN-4', 'czynniki' => ['KTX-WT-01', 'WL-3'], 'znaczenie' => 'Pomiar', 'decyzja' => 'Rozbudować', 'dzialanie' => 'Zamontować', 'sprawdzenie' => 'Raport roczny']];
    $state['ID_SEQ'] = ['WL' => 3, 'WN' => 4];
    $payload = ['operation' => 'submit', 'lock_version' => 0, 'source_hash' => $input['source_hash'], 'html_state' => json_encode($state)];
    $url = route('client.audits.factors.update', [$audit, $profile]);
    $this->actingAs($client)->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $review = IsoFactorReview::firstOrFail();
    expect($review->answers['factors']['KTX-WT-01']['podstawa']['zrodlo'])->toBe('Raport P-4')
        ->and($review->answers['factors']['KTX-WT-01']['skutek'])->toBe('Skutek właściwy dla zakładu')
        ->and($review->answers['custom'][0]['id'])->toBe('WL-3')
        ->and($review->answers['_html']['ID_SEQ']['WN'])->toBe(4);
    $rows = app(IsoFactorConclusions::class)->registry($review);
    expect($rows)->toHaveCount(1)->and($rows[0]['czynniki'])->toBe(['KTX-WT-01', 'WL-3'])->and($rows[0]['scope'])->toBe('client');
    $this->get(route('client.audits.factors.show', [$audit, $profile]))->assertOk()->assertSee('Raport P-4')->assertSee('WN-4');
    unset($state['DEC']['KTX-WT-01']['podstawa']);
    $this->actingAs($staff)->post(route('audits.factors.update', [$audit, $profile]), array_replace($payload, ['operation' => 'save', 'lock_version' => 1, 'html_state' => json_encode($state)]))->assertRedirect();
    expect($review->fresh()->client_approval)->toBeNull();
});

test('source21 high importance cannot be approved without evidence', function () {
    [$audit,$client,$staff,$profile] = factorFixture();
    $input = factorInput($profile);
    $input['answers']['factors']['KTX-WT-01'] = array_replace($input['answers']['factors']['KTX-WT-01'], ['istotnosc' => 'W', 'znaczenie' => 'Z', 'decyzja_w' => 'D', 'dzialanie' => 'A', 'sprawdzenie' => 'S']);
    $this->actingAs($client)->post(route('client.audits.factors.update', [$audit, $profile]), $input)->assertSessionHasErrors('answers.factors.KTX-WT-01.podstawa');
});

test('source21 print HTML strings cannot receive duplicate platform bootstrap scripts', function () {
    $frame = app(IsoHtmlQuestionnaire::class)->frame('4-1', ['channel' => 'test', 'section' => '4-1']);
    expect(substr_count($frame, 'var ENESA_HOST='))->toBe(1)
        ->and(substr_count($frame, 'Transport for the original HTML renderer'))->toBe(1)
        ->and($frame)->toContain('<head><meta charset="utf-8">', 'WERSJA_DANYCH = "4.1/3"');
});

test('source21 restores omitted profile fields and exports their stable codes', function () {
    [$audit,$client,$staff,$profile] = factorFixture();
    $plant = app(IsoPlantQuestionnaire::class);
    $definition = $profile->definition;
    foreach ($definition['groups'] as &$group) {
        $group['questions'] = array_values(array_filter($group['questions'], fn ($q) => empty($q['source_added'])));
    }
    unset($group);
    $answers = $profile->answers;
    $answers['_html']['ODP'] = ['FAKT_PRODUKCJA' => '24 000,5', 'FAKT_PRODUKCJA_JEDN' => 't', 'FAKT_PRZYCHOD' => '340,5', 'FAKT_UDZIAL_ENERGII' => '2,5'];
    $facts = $plant->facts($definition, $answers);
    expect($facts['FAKT_PRODUKCJA'])->toBe(24000.5)->and($facts['FAKT_PRZYCHOD'])->toBe(340.5);
    $state = app(IsoHtmlState::class)->fromAnswers('intro', $answers);
    $restored = app(IsoHtmlState::class)->toAnswers('intro', $state, [], $answers);
    $normalized = $plant->normalize($restored, $definition, $answers, $client->id, false);
    expect($normalized['FAKT_PRODUKCJA']['value'])->toBe('24000.5')->and($normalized['FAKT_PRODUKCJA_JEDN']['value'])->toBe('t');
    $profile->definition = $definition;
    $profile->answers = $normalized;
    $book = app(IsoQuestionnaireWorkbook::class)->export($profile, 'plant');
    $sheet = $book->getSheetByName('Pytania');
    expect($sheet->getCell('C'.workbookRow($sheet, 'FAKT_PRODUKCJA'))->getValue())->toBe(24000.5);
    $review = new IsoFactorReview(['answers' => factorInput($profile)['answers']]);
    $html = app(IsoFactorDocument::class)->body($review, $profile);
    expect($html)->toContain('24 000,5', '340,5 mln zł')->not->toContain('2 025');
    $profile->client_approval = ['name' => 'Klient', 'at' => now()->toIso8601String()];
    $profile->auditor_approval = ['name' => 'Audytor', 'at' => now()->toIso8601String()];
    $html = view('audits.plant-profile.pdf', ['profile' => $profile, 'questionnaire' => $plant])->render();
    expect($html)->toContain('Wielkość produkcji w roku odniesienia', '24000.5');
});

test('source21 published body and PDF remain available unchanged through later corrections', function () {
    [$audit,$client,$staff,$profile] = factorFixture();
    $review = source21Review($audit, $profile, $client, $staff);
    $this->actingAs($client)->get(route('client.audits.factors.show', [$audit, $profile]))->assertOk()->assertDontSee('PRIVATE-SOURCE21-DRAFT');
    $this->get(route('client.audits.factors.document', [$audit, $profile]))->assertForbidden();
    $this->actingAs($staff)->post(route('audits.factors.pdf', [$audit, $profile]), ['lock_version' => 1])->assertSessionHasNoErrors()->assertRedirect();
    $edition = IsoSectionDocument::findOrFail($review->fresh()->document_id);
    $body = $edition->generation_metadata['html'];
    $bytes = $edition->contents();
    expect($body)->toContain('PRIVATE-SOURCE21-DRAFT', '10. Zatwierdzenie', 'Opublikowano wydanie', '72', 'WN-legacy-1')->and($edition->version_number)->toBe('1');
    $url = route('client.audits.factors.document', [$audit, $profile]);
    $this->actingAs($client)->get($url)->assertOk()->assertSee('PRIVATE-SOURCE21-DRAFT');
    $this->get($url.'?format=pdf')->assertContent($bytes);
    $review->update(['answers' => array_replace_recursive($review->answers, ['climate_reason' => 'Nowe uzasadnienie po publikacji']), 'document_id' => null, 'client_approval' => null, 'auditor_approval' => null, 'status' => 'editing']);
    $profile->increment('lock_version');
    $this->get($url)->assertOk()->assertSee('PRIVATE-SOURCE21-DRAFT')->assertDontSee('Nowe uzasadnienie po publikacji');
    $this->get($url.'?format=pdf')->assertContent($bytes);
    expect($edition->fresh()->generation_metadata['html'])->toBe($body)->and($edition->fresh()->contents())->toBe($bytes)->and(IsoFactorReview::count())->toBe(1);
    [$otherAudit] = plantFixture();
    $this->get(route('client.audits.factors.document', [$otherAudit, $profile]))->assertNotFound();
});

test('source21 publication rejects partial energy and conflicting conclusions atomically', function () {
    [$audit,$client,$staff,$profile] = factorFixture();
    $review = source21Review($audit, $profile, $client, $staff);
    $rows = app(IsoFactorConclusions::class)->registry($review);
    $rows[] = array_replace($rows[0], ['id' => 'WN-other', 'decyzja' => 'Sprzeczna decyzja']);
    $review->update(['swot' => array_replace($review->swot, ['wnioski' => $rows])]);
    $this->actingAs($staff)->post(route('audits.factors.pdf', [$audit, $profile]), ['lock_version' => 1])->assertSessionHasErrors('swot.wnioski');
    expect(IsoSectionDocument::count())->toBe(0);
    $profile->update(['answers' => array_replace($profile->answers, ['FAKT_NOSNIKI' => ['value' => [['c0' => 'LPG', 'c1' => '', 'c2' => 'MWh']]]])]);
    $review->update(['swot' => factorSwot(), 'source_hash' => app(IsoFactorQuestionnaire::class)->hash($profile->fresh())]);
    $this->post(route('audits.factors.pdf', [$audit, $profile]), ['lock_version' => 1])->assertSessionHasErrors('document');
    expect(IsoSectionDocument::count())->toBe(0);
});

test('source21 retains old PDF editions and continues numbering in the existing documentation', function () {
    [$audit,$client,$staff,$profile] = factorFixture();
    $review = source21Review($audit, $profile, $client, $staff);
    $legacy = IsoSectionDocument::create(['audit_id' => $audit->id, 'section_id' => '4-1', 'scope' => 'client', 'title' => 'Dotychczasowy raport', 'description' => 'Wydany raport', 'version_number' => '1.0', 'original_filename' => 'legacy.pdf', 'stored_path' => 'legacy.pdf', 'mime_type' => 'application/pdf', 'size' => 11, 'content_base64' => base64_encode('%PDF-legacy'), 'uploaded_by' => $staff->id]);
    $review->update(['document_id' => $legacy->id]);
    $this->actingAs($client)->get(route('client.audits.factors.document', [$audit, $profile]))->assertContent('%PDF-legacy');
    $swot = factorSwot();
    $swot['threats'] = 'Nowa analiza konsultanta';
    $payload = ['operation' => 'save_swot', 'lock_version' => 1, 'source_hash' => $review->source_hash, 'swot' => $swot];
    $this->actingAs($staff)->post(route('audits.factors.update', [$audit, $profile]), $payload)->assertSessionHasNoErrors()->assertRedirect();
    expect($review->fresh()->client_approval)->not->toBeNull()->and($legacy->fresh()->generation_metadata['review_id'])->toBe($review->id);
    $this->actingAs($client)->get(route('client.audits.factors.document', [$audit, $profile]))->assertContent('%PDF-legacy');
    $payload['operation'] = 'approve';
    $payload['lock_version'] = 2;
    $payload['note'] = 'Sprawdzono nową analizę';
    $this->actingAs($staff)->post(route('audits.factors.update', [$audit, $profile]), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('audits.factors.pdf', [$audit, $profile]), ['lock_version' => 3])->assertSessionHasNoErrors()->assertRedirect();
    expect(IsoSectionDocument::findOrFail($review->fresh()->document_id)->version_number)->toBe('2')->and($legacy->fresh()->contents())->toBe('%PDF-legacy');
});
