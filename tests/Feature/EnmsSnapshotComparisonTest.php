<?php

use App\Models\IsoSystemReview;
use App\Services\Enms\LeadershipDocuments;
use App\Services\Enms\Workspace;
use Illuminate\Support\Facades\DB;

/** Emulate MySQL JSON storage, which does not preserve object member order. */
function enmsReorderStoredJson(Workspace $workspace): void
{
    $reorder = function ($value) use (&$reorder) {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            krsort($value, SORT_STRING);
        }

        return array_map($reorder, $value);
    };
    $ids = array_keys($workspace->records());
    foreach (DB::table('enms_record_versions')->whereIn('record_id', $ids)->get(['id', 'payload']) as $row) {
        DB::table('enms_record_versions')->where('id', $row->id)->update([
            'payload' => json_encode($reorder(json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR),
        ]);
    }
}

test('enms client can submit eight unchanged source citations after database JSON key reordering', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $cw = Workspace::for($client, $audit, $profile, true);
    foreach ($s->library->requirements() as $id => $requirement) {
        if ($requirement['normative']) {
            $s->saveDeclaration($cw, $cw->revision(), $s->sources($cw)['hash'], $id, [
                'stan' => $requirement['kod'] === 'a' ? 'czesciowo' : 'niema',
                'plan' => ['opis' => 'Uzupełnienie wdrożenia', 'termin' => '2026-12-31'],
            ]);
        }
    }
    for ($i = 0; $i < 3; $i++) {
        enmsEvidence($cw);
    }
    enmsReorderStoredJson($cw);
    $before = $cw->records();
    $source = $s->sources($cw);
    $declaration = $s->one($cw, 'declaration', 'requirement_id', $s->library->id('requirement', 'a'));
    expect($declaration['payload']['citations']['zakresOrg'])->not->toBe($source['citations']['ZAKRES'])
        ->and($declaration['payload']['citations']['zakresOrg']['source_version'])->toBe(1)
        ->and($source['citations']['ZAKRES']['approved'])->toBeFalse()
        ->and(collect($cw->records('declaration'))->sum(fn ($r) => count($r['payload']['citations'])))->toBe(8)
        ->and($s->readiness($cw)['missing'])->toBe([])
        ->and($cw->records())->toBe($before);
    if (getenv('ENMS_CITATIONS_BROWSER_QA')) {
        enmsBrowserExport('enms-browser-citations', [$audit, $client, $staff, $profile]);
    }
    $this->actingAs($client)->get(route('client.audits.enms.show', [$audit, $profile, 'tab' => 'spojnosc']))
        ->assertOk()->assertDontSee('źródło pola');
    $this->post(route('client.audits.enms.update', [$audit, $profile]), [
        'revision' => $cw->revision(), 'source_hash' => $source['hash'], 'operation' => 'submission',
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($s->status(Workspace::for($client, $audit, $profile, true))['submitted'])->toBeTrue()
        ->and($cw->records('declaration'))->toHaveCount(13)
        ->and($cw->records('evidence'))->toHaveCount(3);
});

test('enms real source changes remain stale until the declaration is saved again', function (string $change) {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $id = $s->library->id('requirement', 'a');
    $data = ['stan' => 'niema', 'plan' => ['opis' => 'Uzupełnienie zakresu', 'termin' => '2026-12-31']];
    $s->saveDeclaration($w, $w->revision(), $s->sources($w)['hash'], $id, $data);
    enmsReorderStoredJson($w);
    $warnings = fn () => array_values(array_filter($s->readiness($w)['missing'], fn ($message) => str_contains($message, 'źródło pola')));
    expect($warnings())->toBe([]);
    $scope = IsoSystemReview::where('audit_id', $audit->id)->where('section', '4-3')->firstOrFail();
    if ($change === 'version') {
        $scope->increment('lock_version');
    } else {
        $scope->update(['answers' => ['ZAKRES' => ['opis' => 'Zmieniony zakres']]]);
    }
    expect($warnings())->toHaveCount(1);
    $s->saveDeclaration($w, $w->revision(), $s->sources($w)['hash'], $id, $data);
    enmsReorderStoredJson($w);
    expect($warnings())->toBe([]);
})->with(['version', 'content']);

test('enms reordered closure basis stays current in the register and document limitations until scope changes', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $e = enmsEvidence($w);
    $cid = $s->library->id('criterion', 'KRY-a1');
    enmsSave($w, 'verification', ['evidence_id' => $e['id'], 'dostepnosc' => 'okazany', 'wiarygodnosc' => 'potwierdzona', 'zakresInformacji' => 'Zakres', 'sposob' => 'wgląd w dokument']);
    enmsSave($w, 'coverage', ['evidence_id' => $e['id'], 'criterion_id' => $cid, 'zakres' => 'pelne', 'coPotwierdza' => 'Zakres']);
    enmsSave($w, 'closure', ['criterion_id' => $cid, 'stan' => 'zamkniete', 'uzasadnienie' => 'Sprawdzono zakres']);
    enmsReorderStoredJson($w);
    $stale = fn () => array_values(array_filter(app(LeadershipDocuments::class)->limitations($w), fn ($message) => str_contains($message, 'podstawa zamknięcia zmieniła się')));
    expect($stale())->toBe([]);
    $url = route('audits.enms.show', [$audit, $profile, 'tab' => 'ocena', 'register' => 'closure']);
    $this->actingAs($staff)->get($url)->assertOk()->assertSee('Zamknięcie aktualne.')->assertDontSee('Podstawa zmieniła się');
    IsoSystemReview::where('audit_id', $audit->id)->where('section', '4-3')->increment('lock_version');
    expect($stale())->toHaveCount(1);
    $this->get($url)->assertOk()->assertSee('Podstawa zmieniła się');
});
