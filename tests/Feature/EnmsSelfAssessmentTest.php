<?php

use App\Models\IsoSystemReview;
use App\Models\User;
use App\Services\Enms\LeadershipDocuments;
use App\Services\Enms\LeadershipLibrary;
use App\Services\Enms\LeadershipWorkbook;
use App\Services\Enms\Workspace;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('enms self assessment renders four editable source questions and persists each answer for both roles', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $profileBefore = $profile->fresh()->getRawOriginal();
    $legacy = IsoSystemReview::create(['audit_id' => $audit->id, 'site_id' => $profile->site_id, 'source_profile_id' => $profile->id, 'section' => '5-1', 'source_hash' => 'legacy', 'status' => 'approved', 'lock_version' => 3, 'answers' => ['SAMO' => ['P1' => ['stan' => 'jest', 'komentarz' => 'Historyczna odpowiedź']]], 'client_approval' => ['user_id' => $client->id], 'auditor_approval' => ['user_id' => $staff->id]]);
    $legacyBefore = $legacy->fresh()->getRawOriginal();
    foreach ([[$staff, 'audits.enms.'], [$client, 'client.audits.enms.']] as [$actor, $prefix]) {
        $response = $this->actingAs($actor)->get(route($prefix.'show', [$audit, $profile, 'tab' => 'pytania']))->assertOk()->assertDontSee('Historyczna odpowiedź');
        expect(substr_count($response->getContent(), 'name="payload[stan]"'))->toBe(4)
            ->and(substr_count($response->getContent(), 'name="payload[komentarz]"'))->toBe(4);
        foreach ($s->library->data()['PYTANIA_4'] as $q) {
            $response->assertSee($q['nazwa'])->assertSee($q['opis'])->assertSee($q['dowod']);
        }
    }
    expect($w->revision())->toBe(0);
    if (getenv('ENMS_BROWSER_QA')) {
        enmsBrowserExport('enms-self-assessment-browser', [$audit, $client, $staff, $profile]);
    }
    foreach (['P1' => 'jest', 'P2' => 'czesciowo', 'P3' => 'nie', 'P4' => ''] as $id => $state) {
        $this->actingAs($client)->post(route('client.audits.enms.update', [$audit, $profile]), ['revision' => $w->revision(), 'source_hash' => $s->sources($w)['hash'], 'operation' => 'self_assessment', 'question_id' => $id, 'payload' => ['stan' => $state, 'komentarz' => $id === 'P4' ? '0' : "Komentarz $id <script>alert(1)</script>"], 'tab' => 'pytania'])->assertRedirect()->assertSessionHasNoErrors();
        expect($s->one($w, 'self_assessment', 'question_id', $id)['payload'])->toBe(['question_id' => $id, 'stan' => $state, 'komentarz' => $id === 'P4' ? '0' : "Komentarz $id <script>alert(1)</script>"]);
    }
    $this->actingAs($staff)->get(route('audits.enms.show', [$audit, $profile]))->assertOk()->assertSee('Komentarz P3 <script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)->assertSee('>0</textarea>', false);
    $first = $s->one($w, 'self_assessment', 'question_id', 'P1');
    $s->saveSelfAssessment($w, $w->revision(), $s->sources($w)['hash'], 'P1', ['stan' => 'nie', 'komentarz' => null]);
    $updated = $s->one($w, 'self_assessment', 'question_id', 'P1');
    expect($updated['id'])->toBe($first['id'])->and($updated['version'])->toBe(2)->and($updated['payload']['komentarz'])->toBe('');
    $revision = $w->revision();
    $s->saveSelfAssessment($w, $revision, $s->sources($w)['hash'], 'P1', ['stan' => 'nie']);
    expect($w->revision())->toBe($revision)->and(count($w->records('self_assessment')))->toBe(4)
        ->and($w->records('self_assessment', 4)[$first['id']]['payload'])->toBe($first['payload'])
        ->and($profile->fresh()->getRawOriginal())->toBe($profileBefore)->and($legacy->fresh()->getRawOriginal())->toBe($legacyBefore);
});

test('enms self assessment rejects invalid questions values and stale or foreign requests without writes', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $input = ['revision' => 0, 'source_hash' => $s->sources($w)['hash'], 'operation' => 'self_assessment', 'question_id' => 'P1', 'payload' => ['stan' => 'jest']];
    $url = route('client.audits.enms.update', [$audit, $profile]);
    foreach ([['question_id' => 'P5'], ['payload' => ['stan' => 'niema']], ['payload' => ['stan' => ['jest']]], ['payload' => ['komentarz' => ['invalid']]], ['payload' => ['komentarz' => str_repeat('a', 12001)]]] as $changes) {
        $this->actingAs($client)->postJson($url, array_replace($input, $changes))->assertUnprocessable();
    }
    $this->postJson($url, array_replace($input, ['revision' => 1]))->assertConflict();
    $this->postJson($url, array_replace($input, ['source_hash' => str_repeat('0', 64)]))->assertConflict();
    $page = route('client.audits.enms.show', [$audit, $profile]);
    $this->from($page)->post($url, array_replace($input, ['payload' => ['komentarz' => ['invalid']]]))->assertRedirect($page)->assertSessionHasErrors('komentarz');
    $this->get($page)->assertOk()->assertSee('enms-invalid');
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('employee'));
    $viewer->givePermissionTo(Permission::findOrCreate('audits.view'));
    $this->actingAs($viewer)->get(route('audits.enms.show', [$audit, $profile]))->assertOk()->assertSee('<fieldset disabled>', false)->assertDontSee('Zapisz odpowiedź');
    $this->postJson(route('audits.enms.update', [$audit, $profile]), $input)->assertForbidden();
    [$otherAudit, $otherClient, $otherStaff, $otherProfile] = enmsFixture();
    $this->actingAs($otherClient)->postJson($url, $input)->assertNotFound();
    $this->actingAs($client)->postJson(route('client.audits.enms.update', [$audit, $otherProfile]), $input)->assertNotFound();
    config(['enms.audit_ids' => []]);
    $this->actingAs($staff)->postJson(route('audits.enms.update', [$audit, $profile]), $input)->assertNotFound();
    expect(DB::table('enms_records')->count())->toBe(0);
});

test('enms self assessment keeps existing submission valid on read and follows normal edit and approval rules', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $cw = Workspace::for($client, $audit, $profile, true);
    foreach ($s->library->requirements() as $id => $r) {
        if ($r['normative']) {
            $s->saveDeclaration($cw, $cw->revision(), $s->sources($cw)['hash'], $id, ['stan' => 'niema', 'plan' => ['opis' => 'Plan wdrożenia', 'termin' => '2026-12-31']]);
        }
    }
    $s->transition($cw, $cw->revision(), $s->sources($cw)['hash'], 'submission', []);
    $before = $w->records();
    $clientHash = $s->clientHash($w);
    $this->actingAs($client)->get(route('client.audits.enms.show', [$audit, $profile]))->assertOk()->assertSee('<fieldset disabled>', false)->assertDontSee('Zapisz odpowiedź');
    expect($w->records())->toBe($before)->and($s->clientHash($w))->toBe($clientHash)->and($s->status($w)['submitted'])->toBeTrue();
    expect(fn () => $s->saveSelfAssessment($cw, $cw->revision(), $s->sources($cw)['hash'], 'P1', ['stan' => 'jest']))->toThrow(ValidationException::class);
    $s->saveSelfAssessment($w, $w->revision(), $s->sources($w)['hash'], 'P1', ['stan' => 'nie', 'komentarz' => 'Uzupełnienie audytora']);
    expect($s->status($cw)['submitted'])->toBeFalse()->and($s->readiness($cw)['missing'])->toBe([])->and(count($w->records('declaration')))->toBe(13);
    $s->transition($cw, $cw->revision(), $s->sources($cw)['hash'], 'submission', []);
    $before = $w->records();
    $s->saveSelfAssessment($w, $w->revision(), $s->sources($w)['hash'], 'P1', ['stan' => 'nie', 'komentarz' => 'Uzupełnienie audytora']);
    expect($w->records())->toBe($before)->and($s->status($cw)['submitted'])->toBeTrue();
    $s->saveSelfAssessment($w, $w->revision(), $s->sources($w)['hash'], 'P1', ['stan' => '', 'komentarz' => '']);
    expect($s->status($cw)['submitted'])->toBeFalse()->and(count($w->records('submission')))->toBe(1)
        ->and(collect($w->records('submission'))->first()['version'])->toBe(2);
});

test('enms self assessment Excel preserves source mappings and round trips all choices optional zero and empty comments', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $excel = app(LeadershipWorkbook::class);
    $book = $excel->build($w);
    $sheet = $book->getSheetByName('Odpowiedzi');
    $values = ['SAMO.P1.stan' => 'Jest i można pokazać', 'SAMO.P1.komentarz' => "Plan\nDruga linia", 'SAMO.P2.stan' => 'Częściowo', 'SAMO.P2.komentarz' => '0', 'SAMO.P3.stan' => 'Jeszcze tego nie ma', 'SAMO.P3.komentarz' => '', 'SAMO.P4.stan' => '', 'SAMO.P4.komentarz' => 'Komentarz bez odpowiedzi'];
    for ($row = 2; $row <= 9; $row++) {
        $key = $sheet->getCell('A'.$row)->getValue();
        expect($key)->toBe(array_keys($values)[$row - 2]);
        $sheet->setCellValueExplicit('E'.$row, $values[$key], DataType::TYPE_STRING);
    }
    $path = tempnam(sys_get_temp_dir(), 'enms-self-xlsx');
    try {
        (new Xlsx($book))->setPreCalculateFormulas(false)->save($path);
        $excel->import($w, $path);
        $fresh = $excel->build($w)->getSheetByName('Odpowiedzi');
        for ($row = 2; $row <= 9; $row++) {
            expect((string) $fresh->getCell('E'.$row)->getValue())->toBe($values[$fresh->getCell('A'.$row)->getValue()]);
        }
        expect($s->one($w, 'self_assessment', 'question_id', 'P3')['payload']['stan'])->toBe('nie')->and($s->status($w)['submitted'])->toBeFalse();
        $clear = $excel->build($w);
        $clear->getSheetByName('Odpowiedzi')->setCellValueExplicit('E2', '', DataType::TYPE_STRING)->setCellValueExplicit('E3', '', DataType::TYPE_STRING);
        (new Xlsx($clear))->setPreCalculateFormulas(false)->save($path);
        $excel->import($w, $path);
        expect($s->one($w, 'self_assessment', 'question_id', 'P1')['payload'])->toBe(['question_id' => 'P1', 'stan' => '', 'komentarz' => '']);
    } finally {
        unlink($path);
    }
});

test('enms legacy Excel remains importable without clearing new self assessment', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $s->saveSelfAssessment($w, 0, $s->sources($w)['hash'], 'P1', ['stan' => 'jest', 'komentarz' => 'Zachowaj tę odpowiedź']);
    $before = $w->records('self_assessment');
    $excel = app(LeadershipWorkbook::class);
    $book = $excel->build($w);
    $book->getSheetByName('Odpowiedzi')->removeRow(2, 8);
    // Reconstruct the signed enms-1 contract, whose declaration mappings have not changed.
    $fields = (new ReflectionMethod($excel, 'fields'))->invoke($excel, $w, false);
    $stamp = json_decode(Crypt::decryptString($book->getSheetByName('_ENESA')->getCell('A1')->getValue()), true);
    $stamp['format'] = 'enms-1';
    $stamp['layout'] = hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR));
    $book->getSheetByName('_ENESA')->setCellValue('A1', Crypt::encryptString(json_encode($stamp, JSON_THROW_ON_ERROR)));
    $path = tempnam(sys_get_temp_dir(), 'enms-old-xlsx');
    try {
        (new Xlsx($book))->setPreCalculateFormulas(false)->save($path);
        $excel->import($w, $path);
        expect($w->records('self_assessment'))->toBe($before);
    } finally {
        unlink($path);
    }
});

test('enms questionnaire print freezes self assessment while old drafts and other document types retain their content', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    foreach (['P1' => 'jest', 'P2' => 'czesciowo', 'P3' => 'nie', 'P4' => ''] as $id => $state) {
        $s->saveSelfAssessment($w, $w->revision(), $s->sources($w)['hash'], $id, ['stan' => $state, 'komentarz' => $id === 'P4' ? '0' : "Test $id: pierwsza linia\nDruga linia <script>test</script>"]);
    }
    $docs = app(LeadershipDocuments::class);
    $draft = $docs->draft($w, $w->revision(), $s->sources($w)['hash'], ['type' => 'ankieta', 'version' => 'QA-P1-P4', 'reason' => 'Test wydruku'], ['name' => 'ENESA', 'logo' => null]);
    $snapshot = $draft['payload']['snapshot'];
    expect(array_column($snapshot['self_assessment'], 'id'))->toBe(['P1', 'P2', 'P3', 'P4'])
        ->and(array_column($snapshot['self_assessment'], 'answer'))->toBe([...array_values(LeadershipLibrary::SELF_ASSESSMENT_STATES), 'Brak — do uzupełnienia'])
        ->and($snapshot['self_assessment'][3]['comment'])->toBe('0');
    $html = function (array $document) use ($s) {
        $snapshot = $document['snapshot'];
        $rows = fn ($kind) => array_values(array_filter($snapshot['records'], fn ($r) => $r['kind'] === $kind));
        $one = fn ($kind, $field, $id) => collect($rows($kind))->first(fn ($r) => ($r['payload'][$field] ?? null) === $id);

        return view('audits.enms.pdf', ['document' => $document, 'snapshot' => $snapshot, 'rows' => $rows, 'one' => $one, 'issued' => false, 'registers' => $s->registers])->render();
    };
    expect($html($draft['payload']))->toContain('Samoocena kierownictwa', '&lt;script&gt;test&lt;/script&gt;')->not->toContain('<script>test</script>');
    $old = $draft['payload'];
    unset($old['snapshot']['self_assessment']);
    expect($html($old))->not->toContain('Samoocena kierownictwa');
    $card = $docs->draft($w, $w->revision(), $s->sources($w)['hash'], ['type' => 'karta', 'version' => 'QA', 'reason' => 'Test'], ['name' => 'ENESA', 'logo' => null]);
    expect($card['payload']['snapshot'])->not->toHaveKey('self_assessment');
    $s->saveSelfAssessment($w, $w->revision(), $s->sources($w)['hash'], 'P1', ['stan' => 'nie', 'komentarz' => 'Późniejsza korekta']);
    expect($html($w->record($draft['id'])['payload']))->not->toContain('Późniejsza korekta');
    expect(fn () => $docs->issue($w, $draft, 'test'))->toThrow(ValidationException::class);
    $bytes = $docs->render($draft['payload']);
    expect($bytes)->toStartWith('%PDF');
    if (getenv('ENMS_QA')) {
        file_put_contents(base_path('tmp/enms-self-assessment.pdf'), $bytes);
    }
});
