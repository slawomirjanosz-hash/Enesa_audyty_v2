<?php

use App\Models\IsoFactorReview;
use App\Models\IsoSectionDocument;
use App\Models\IsoStakeholderReview;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoStakeholderQuestionnaire;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function stakeholderFixture(): array
{
    [$audit, $client, $staff, $profile] = factorFixture();
    $factorService = app(IsoFactorQuestionnaire::class);
    $answers = factorInput($profile)['answers'];
    $factors = IsoFactorReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'source_profile_id' => $profile->id, 'source_hash' => $factorService->hash($profile), 'status' => 'submitted', 'lock_version' => 1, 'answers' => $answers, 'client_approval' => ['user_id' => $client->id, 'name' => $client->name, 'at' => now()->toIso8601String()]]);
    $service = app(IsoStakeholderQuestionnaire::class);
    $input = ['FAKT_KLIMAT_STRONY' => 'nie', 'climate_reason' => 'Sprawdzono wymagania stron — nie stwierdzono wymagań klimatycznych.', 'parties' => []];
    foreach ($service->parties($profile, $factors) as $party) {
        if ($party['visible']) {
            $input['parties'][$party['kod']] = ['decyzja' => 'potwierdzona', 'wymagania' => $party['wymagania'], 'jak' => $party['jak']];
        }
    }

    return [$audit, $client, $staff, $profile, $factors, ['lock_version' => 0, 'source_hash' => $service->hash($profile, $factors), 'operation' => 'save', 'complete_form' => 1, 'answers' => $input]];
}

function stakeholderConsultantPayload($review, $profile, $factors): array
{
    $service = app(IsoStakeholderQuestionnaire::class);
    $decisions = [];
    foreach ($service->register($review->answers, $service->parties($profile, $factors), []) as $row) {
        $decisions[$row['kod']] = ['zgodnosc' => 'tak', 'reason' => 'Potwierdzono wymaganie w umowie organizacji.', 'legal_basis' => 'Umowa testowa nr 2026/1, § 5', 'owner' => 'Kierownik zakładu', 'deadline' => '2027-10-11'];
    }

    return ['operation' => 'consultant', 'lock_version' => $review->lock_version, 'source_hash' => $service->hash($profile, $factors), 'consultant' => $decisions, 'analysis' => ['summary' => 'Wymagania uwzględniono w planie działań i rejestrze ryzyk.', 'owner' => 'Pełnomocnik EnMS', 'reviewed_on' => '2026-10-01', 'next_review' => '2027-10-01']];
}

test('stakeholder library contains all 28 stable codes 21 rules and fixed compliance classifications', function () {
    $schema = app(IsoStakeholderQuestionnaire::class)->schema();
    expect($schema['strony'])->toHaveCount(28)->and($schema['mapowanie'])->toHaveCount(21)->and($schema['grupy'])->toHaveCount(4);
    expect(collect($schema['strony'])->where('zgodnosc', 'TAK'))->toHaveCount(8);
    expect(collect($schema['strony'])->where('zgodnosc', 'KANDYDAT'))->toHaveCount(4);
    $codes = array_column(app(IsoFactorQuestionnaire::class)->schema()['czynniki'], 'kod');
    foreach ($schema['mapowanie'] as $mapping) {
        expect($codes)->toContain($mapping['czynnik']);
    }
});

test('stakeholder proposals OR profile conditions and confirmed factors and merge every source', function () {
    [$audit, $client, $staff, $profile, $factors] = stakeholderFixture();
    $profile->answers = $profile->answers + ['FAKT_PODMIOT' => ['value' => 'publiczny'], 'FAKT_PRZETARGI' => ['value' => 'nie'], 'FAKT_ODBIORCY_CO2' => ['value' => 'tak'], 'FAKT_CIEPLO_PALIWO' => ['value' => ['gaz ziemny']], 'FAKT_KOTLOWNIA' => ['value' => 'wlasne']];
    $factors->source_hash = app(IsoFactorQuestionnaire::class)->hash($profile);
    $factors->answers = ['factors' => ['KTX-ZR-10' => ['decyzja' => 'potwierdzony'], 'KTX-ZM-03' => ['decyzja' => 'potwierdzony'], 'KTX-ZK-08' => ['decyzja' => 'potwierdzony']]];
    $service = app(IsoStakeholderQuestionnaire::class);
    $rows = collect($service->parties($profile, $factors))->keyBy('kod');
    expect($rows['STK-Z-07']['visible'])->toBeTrue()->and($rows['STK-Z-07']['mapping'])->toHaveCount(1);
    expect($rows['STK-Z-12']['mapping'])->toHaveCount(2)->and($rows['STK-Z-12']['wymagania'])->toContain('Odporność łańcucha dostaw');
    expect($rows['STK-Z-10']['visible'])->toBeTrue();
    expect($rows['STK-Z-16']['zgodnosc'])->toBe('TAK');
    $factors->client_approval = null;
    $rows = collect($service->parties($profile, $factors))->keyBy('kod');
    expect($rows['STK-Z-07']['visible'])->toBeFalse()->and($rows['STK-Z-12']['mapping'])->toHaveCount(0);
});

test('stakeholder workflow requires consultant decisions before client approval and publishes both documents', function () {
    [$audit, $client, $staff, $profile, $factors, $input] = stakeholderFixture();
    $clientUrl = route('client.audits.stakeholders.update', [$audit, $profile]);
    $staffUrl = route('audits.stakeholders.update', [$audit, $profile]);
    $this->actingAs($client)->get(route('client.audits.stakeholders.show', [$audit, $profile]))->assertOk()->assertSee('FAKT_KLIMAT_STRONY')->assertSee('STK-Z-15');
    $this->get(route('client.audits.show', [$audit, 'tab' => 'iso50001', 'section' => '4-2']))->assertOk()->assertSee('Otwórz ankietę 4.2');
    $this->post($clientUrl, $input)->assertSessionHasNoErrors();
    $review = IsoStakeholderReview::sole();
    expect($review->client_changes)->toBeNull();
    $input['lock_version'] = 1;
    $input['operation'] = 'submit';
    $this->post($clientUrl, $input)->assertSessionHasErrors('answers.consultant');
    $payload = stakeholderConsultantPayload($review, $profile, $factors);
    $this->post($clientUrl, $payload)->assertForbidden();
    $this->actingAs($staff)->post($staffUrl, $payload)->assertSessionHasNoErrors();
    $this->post($staffUrl, $payload)->assertStatus(409);
    $this->actingAs($client)->get(route('client.audits.stakeholders.show', [$audit, $profile]))->assertOk()->assertSee('Umowa testowa nr 2026/1')->assertDontSee('name="consultant[', false);
    $input['lock_version'] = 2;
    $this->post($clientUrl, $input)->assertSessionHasNoErrors();
    $this->actingAs($staff)->post($staffUrl, ['operation' => 'approve', 'lock_version' => 3, 'source_hash' => $input['source_hash']])->assertSessionHasNoErrors();
    $review->refresh();
    expect($review->status)->toBe('approved')->and($review->compliance_register)->not->toBeEmpty();
    foreach ($review->compliance_register as $row) {
        expect($row['zgodnosc'])->toBe('tak')->and($row['consultant']['legal_basis'])->not->toBeEmpty();
    }
    foreach ([0, 1] as $extract) {
        $this->post(route('audits.stakeholders.pdf', [$audit, $profile]), ['lock_version' => 4, 'extract' => $extract])->assertRedirect();
    }
    expect(IsoSectionDocument::where('section_id', '4-2')->count())->toBe(2);
    $this->actingAs($client)->post(route('client.audits.stakeholders.pdf', [$audit, $profile]), ['lock_version' => 4, 'preview' => 1, 'extract' => 1])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $input['operation'] = 'save';
    $input['lock_version'] = 4;
    $input['answers']['parties']['STK-Z-15']['wymagania'] = 'Zmienione wymaganie umowne';
    $this->actingAs($staff)->post($staffUrl, $input)->assertSessionHasNoErrors();
    expect($review->fresh()->status)->toBe('auditor_corrected')->and($review->fresh()->document_id)->toBeNull()->and($review->fresh()->compliance_document_id)->toBeNull()->and($review->fresh()->client_approval)->toBeNull();
    $this->actingAs($client)->get(route('client.audits.stakeholders.show', [$audit, $profile]))->assertSee('Zmienione wymaganie umowne')->assertSee('stakeholder-change');
    $input['lock_version'] = 5;
    $input['operation'] = 'submit';
    $this->post($clientUrl, $input)->assertSessionHasErrors('answers.consultant');
    expect(IsoStakeholderReview::count())->toBe(1)->and(DB::table('iso_stakeholder_events')->count())->toBe(5);
});

test('stakeholder validation isolates companies rejects stale sources and disallows invalid custom rows', function () {
    [$audit, $client, $staff, $profile, $factors, $input] = stakeholderFixture();
    $url = route('client.audits.stakeholders.update', [$audit, $profile]);
    $bad = $input;
    $bad['answers']['parties']['STK-W-01']['decyzja'] = 'odrzucona';
    $this->actingAs($client)->post($url, $bad)->assertSessionHasErrors('answers.parties.STK-W-01.powod');
    $bad = $input;
    $bad['answers']['custom'] = [['id' => 'bad', 'nazwa' => '', 'typ' => 'invalid']];
    $this->post($url, $bad)->assertSessionHasErrors('answers.custom.0.id');
    $factors->increment('lock_version');
    $this->post($url, $input)->assertStatus(409);
    [$otherAudit, $otherClient] = plantFixture();
    $this->get(route('client.audits.stakeholders.show', [$otherAudit, $profile]))->assertNotFound();
    $this->actingAs($otherClient)->get(route('client.audits.stakeholders.show', [$audit, $profile]))->assertNotFound();
    expect(IsoStakeholderReview::count())->toBe(0);
});

test('fixed compliance cannot be downgraded and approval requires verified legal bases', function () {
    [$audit, $client, $staff, $profile, $factors, $input] = stakeholderFixture();
    $this->actingAs($client)->post(route('client.audits.stakeholders.update', [$audit, $profile]), $input)->assertSessionHasNoErrors();
    $review = IsoStakeholderReview::sole();
    $payload = stakeholderConsultantPayload($review, $profile, $factors);
    $payload['consultant']['STK-Z-01']['zgodnosc'] = 'nie';
    $payload['consultant']['STK-Z-01']['legal_basis'] = '';
    $url = route('audits.stakeholders.update', [$audit, $profile]);
    $this->actingAs($staff)->post($url, $payload)->assertSessionHasNoErrors();
    expect($review->fresh()->consultant['STK-Z-01']['zgodnosc'])->toBe('tak');
    $input['lock_version'] = 2;
    $input['operation'] = 'submit';
    $this->actingAs($client)->post(route('client.audits.stakeholders.update', [$audit, $profile]), $input)->assertSessionHasNoErrors();
    $this->actingAs($staff)->post($url, ['operation' => 'approve', 'lock_version' => 3, 'source_hash' => $input['source_hash']])->assertSessionHasErrors('consultant');
    $this->post(route('audits.stakeholders.pdf', [$audit, $profile]), ['lock_version' => 3])->assertForbidden();
});

test('custom parties have stable identities and changes invalidate consultant decisions', function () {
    [$audit, $client, $staff, $profile, $factors, $input] = stakeholderFixture();
    $service = app(IsoStakeholderQuestionnaire::class);
    $id = (string) Str::uuid();
    $input['answers']['custom'] = [['id' => $id, 'nazwa' => 'Sąsiednia wspólnota', 'typ' => 'zewnętrzna', 'wymagania' => 'Ograniczenie hałasu instalacji', 'jak' => 'Plan modernizacji']];
    $clientUrl = route('client.audits.stakeholders.update', [$audit, $profile]);
    $staffUrl = route('audits.stakeholders.update', [$audit, $profile]);
    $this->actingAs($client)->post($clientUrl, $input)->assertSessionHasNoErrors();
    $review = IsoStakeholderReview::sole();
    $payload = stakeholderConsultantPayload($review, $profile, $factors);
    $payload['consultant']['CUSTOM-'.$id]['reason'] = '';
    $this->actingAs($staff)->post($staffUrl, $payload)->assertSessionHasErrors('consultant.CUSTOM-'.$id.'.reason');
    $payload['consultant']['CUSTOM-'.$id]['reason'] = 'Porozumienie z sąsiadami';
    $this->post($staffUrl, $payload)->assertSessionHasNoErrors();
    $payload['lock_version'] = 2;
    $this->post($staffUrl, $payload)->assertSessionHasNoErrors();
    expect($review->fresh()->lock_version)->toBe(2);
    $input['lock_version'] = 2;
    $input['answers']['custom'][0]['wymagania'] = 'Inne wymaganie sąsiadów';
    $this->actingAs($client)->post($clientUrl, $input)->assertSessionHasNoErrors();
    $rows = collect($service->register($review->fresh()->answers, $service->parties($profile, $factors), $review->fresh()->consultant))->keyBy('kod');
    expect($rows['CUSTOM-'.$id]['zgodnosc'])->toBe('pending');
    expect($review->fresh()->answers['custom'][0]['id'])->toBe($id);
});

test('source changes block approved stakeholder documents without removing previous files', function () {
    [$audit, $client, $staff, $profile, $factors, $input] = stakeholderFixture();
    $approval = ['user_id' => $client->id, 'name' => $client->name, 'at' => now()->toIso8601String()];
    $review = IsoStakeholderReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'source_profile_id' => $profile->id, 'source_hash' => $input['source_hash'], 'status' => 'approved', 'lock_version' => 1, 'answers' => $input['answers'], 'client_approval' => $approval, 'auditor_approval' => $approval]);
    $factors->increment('lock_version');
    $this->actingAs($client)->post(route('client.audits.stakeholders.pdf', [$audit, $profile]), ['lock_version' => 1])->assertStatus(409);
    $this->get(route('client.audits.stakeholders.show', [$audit, $profile]))->assertOk()->assertSee('Dotychczasowe zatwierdzenia są nieaktualne.');
    $this->actingAs($staff)->post(route('audits.stakeholders.update', [$audit, $profile]), ['operation' => 'approve', 'lock_version' => 1, 'source_hash' => app(IsoStakeholderQuestionnaire::class)->hash($profile, $factors->fresh())])->assertStatus(409);
    expect($review->fresh()->lock_version)->toBe(1);
});
