<?php

use App\Models\IsoSystemReview;
use App\Services\Enms\AuthorizedActions;
use App\Services\Enms\LeadershipDocuments;
use App\Services\Enms\LeadershipProgress;
use App\Services\Enms\LeadershipWorkbook;
use App\Services\Enms\LeadershipWorkflow;
use App\Services\Enms\TaskReferences;
use App\Services\Enms\Workspace;
use App\Services\QuestionnairePendingActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\HttpException;

function enmsFixture(): array
{
    [$audit,$client,$staff,$profile] = enmsSystemFixture();
    IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'source_profile_id' => $profile->id, 'section' => '4-3', 'source_hash' => 'test-source', 'status' => 'editing', 'lock_version' => 1, 'answers' => ['ZAKRES' => ['opis' => 'Zakres testowy']]]);

    return [$audit, $client, $staff, $profile, Workspace::for($staff, $audit, $profile, false), app(LeadershipWorkflow::class)];
}

function enmsSave(Workspace $w, string $kind, array $data, ?string $id = null): array
{
    $s = app(LeadershipWorkflow::class);

    return $s->save($w, $w->revision(), $s->sources($w)['hash'], $kind, $id, $data);
}

function enmsEvidence(Workspace $w): array
{
    return enmsSave($w, 'evidence', ['rodzaj' => 'DOK', 'tytul' => 'Decyzja organizacji', 'identyfikator' => 'D-TEST', 'data' => today()->toDateString(), 'wydal' => 'Organizacja', 'gdzie' => 'Dokumentacja', 'requirements' => [app(LeadershipWorkflow::class)->library->id('requirement', 'a')]]);
}

function enmsAuthority(Workspace $w, array $evidence, string $class = 'OPER', string $activity = 'wykonanie'): array
{
    return enmsSave($w, 'authority', ['user_id' => $w->actor->id, 'class' => $class, 'activity' => $activity, 'evidence_id' => $evidence['id'], 'valid_from' => today()->toDateString(), 'required_steps' => 1, 'step' => 1]);
}

function enmsTask(Workspace $w): array
{
    return enmsSave($w, 'task', ['przedmiot' => 'Kampania pomiarowa', 'owner_id' => $w->actor->id, 'requirements' => [app(LeadershipWorkflow::class)->library->id('requirement', 'a')], 'istotnosc' => 'proste', 'risk' => 'niskie', 'action_type' => 'operation', 'result_action_type' => 'reporting', 'stan' => 'zaplanowane']);
}

/** An opt-in copy for browser QA, containing factory data only. */
function enmsBrowserExport(string $name, array $fixture): void
{
    [$audit, $client, $staff, $profile] = $fixture;
    $path = base_path('tmp/'.$name.'-'.time().'.sqlite');
    $target = new PDO('sqlite:'.$path);
    $target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $target->beginTransaction();
    foreach (DB::select("select name, sql from sqlite_master where type='table' and name not like 'sqlite_%'") as $table) {
        $target->exec($table->sql);
        foreach (DB::table($table->name)->get() as $row) {
            $row = (array) $row;
            $columns = implode(',', array_map(fn ($k) => '"'.str_replace('"', '""', $k).'"', array_keys($row)));
            $stmt = $target->prepare('insert into "'.$table->name.'" ('.$columns.') values ('.implode(',', array_fill(0, count($row), '?')).')');
            $stmt->execute(array_values($row));
        }
    }
    $target->commit();
    file_put_contents(base_path('tmp/'.$name.'.json'), json_encode(['database' => $path, 'staff' => $staff->email, 'client' => $client->email, 'audit' => $audit->id, 'profile' => $profile->id], JSON_THROW_ON_ERROR));
}

test('enms native tabs render source questions without technical tabs and without creating records', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    foreach (['pytania', 'obowiazki', 'dowody', 'spojnosc', 'weryfikacja', 'ocena', 'ustalenia', 'zadania', 'raport', 'karta', 'historia'] as $tab) {
        $response = $this->actingAs($staff)->get(route('audits.enms.show', [$audit, $profile, 'tab' => $tab]));
        $response->assertOk()->assertDontSee('Specyfikacja dla Sławka')->assertDontSee('localStorage')->assertDontSee('Wczytaj zestaw demonstracyjny');
        if ($tab === 'obowiazki') {
            foreach ($s->library->requirements() as $r) {
                $response->assertSee($r['question']);
            }
        }
        if (getenv('ENMS_QA')) {
            file_put_contents(base_path('tmp/enms-'.$tab.'.html'), $response->getContent());
        }
    }
    expect($w->revision())->toBe(0)->and(DB::table('enms_records')->count())->toBe(0);
    $this->actingAs($client)->get(route('client.audits.enms.show', [$audit, $profile]))->assertOk()->assertDontSee('Ocena · 5.')->assertDontSee('Ocena · 6.');
    if (getenv('ENMS_BROWSER_QA')) {
        enmsBrowserExport('enms-browser', [$audit, $client, $staff, $profile]);
    }
});

test('enms thirteen honest gaps with plans can be submitted and editing invalidates only new approval', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $cw = Workspace::for($client, $audit, $profile, true);
    $old = $profile->fresh()->getRawOriginal();
    foreach ($s->library->requirements() as $id => $r) {
        if ($r['normative']) {
            $s->saveDeclaration($cw, $cw->revision(), $s->sources($cw)['hash'], $id, ['stan' => 'niema', 'plan' => ['opis' => 'Plan wdrożenia', 'termin' => '2026-12-31']]);
        }
    }
    expect($s->readiness($cw)['missing'])->toBe([]);
    $s->transition($cw, $cw->revision(), $s->sources($cw)['hash'], 'submission', []);
    expect($s->status($cw)['submitted'])->toBeTrue();
    expect(fn () => $s->saveDeclaration($cw, $cw->revision(), $s->sources($cw)['hash'], $s->library->id('requirement', 'a'), ['stan' => 'niema']))->toThrow(ValidationException::class);
    $s->saveDeclaration($w, $w->revision(), $s->sources($w)['hash'], $s->library->id('requirement', 'a'), ['stan' => 'czesciowo', 'plan' => ['opis' => 'Korekta audytora', 'termin' => '2026-12-31']]);
    expect($s->status($cw)['submitted'])->toBeFalse()->and($profile->fresh()->getRawOriginal())->toBe($old);
});

test('enms agreed methodological rules require details only for present state and a dated plan for gaps', function (string $code, string $state) {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $cw = Workspace::for($client, $audit, $profile, true);
    foreach ($s->library->requirements() as $id => $r) {
        if ($r['normative']) {
            $s->saveDeclaration($cw, $cw->revision(), $s->sources($cw)['hash'], $id, ['stan' => 'niema', 'plan' => ['opis' => 'Plan wdrożenia', 'termin' => '2026-12-31']]);
        }
    }
    $id = $s->library->id('requirement', $code);
    $save = fn (array $data) => $s->saveDeclaration($cw, $cw->revision(), $s->sources($cw)['hash'], $id, $data);
    $save(['stan' => $state]);
    expect($s->readiness($cw)['missing'])->not->toBeEmpty();
    expect(fn () => $s->transition($cw, $cw->revision(), $s->sources($cw)['hash'], 'submission', []))->toThrow(ValidationException::class);

    if ($state === 'jest') {
        $save(['plan' => ['opis' => 'Sam plan nie potwierdza istniejącego rozwiązania', 'termin' => '2026-12-31']]);
        expect(collect($s->readiness($cw)['missing'])->contains(fn ($message) => str_contains($message, 'nie wypełniono')))->toBeTrue();
        $proof = enmsEvidence($cw);
        $fields = [];
        foreach ($s->library->requirement($id)['pola'] as $field) {
            if ($field['wymagane'] && $field['typ'] !== 'odwolanie') {
                $fields[$field['klucz']] = $field['typ'] === 'data' ? '2026-11-16' : 'Opis testowy';
            }
        }
        $save(['pola' => $fields, 'evidence_ids' => [$proof['id']]]);
        if ($code === 'PRZ-14') {
            // The separate PRZ-00 consistency rule still applies; the agreement does not remove it.
            expect($s->readiness($cw)['inconsistencies'])->toHaveKey('NS-prz00');
            $s->transition($cw, $cw->revision(), $s->sources($cw)['hash'], 'explanation', ['key' => 'NS-prz00', 'text' => 'Najwyższe kierownictwo pozostaje do ustalenia w osobnym działaniu.']);
        }
    } else {
        expect($s->readiness($cw)['missing'])->toBe([$code.': uzupełnij plan i termin.']);
        $save(['plan' => ['opis' => 'Plan uzupełnienia']]);
        expect($s->readiness($cw)['missing'])->toBe([$code.': uzupełnij plan i termin.']);
        $save(['plan' => ['termin' => '2026-12-31']]);
        expect($s->one($cw, 'declaration', 'requirement_id', $id)['payload']['pola'] ?? [])->toBe([]);
    }
    expect($s->readiness($cw)['missing'])->toBe([]);
    $s->transition($cw, $cw->revision(), $s->sources($cw)['hash'], 'submission', []);
    expect($s->status($cw)['submitted'])->toBeTrue();
})->with([
    ['PRZ-00', 'jest'], ['PRZ-00', 'czesciowo'], ['PRZ-00', 'niema'],
    ['PRZ-14', 'jest'], ['PRZ-14', 'czesciowo'], ['PRZ-14', 'niema'],
]);

test('enms agreed verification methods preserve the five source choices and persist the three additions', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $methods = ['wgląd w dokument', 'oględziny', 'rozmowa', 'zapis w systemie', 'próbka', 'wgląd w zapisy', 'wgląd w zapis w systemie', 'wgląd w pozycję budżetu'];
    $field = collect($s->registers->fields('verification', $s->options($w)))->firstWhere('key', 'sposob');
    expect(array_keys($field['choices']))->toBe($methods);
    $proof = enmsEvidence($w);
    foreach ($methods as $method) {
        $verification = enmsSave($w, 'verification', ['evidence_id' => $proof['id'], 'dostepnosc' => 'okazany', 'wiarygodnosc' => 'potwierdzona', 'zakresInformacji' => 'Zakres sprawdzonych informacji', 'sposob' => $method, 'probka' => $method === 'próbka' ? 'Opis liczebności i doboru próbki' : null]);
        expect($w->record($verification['id'])['payload']['sposob'])->toBe($method)
            ->and($s->verificationMissing($verification['payload']))->toBe([]);
    }
    expect($w->records('verification'))->toHaveCount(1);
    $response = $this->actingAs($staff)->get(route('audits.enms.show', [$audit, $profile, 'tab' => 'weryfikacja']));
    $response->assertOk();
    foreach ($methods as $method) {
        $response->assertSee('value="'.$method.'"', false);
    }
    expect(fn () => enmsSave($w, 'verification', ['evidence_id' => $proof['id'], 'dostepnosc' => 'okazany', 'sposob' => 'nieuzgodniona metoda']))->toThrow(ValidationException::class);
});

test('enms task execution and acceptance require different grants and the denial is journalled', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $e = enmsEvidence($w);
    $g = enmsAuthority($w, $e);
    $t = enmsTask($w);
    $actions = app(AuthorizedActions::class);
    expect(fn () => enmsSave($w, 'task', array_replace($t['payload'], ['stan' => 'wykonane', 'evidence_id' => $e['id'], 'executor_id' => $staff->id]), $t['id']))->toThrow(ValidationException::class);
    $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'complete_task', $t['id'], $g['id'], ['evidence_id' => $e['id'], 'text' => 'Wykonano pomiar']);
    expect($w->record($t['id'])['payload']['stan'])->toBe('wykonane');
    expect(fn () => $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'approve_task_result', $t['id'], $g['id'], ['evidence_id' => $e['id'], 'text' => 'Przyjęto rezultat']))->toThrow(ValidationException::class);
    expect(collect($w->history())->contains('action', 'denied'))->toBeTrue();
    $g2 = enmsAuthority($w, $e, 'SPRAW', 'zatwierdzenie_rezultatu');
    $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'approve_task_result', $t['id'], $g2['id'], ['evidence_id' => $e['id'], 'text' => 'Przyjęto rezultat']);
    expect($w->record($t['id'])['payload']['result_approved']['authorization_ids'])->toBe([$g2['id']]);
});

test('enms closure becomes stale when proof verification or scope changes and its history survives', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $e = enmsEvidence($w);
    $cid = $s->library->id('criterion', 'KRY-a1');
    enmsSave($w, 'verification', ['evidence_id' => $e['id'], 'dostepnosc' => 'okazany', 'wiarygodnosc' => 'potwierdzona', 'zakresInformacji' => 'Granice i lokalizacje', 'sposob' => 'wgląd w dokument']);
    enmsSave($w, 'coverage', ['evidence_id' => $e['id'], 'criterion_id' => $cid, 'zakres' => 'pelne', 'coPotwierdza' => 'Zakres']);
    $closure = enmsSave($w, 'closure', ['criterion_id' => $cid, 'stan' => 'zamkniete', 'uzasadnienie' => 'Sprawdzono zgodność źródeł']);
    $rev = $w->revision();
    expect($closure['payload']['basis'])->toBe($s->basis($w, $cid));
    IsoSystemReview::where('section', '4-3')->increment('lock_version');
    expect($closure['payload']['basis'])->not->toBe($s->basis($w, $cid))->and($w->records('closure', $rev)[$closure['id']])->toBe($closure);
});

test('enms repeats a cyclic task without rejecting it and warns about matching occurrence', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $a = enmsTask($w);
    $b = enmsTask($w);
    expect($b['payload']['similar_ids'])->toContain($a['id']);
    $c = enmsSave($w, 'task', array_replace($a['payload'], ['cycle' => 'kwartał', 'occurrence' => '2027-Q1']));
    expect($c['payload']['similar_ids'])->toBe([]);
});

test('enms task is one record shared by the clause adapter and completion is visible in both sections', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $e = enmsEvidence($w);
    $g = enmsAuthority($w, $e);
    $t = enmsTask($w);
    $adapter = app(TaskReferences::class);
    $t = enmsSave($w, 'task', array_replace($t['payload'], ['requirements' => [$s->library->id('requirement', 'a'), $adapter->id('6-2')]]), $t['id']);
    expect(array_keys($adapter->forSection($w, '5-1')))->toBe([$t['id']])->and(array_keys($adapter->forSection($w, '6-2')))->toBe([$t['id']]);
    app(AuthorizedActions::class)->apply($w, $w->revision(), $s->sources($w)['hash'], 'complete_task', $t['id'], $g['id'], ['evidence_id' => $e['id'], 'text' => 'Wykonano']);
    expect($adapter->forSection($w, '6-2')[$t['id']]['payload']['stan'])->toBe('wykonane')->and($w->records('task'))->toHaveCount(1);
    $this->actingAs($staff)->get(route('audits.system.show', [$audit, $profile, '6-2']))->assertOk()->assertSee('Wspólne zadania EnMS')->assertSee('Kampania pomiarowa');
});

test('enms workbook round trips question mappings preserves evidence links and rejects foreign stale or formula input', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $proof = enmsEvidence($w);
    $selected = enmsEvidence($w);
    $requirementId = $s->library->id('requirement', 'b');
    $s->saveDeclaration($w, $w->revision(), $s->sources($w)['hash'], $requirementId, ['stan' => 'niema', 'evidence_ids' => [$selected['id']]]);
    $proofBeforeImport = $w->record($proof['id']);
    $excel = app(LeadershipWorkbook::class);
    $book = $excel->build($w);
    $sheet = $book->getSheetByName('Odpowiedzi');
    for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
        $key = $sheet->getCell('A'.$row)->getValue();
        if (str_ends_with($key, '/stan')) {
            $sheet->setCellValueExplicit('E'.$row, 'Jeszcze tego nie ma', DataType::TYPE_STRING);
        }
        if (str_ends_with($key, '/plan.opis')) {
            $sheet->setCellValue('E'.$row, 'Plan wdrożenia');
        }
        if (str_ends_with($key, '/plan.termin')) {
            $sheet->setCellValue('E'.$row, '2026-12-31');
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'enms-xlsx');
    try {
        (new Xlsx($book))->setPreCalculateFormulas(false)->save($path);
        $excel->import($w, $path);
        expect($s->readiness($w)['missing'])->toBe([])->and($w->record($proof['id'])['payload']['requirements'])->toBe([$s->library->id('requirement', 'a')]);
        expect($w->record($proof['id']))->toBe($proofBeforeImport)
            ->and($s->one($w, 'declaration', 'requirement_id', $requirementId)['payload']['evidence_ids'])->toBe([$selected['id']])
            ->and($s->one($w, 'declaration', 'requirement_id', $s->library->id('requirement', 'a'))['payload']['evidence_ids'])->toBe([]);
        expect(fn () => $excel->import($w, $path))->toThrow(ValidationException::class);
        [$otherAudit,$otherClient,$otherStaff,$otherProfile,$otherW] = enmsFixture();
        expect(fn () => $excel->import($otherW, $path))->toThrow(ValidationException::class);
        $fresh = $excel->build($w);
        $fresh->getSheetByName('Odpowiedzi')->setCellValue('E2', '=1+1');
        (new Xlsx($fresh))->setPreCalculateFormulas(false)->save($path);
        expect(fn () => $excel->import($w, $path))->toThrow(ValidationException::class);
    } finally {
        unlink($path);
        $book->disconnectWorksheets();
    }
});

test('enms document draft issue approval and effective date preserve exact issued bytes', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $e = enmsEvidence($w);
    $reporting = enmsAuthority($w, $e, 'SPRAW', 'zatwierdzenie_rezultatu');
    $governance = enmsAuthority($w, $e, 'USTR', 'zatwierdzenie_rezultatu');
    enmsSave($w, 'verification', ['evidence_id' => $e['id'], 'dostepnosc' => 'okazany', 'wiarygodnosc' => 'potwierdzona', 'zakresInformacji' => 'Upoważnienia i decyzje', 'sposob' => 'wgląd w dokument']);
    $docs = app(LeadershipDocuments::class);
    $actions = app(AuthorizedActions::class);
    foreach ($s->library->requirements() as $id => $requirement) {
        if ($requirement['normative']) {
            $rule = enmsSave($w, 'rule', ['requirement_id' => $id]);
            $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'resolve_rule', $rule['id'], $governance['id'], ['evidence_id' => $e['id'], 'text' => 'Treść pozostaje do ustalenia', 'rule_state' => 'przyjeta']);
        }
    }
    $draft = $docs->draft($w, $w->revision(), $s->sources($w)['hash'], ['type' => 'karta', 'version' => '1.0', 'reason' => 'Pierwsze wydanie'], ['name' => 'ENESA']);
    expect($w->records('document'))->toBe([]);
    $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'issue_document', $draft['id'], $reporting['id'], ['evidence_id' => $e['id'], 'text' => 'Wydanie kontrolowane']);
    $doc = array_values($w->records('document'))[0];
    $bytes = $docs->bytes($doc);
    expect($bytes)->toStartWith('%PDF')->and($actions->documentState($w, $doc)['approved'])->toBeFalse();
    $approval = ['evidence_id' => $e['id'], 'text' => 'Dowód obejmuje tę wersję', 'document_hash' => $doc['payload']['file_hash']];
    $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'approve_document', $doc['id'], $governance['id'], $approval);
    expect($actions->documentState($w, $doc)['status'])->toBe('Zatwierdzony');
    $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'activate_document', $doc['id'], $governance['id'], $approval + ['effective_from' => today()->addDay()->toDateString()]);
    expect($actions->documentState($w, $doc)['status'])->toBe('Zatwierdzony — oczekuje na wejście w życie')->and($docs->bytes($w->record($doc['id'])))->toBe($bytes);
    $this->actingAs($client)->get(route('client.audits.enms.document', [$audit, $profile, $draft['id']]))->assertForbidden();
    $this->get(route('client.audits.enms.document', [$audit, $profile, $doc['id']]))->assertOk()->assertHeader('content-type', 'application/pdf');
    if (getenv('ENMS_QA')) {
        file_put_contents(base_path('tmp/enms-karta.pdf'), $bytes);
    }
});

test('enms rule content is a proposal until authorized management resolves it and edits invalidate the resolution', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $e = enmsEvidence($w);
    $oper = enmsAuthority($w, $e);
    $rule = enmsSave($w, 'rule', ['requirement_id' => $s->library->id('requirement', 'a'), 'tresc' => 'Przegląd zakresu raz w roku', 'evidence_id' => $e['id']]);
    $actions = app(AuthorizedActions::class);
    $input = ['evidence_id' => $e['id'], 'text' => 'Rozstrzygnięcie organizacji', 'rule_state' => 'nieprzyjeta'];
    expect($rule['payload'])->not->toHaveKey('resolution');
    expect(fn () => $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'resolve_rule', $rule['id'], $oper['id'], $input))->toThrow(ValidationException::class);
    $governance = enmsAuthority($w, $e, 'USTR', 'zatwierdzenie_rezultatu');
    enmsSave($w, 'verification', ['evidence_id' => $e['id'], 'dostepnosc' => 'okazany', 'wiarygodnosc' => 'potwierdzona', 'zakresInformacji' => 'Kompetencje kierownictwa', 'sposob' => 'wgląd w dokument']);
    $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'resolve_rule', $rule['id'], $governance['id'], $input);
    expect($w->record($rule['id'])['payload']['resolution']['state'])->toBe('nieprzyjeta');
    $resolvedRevision = $w->revision();
    enmsSave($w, 'rule', array_replace($rule['payload'], ['tresc' => 'Nowa propozycja']), $rule['id']);
    expect($w->record($rule['id'])['payload'])->not->toHaveKey('resolution')
        ->and($w->records('rule', $resolvedRevision)[$rule['id']]['payload']['resolution']['state'])->toBe('nieprzyjeta');
});

test('enms partial closure reports its limitation and a new unreliable proof invalidates the basis', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $e = enmsEvidence($w);
    $cid = $s->library->id('criterion', 'KRY-a1');
    enmsSave($w, 'verification', ['evidence_id' => $e['id'], 'dostepnosc' => 'okazany', 'wiarygodnosc' => 'potwierdzona', 'zakresInformacji' => 'Zakres', 'sposob' => 'wgląd w dokument']);
    enmsSave($w, 'coverage', ['evidence_id' => $e['id'], 'criterion_id' => $cid, 'zakres' => 'czesciowe', 'coPotwierdza' => 'Obiekty', 'czegoBrakuje' => 'Granice']);
    $closure = enmsSave($w, 'closure', ['criterion_id' => $cid, 'stan' => 'zamkniete', 'uzasadnienie' => 'Łączna podstawa jest wystarczająca']);
    expect($closure['payload']['partial_only'])->toBeTrue();
    expect(implode(' ', app(LeadershipDocuments::class)->limitations($w)))->toContain('wyłącznie na podstawie pokryć częściowych');
    $bad = enmsEvidence($w);
    enmsSave($w, 'coverage', ['evidence_id' => $bad['id'], 'criterion_id' => $cid, 'zakres' => 'zadne', 'czegoBrakuje' => 'Dowodu nie okazano']);
    expect($s->basis($w, $cid))->not->toBe($closure['payload']['basis']);
});

test('enms consultant evidence does not invalidate client submission but changes to linked evidence do', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $before = $s->clientHash($w);
    $e = enmsEvidence($w);
    expect($s->clientHash($w))->toBe($before);
    $s->saveDeclaration($w, $w->revision(), $s->sources($w)['hash'], $s->library->id('requirement', 'a'), ['stan' => 'jest', 'evidence_ids' => [$e['id']]]);
    $linked = $s->clientHash($w);
    enmsSave($w, 'evidence', ['rodzaj' => 'ZAP', 'tytul' => 'Protokół dodany przez konsultanta po odpowiedzi', 'requirements' => [$s->library->id('requirement', 'a')]]);
    expect($s->clientHash($w))->toBe($linked);
    enmsSave($w, 'evidence', array_replace($e['payload'], ['tytul' => 'Zmieniona podstawa odpowiedzi']), $e['id']);
    expect($s->clientHash($w))->not->toBe($linked);
});

test('enms multistep authority requires all steps and rechecks revoked preceding grants', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $e = enmsEvidence($w);
    $first = enmsAuthority($w, $e);
    $first = enmsSave($w, 'authority', array_replace($first['payload'], ['required_steps' => 2]), $first['id']);
    $second = enmsSave($w, 'authority', array_replace($first['payload'], ['step' => 2]));
    $task = enmsTask($w);
    $actions = app(AuthorizedActions::class);
    $input = ['evidence_id' => $e['id'], 'text' => 'Wykonano'];
    expect($actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'complete_task', $task['id'], $first['id'], $input))->toBeFalse();
    expect($w->record($task['id'])['payload']['stan'])->toBe('zaplanowane');
    expect($actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'complete_task', $task['id'], $second['id'], $input))->toBeTrue();
    expect($w->record($task['id'])['payload']['execution_authorization_ids'])->toHaveCount(2);
    $task2 = enmsTask($w);
    expect($actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'complete_task', $task2['id'], $first['id'], $input))->toBeFalse();
    $w->append($w->revision(), 'authority', $first['id'], $first['symbol'], array_replace($first['payload'], ['revocation' => ['date' => today()->toDateString()]]), config('enms.library'), $first['scope_version']);
    expect($actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'complete_task', $task2['id'], $second['id'], $input))->toBeFalse();
    expect($w->record($task2['id'])['payload']['stan'])->toBe('zaplanowane');
});

test('enms independent effectiveness rejects execution conflicts and self assessment needs a different supervisor', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $e = enmsEvidence($w);
    $execution = enmsAuthority($w, $e);
    $reporting = enmsAuthority($w, $e, 'SPRAW', 'zatwierdzenie_rezultatu');
    $actions = app(AuthorizedActions::class);
    $task = enmsTask($w);
    $input = ['evidence_id' => $e['id'], 'text' => 'Pomiar wykonany'];
    $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'complete_task', $task['id'], $execution['id'], $input);
    $assessment = $input + ['competence_id' => $e['id'], 'wynik' => 'skuteczne', 'metoda' => 'Porównanie pomiarów przed i po'];
    $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'assess_effectiveness', $task['id'], $reporting['id'], $assessment);
    $result = array_values($w->records('effectiveness'))[0];
    expect($result['payload']['supervised'])->toBeFalse();
    expect(fn () => $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'approve_effectiveness', $result['id'], $reporting['id'], $input))->toThrow(ValidationException::class);
    $supervisor = enmsSave($w, 'authority', array_replace($reporting['payload'], ['user_id' => $client->id]));
    $cw = Workspace::for($client, $audit, $profile, true);
    $actions->apply($cw, $cw->revision(), $s->sources($cw)['hash'], 'approve_effectiveness', $result['id'], $supervisor['id'], $input);
    expect($w->record($result['id'])['payload']['supervised'])->toBeTrue();
    $important = enmsTask($w);
    $important = enmsSave($w, 'task', array_replace($important['payload'], ['istotnosc' => 'istotne']), $important['id']);
    $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'complete_task', $important['id'], $execution['id'], $input);
    expect(fn () => $actions->apply($w, $w->revision(), $s->sources($w)['hash'], 'assess_effectiveness', $important['id'], $reporting['id'], $assessment))->toThrow(ValidationException::class);
});

test('enms preview needs no company and document routes never read another workspace record', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $type = $audit->surveys()->first()->auditType;
    $this->actingAs($staff)->get(route('audit-types.questionnaire-preview', [$type, '5-1', 'new_model' => 1]))->assertOk()->assertDontSee($audit->company->name)->assertDontSee('Specyfikacja dla Sławka');
    expect(DB::table('enms_workspaces')->count())->toBe(0);
    $draft = app(LeadershipDocuments::class)->draft($w, $w->revision(), $s->sources($w)['hash'], ['type' => 'karta', 'version' => '1.0', 'reason' => 'Kontrola izolacji'], ['name' => 'ENESA']);
    [$otherAudit,$otherClient,$otherStaff,$otherProfile] = enmsFixture();
    $this->actingAs($otherStaff)->get(route('audits.enms.document', [$otherAudit, $otherProfile, $draft['id']]))->assertNotFound();
    $this->actingAs($otherClient)->get(route('client.audits.enms.show', [$audit, $profile]))->assertNotFound();
    $this->actingAs($client)->post(route('client.audits.enms.update', [$audit, $profile]), ['revision' => $w->revision(), 'source_hash' => $s->sources($w)['hash'], 'operation' => 'record', 'kind' => 'authority', 'payload' => []])->assertForbidden();
});

test('enms diagnostic report and appendix use completed workflow snapshots', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $e = enmsEvidence($w);
    enmsSave($w, 'evidence', array_replace($e['payload'], ['requirements' => [$s->library->id('requirement', 'PRZ-00')]]), $e['id']);
    $cw = Workspace::for($client, $audit, $profile, true);
    foreach ($s->library->requirements() as $id => $r) {
        if ($r['normative']) {
            $s->saveDeclaration($cw, $cw->revision(), $s->sources($cw)['hash'], $id, ['stan' => 'niema', 'plan' => ['opis' => 'Opracowanie zasad i zebranie dowodów', 'termin' => '2026-12-31']]);
        }
    }
    $s->transition($cw, $cw->revision(), $s->sources($cw)['hash'], 'submission', []);
    foreach ($s->library->requirements() as $id => $r) {
        if ($r['normative']) {
            enmsSave($w, 'assessment', ['requirement_id' => $id, 'stan' => 'niewykazane', 'uzasadnienie' => 'Organizacja potwierdziła brak wdrożenia. Zaplanowano działania.', 'mode' => 'ciagla']);
        }
    }
    $s->transition($w, $w->revision(), $s->sources($w)['hash'], 'completion', ['text' => 'Ocena na podstawie deklaracji organizacji; wdrożenie niewykazane.']);
    expect($s->status($w)['assessed'])->toBeTrue();
    expect(app(LeadershipProgress::class)->forAudits([$audit->id])[$audit->id.':'.$profile->site_id])->toBe(['answered' => 13, 'total' => 13, 'percent' => 100]);
    foreach (['raport', 'zalacznik', 'ankieta'] as $type) {
        $draft = app(LeadershipDocuments::class)->draft($w, $w->revision(), $s->sources($w)['hash'], ['type' => $type, 'version' => '1.0', 'reason' => 'Pierwsza ocena'], ['name' => 'ENESA']);
        $bytes = app(LeadershipDocuments::class)->render($draft['payload']);
        expect($bytes)->toStartWith('%PDF');
        expect($draft['payload']['snapshot']['status']['assessed'])->toBeTrue();
        if (getenv('ENMS_QA')) {
            file_put_contents(base_path('tmp/enms-'.$type.'.pdf'), $bytes);
        }
    }
    $newAnswer = $s->library->id('requirement', 'a');
    $s->saveDeclaration($w, $w->revision(), $s->sources($w)['hash'], $newAnswer, ['stan' => 'czesciowo', 'plan' => ['opis' => 'Zmieniony plan', 'termin' => '2027-01-31']]);
    expect($s->status($w)['assessed'])->toBeFalse();
    $this->actingAs($client);
    expect(app(QuestionnairePendingActions::class)->enmsForClient(collect([$audit]))[$audit->id])->toHaveCount(1);
});

test('enms questions need a current answer and only the auditor can close them', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $cw = Workspace::for($client, $audit, $profile, true);
    $q = enmsSave($w, 'question', ['requirement_id' => $s->library->id('requirement', 'a'), 'pytanie' => 'Kto zatwierdził zakres?']);
    $data = ['question_id' => $q['id'], 'text' => 'Wyjaśnienie przyjęto'];
    expect(fn () => $s->transition($w, $w->revision(), $s->sources($w)['hash'], 'close_question', $data))->toThrow(ValidationException::class);
    $s->transition($cw, $cw->revision(), $s->sources($cw)['hash'], 'answer', ['question_id' => $q['id'], 'text' => 'Kierownictwo wskazane w dokumencie']);
    expect($s->hasCurrentAnswer($w, $q))->toBeTrue();
    expect(fn () => $s->transition($cw, $cw->revision(), $s->sources($cw)['hash'], 'close_question', $data))->toThrow(HttpException::class);
    $s->transition($w, $w->revision(), $s->sources($w)['hash'], 'close_question', $data);
    expect($w->record($q['id'])['payload']['state'])->toBe('closed');
    $q = enmsSave($w, 'question', array_replace($q['payload'], ['pytanie' => 'Kiedy zatwierdzono zakres?']), $q['id']);
    expect($q['payload']['state'])->toBe('open')->and($s->hasCurrentAnswer($w, $q))->toBeFalse();
});

test('enms reproduces all thirteen reference assessments from its exact evidence graph', function () {
    [$audit,$client,$staff,$profile,$w,$s] = enmsFixture();
    $reference = json_decode(file_get_contents(base_path('tests/Fixtures/enms-leadership-reference.json')), true, 512, JSON_THROW_ON_ERROR);
    $proofs = [];
    foreach ($reference['evidence'] as $row) {
        $proof = enmsSave($w, 'evidence', ['rodzaj' => $row['kind'], 'tytul' => 'Dowód referencyjny '.$row['id'], 'identyfikator' => $row['id'], 'data' => '2026-11-15', 'wydal' => 'Organizacja testowa', 'uczestnicy' => 'Uczestnik testowy', 'gdzie' => 'Rejestr testowy', 'requirements' => array_map(fn ($code) => $s->library->id('requirement', $code), $row['requirements'])]);
        $proofs[$row['id']] = $proof['id'];
        enmsSave($w, 'verification', $row['verification'] + ['evidence_id' => $proof['id'], 'zakresInformacji' => 'Zakres informacji z dowodu referencyjnego', 'uwaga' => 'Ograniczenie z referencji']);
    }
    $coverage = [];
    foreach ($reference['coverage'] as $row) {
        $record = enmsSave($w, 'coverage', ['evidence_id' => $proofs[$row['evidence']], 'criterion_id' => $s->library->id('criterion', $row['criterion']), 'zakres' => $row['extent'], 'coPotwierdza' => 'Pokrycie zgodne ze scenariuszem', 'czegoBrakuje' => 'Ograniczenie ze scenariusza']);
        $coverage[$row['evidence'].'/'.$row['criterion']] = $record['id'];
    }
    foreach ($reference['closures'] as $code => $row) {
        $closure = enmsSave($w, 'closure', ['criterion_id' => $s->library->id('criterion', $code), 'stan' => $row['state'], 'uzasadnienie' => 'Ręczne rozstrzygnięcie konsultanta ze scenariusza']);
        expect($closure['payload']['partial_only'])->toBe($row['partial_only']);
    }
    foreach ($reference['assessments'] as $code => $row) {
        $assessment = enmsSave($w, 'assessment', ['requirement_id' => $s->library->id('requirement', $code), 'stan' => $row['state'], 'uzasadnienie' => 'Ocena konsultanta ze scenariusza referencyjnego', 'mode' => 'ciagla', 'coverage_ids' => array_map(fn ($id) => $coverage[$id], $row['sources'])]);
        expect($assessment['payload']['stan'])->toBe($row['state']);
    }
    expect($w->records('evidence'))->toHaveCount(18)->and($w->records('coverage'))->toHaveCount(52)->and($w->records('closure'))->toHaveCount(31)->and($w->records('assessment'))->toHaveCount(13);
});

test('enms reference declarations pass from client through consultant assessment to diagnostic documents', function (bool $reorderJson) {
    $fixture = enmsFixture();
    [$audit, $client, $staff, $profile, $w, $s] = $fixture;
    foreach (['6-2' => 'CELE', '5-3' => 'ROLE'] as $section => $key) {
        IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'source_profile_id' => $profile->id, 'section' => $section, 'source_hash' => 'test-source', 'status' => 'editing', 'lock_version' => 1, 'answers' => [$key => [['opis' => 'Dane źródłowe organizacji testowej']]]]);
    }
    if (getenv('ENMS_REFERENCE_BROWSER_QA')) {
        enmsBrowserExport('enms-browser-reference', $fixture);
    }
    $reference = json_decode(file_get_contents(base_path('tests/Fixtures/enms-leadership-reference.json')), true, 512, JSON_THROW_ON_ERROR);
    $cw = Workspace::for($client, $audit, $profile, true);
    $proofs = [];
    $addProof = fn (Workspace $context, array $row) => enmsSave($context, 'evidence', ['rodzaj' => $row['kind'], 'tytul' => 'Dowód referencyjny '.$row['id'], 'identyfikator' => $row['id'], 'data' => '2026-11-15', 'wydal' => 'Organizacja testowa', 'uczestnicy' => 'Uczestnik testowy', 'gdzie' => 'Rejestr testowy', 'requirements' => array_map(fn ($code) => $s->library->id('requirement', $code), $row['requirements'])])['id'];
    foreach ($reference['evidence'] as $row) {
        if ($row['added_by'] === 'klient') {
            $proofs[$row['id']] = $addProof($cw, $row);
        }
    }
    foreach ($reference['declarations'] as $code => $row) {
        $s->saveDeclaration($cw, $cw->revision(), $s->sources($cw)['hash'], $s->library->id('requirement', $code), $row + ['evidence_ids' => array_map(fn ($id) => $proofs[$id], $row['evidence'])]);
    }
    expect($s->readiness($cw)['missing'])->toBe([]);
    expect($s->readiness($cw)['gaps'])->toHaveCount(5);
    if ($reorderJson) {
        enmsReorderStoredJson($cw);
    }
    $this->actingAs($client)->post(route('client.audits.enms.update', [$audit, $profile]), ['revision' => $cw->revision(), 'source_hash' => $s->sources($cw)['hash'], 'operation' => 'submission'])->assertSessionHasNoErrors()->assertRedirect();
    expect($s->status($cw)['submitted'])->toBeTrue();
    foreach ($reference['evidence'] as $row) {
        if ($row['added_by'] !== 'klient') {
            $proofs[$row['id']] = $addProof($w, $row);
        }
    }
    expect($s->status($cw)['submitted'])->toBeTrue();
    foreach ($reference['evidence'] as $row) {
        enmsSave($w, 'verification', $row['verification'] + ['evidence_id' => $proofs[$row['id']], 'zakresInformacji' => 'Zakres informacji z dowodu referencyjnego', 'uwaga' => 'Ograniczenie z referencji']);
    }
    $coverage = [];
    foreach ($reference['coverage'] as $row) {
        $coverage[$row['evidence'].'/'.$row['criterion']] = enmsSave($w, 'coverage', ['evidence_id' => $proofs[$row['evidence']], 'criterion_id' => $s->library->id('criterion', $row['criterion']), 'zakres' => $row['extent'], 'coPotwierdza' => 'Pokrycie zgodne ze scenariuszem', 'czegoBrakuje' => 'Ograniczenie ze scenariusza'])['id'];
    }
    foreach ($reference['findings'] as $row) {
        enmsSave($w, 'finding', ['requirement_id' => $s->library->id('requirement', $row['requirement']), 'criterion_id' => $s->library->id('criterion', $row['criterion']), 'typ' => $row['type'], 'priorytet' => $row['priority'], 'opis' => 'Ustalenie testowe', 'ryzyko' => 'Ryzyko testowe']);
    }
    foreach ($reference['closures'] as $code => $row) {
        enmsSave($w, 'closure', ['criterion_id' => $s->library->id('criterion', $code), 'stan' => $row['state'], 'uzasadnienie' => 'Rozstrzygnięcie konsultanta']);
    }
    if ($reorderJson) {
        enmsReorderStoredJson($w);
    }
    foreach ($reference['assessments'] as $code => $row) {
        enmsSave($w, 'assessment', ['requirement_id' => $s->library->id('requirement', $code), 'stan' => $row['state'], 'uzasadnienie' => 'Ocena konsultanta', 'mode' => 'ciagla', 'coverage_ids' => array_map(fn ($id) => $coverage[$id], $row['sources'])]);
    }
    if ($reorderJson) {
        enmsReorderStoredJson($w);
    }
    $this->actingAs($staff)->post(route('audits.enms.update', [$audit, $profile]), ['revision' => $w->revision(), 'source_hash' => $s->sources($w)['hash'], 'operation' => 'completion', 'payload' => ['text' => 'Zakończenie diagnostyki na podstawie scenariusza referencyjnego']])->assertSessionHasNoErrors()->assertRedirect();
    expect($s->status($w)['assessed'])->toBeTrue();
    foreach (['raport', 'zalacznik'] as $type) {
        $this->post(route('audits.enms.draft', [$audit, $profile]), ['revision' => $w->revision(), 'source_hash' => $s->sources($w)['hash'], 'type' => $type, 'version' => '1.0', 'reason' => 'Odbiór referencyjny'])->assertSessionHasNoErrors()->assertRedirect();
        $draft = collect($w->records('document_draft'))->first(fn ($r) => $r['payload']['type'] === $type);
        expect($draft['payload']['snapshot']['issuer']['logo'])->toStartWith('data:image/');
        $this->get(route('audits.enms.document', [$audit, $profile, $draft['id']]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($draft['payload']['snapshot']['status']['assessed'])->toBeTrue();
    }
    expect($w->records('declaration'))->toHaveCount(15)->and($w->records('finding'))->toHaveCount(9);
})->with(['original JSON order' => false, 'database JSON order' => true]);
