<?php

use App\Models\IsoSystemReview;
use App\Services\Enms\LeadershipProgress;
use App\Services\Enms\Rollout;
use App\Services\QuestionnairePendingActions;
use Illuminate\Support\Facades\DB;

test('enms pilot entry is visible for both roles only in an enabled audit', function () {
    [$audit, $client, $staff, $profile] = enmsFixture();
    foreach ([[$staff, 'audits.'], [$client, 'client.audits.']] as [$user, $prefix]) {
        $response = $this->actingAs($user)->get(route($prefix.'show', [$audit, 'tab' => 'iso50001', 'section' => '5-1']))
            ->assertOk()->assertSee('Otwórz ankietę 5.1')->assertSee(route($prefix.'enms.show', [$audit, $profile]), false)
            ->assertDontSee(route($prefix.'system.show', [$audit, $profile, '5-1']), false);
        expect(substr_count($response->getContent(), '>Otwórz ankietę 5.1</a>'))->toBe(1);
        $this->get(route($prefix.'enms.show', [$audit, $profile]))->assertOk()->assertDontSee('wersja 0.4 · pilotaż')->assertDontSee('wersja 0.3');
        $this->get(route($prefix.'enms.show', [$audit, $profile, 'tab' => 'historia']))->assertOk()
            ->assertDontSee('wersja 0.3')->assertDontSee(route($prefix.'system.show', [$audit, $profile, '5-1']), false);
        $this->get(route($prefix.'system.show', [$audit, $profile, '5-1']))->assertRedirect(route($prefix.'enms.show', [$audit, $profile]));
    }
    expect(DB::table('enms_workspaces')->count())->toBe(0);
    config(['enms.audit_ids' => []]);
    foreach ([[$staff, 'audits.'], [$client, 'client.audits.']] as [$user, $prefix]) {
        $this->actingAs($user)->get(route($prefix.'show', [$audit, 'tab' => 'iso50001', 'section' => '5-1']))
            ->assertOk()->assertDontSee('wersja 0.4')->assertSee('Otwórz ankietę 5.1');
        $this->get(route($prefix.'system.show', [$audit, $profile, '5-1']))->assertOk();
    }
});

test('enms disabled audit rejects every direct endpoint for both roles without writes', function () {
    [$audit, $client, $staff, $profile] = enmsFixture();
    config(['enms.audit_ids' => []]);
    expect(Rollout::enabled($audit->id))->toBeFalse();
    foreach ([[$staff, 'audits.enms.'], [$client, 'client.audits.enms.']] as [$user, $prefix]) {
        $this->actingAs($user);
        foreach (['show', 'excel', 'document'] as $action) {
            $params = [$audit, $profile];
            if ($action === 'document') {
                $params[] = '00000000-0000-4000-8000-000000000000';
            }
            $this->get(route($prefix.$action, $params))->assertNotFound();
        }
        foreach (['update', 'draft', 'excel-import'] as $action) {
            $this->post(route($prefix.$action, [$audit, $profile]), [])->assertNotFound();
        }
    }
    expect(DB::table('enms_workspaces')->count())->toBe(0)
        ->and(DB::table('enms_records')->count())->toBe(0);
});

test('enms pilot begins empty and preserves approved legacy answers and documents after new saves', function () {
    [$audit, $client, $staff, $profile, $w, $service] = enmsFixture();
    $legacy = IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'source_profile_id' => $profile->id, 'section' => '5-1', 'source_hash' => 'legacy-source', 'status' => 'approved', 'lock_version' => 7,
        'answers' => ['legacy' => ['value' => 'Historyczna odpowiedź 0.3']], 'client_approval' => ['user_id' => $client->id], 'auditor_approval' => ['user_id' => $staff->id]]);
    $before = $legacy->fresh()->getRawOriginal();
    $profileBefore = $profile->fresh()->getRawOriginal();
    $documents = DB::table('iso_section_documents')->get()->toJson();
    $this->actingAs($client)->get(route('client.audits.enms.show', [$audit, $profile, 'tab' => 'obowiazki']))
        ->assertOk()->assertDontSee('Historyczna odpowiedź 0.3');
    expect($w->records())->toBe([])->and($service->status($w)['submitted'])->toBeFalse();
    $this->post(route('client.audits.enms.update', [$audit, $profile]), [
        'revision' => 0, 'source_hash' => $service->sources($w)['hash'], 'operation' => 'declaration',
        'requirement_id' => $service->library->id('requirement', 'a'),
        'payload' => ['stan' => 'niema', 'plan' => ['opis' => 'Nowy plan pilotażowy', 'termin' => '2026-12-31']],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('client.audits.enms.show', [$audit, $profile, 'tab' => 'obowiazki']))->assertOk()->assertSee('Nowy plan pilotażowy');
    expect($legacy->fresh()->getRawOriginal())->toBe($before)
        ->and($profile->fresh()->getRawOriginal())->toBe($profileBefore)
        ->and(DB::table('iso_section_documents')->get()->toJson())->toBe($documents)
        ->and($w->records('submission'))->toBe([]);
});

test('enms disabling the pilot hides its progress and notifications while keeping records', function () {
    [$audit, $client, $staff, $profile, $w, $service] = enmsFixture();
    $service->saveDeclaration($w, 0, $service->sources($w)['hash'], $service->library->id('requirement', 'a'), ['stan' => 'niema']);
    enmsSave($w, 'question', ['requirement_id' => $service->library->id('requirement', 'a'), 'pytanie' => 'Kto określa zakres?']);
    $this->actingAs($client);
    expect(app(LeadershipProgress::class)->forAudits([$audit->id]))->not->toBeEmpty()
        ->and(app(QuestionnairePendingActions::class)->enmsForClient(collect([$audit])))->not->toBeEmpty();
    $before = $w->records();
    config(['enms.audit_ids' => []]);
    expect(app(LeadershipProgress::class)->forAudits([$audit->id]))->toBe([])
        ->and(app(QuestionnairePendingActions::class)->enmsForClient(collect([$audit])))->toBe([]);
    $this->actingAs($staff)->get(route('audits.system.show', [$audit, $profile, '6-2']))->assertOk()->assertDontSee('Wspólne zadania EnMS');
    expect($w->records())->toBe($before);
});
