<?php

use App\Models\IsoSystemReview;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function enmsReadinessLinks(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);

    return array_map(fn ($node) => $node->getAttribute('href'), iterator_to_array($xpath->query('//div[contains(@class,"enms-readiness-issue")]/a')));
}

test('enms readiness links open each unanswered obligation in the current audit and role without writes', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $before = $s->readiness($w);
    foreach ([[$client, 'client.audits.enms.'], [$staff, 'audits.enms.']] as [$actor, $prefix]) {
        $page = $this->actingAs($actor)->get(route($prefix.'show', [$audit, $profile, 'tab' => 'spojnosc']))->assertOk();
        $links = enmsReadinessLinks($page->getContent());
        expect($links)->toHaveCount(13);
        foreach (range('a', 'm') as $code) {
            expect($links)->toContain(route($prefix.'show', [$audit, $profile, 'tab' => 'obowiazki']).'#req-'.$code.'-state');
        }
        $answers = $this->get(route($prefix.'show', [$audit, $profile, 'tab' => 'obowiazki']))->assertOk();
        foreach ($links as $link) {
            $answers->assertSee('id="'.parse_url($link, PHP_URL_FRAGMENT).'"', false);
        }
    }
    expect($s->readiness($w))->toBe($before)->and($w->revision())->toBe(0)->and($w->records())->toBe([]);
});

test('enms readiness links identify plans details citations evidence explanations and auditor questions', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    foreach (['a' => ['stan' => 'jest'], 'b' => ['stan' => 'czesciowo', 'plan' => ['opis' => 'Plan bez terminu']], 'c' => ['stan' => 'niema'], 'PRZ-14' => ['stan' => 'niema']] as $code => $payload) {
        $s->saveDeclaration($w, $w->revision(), $s->sources($w)['hash'], $s->library->id('requirement', $code), $payload);
    }
    // A changed 4.3 snapshot must link back to the source block in the declaration.
    IsoSystemReview::where('audit_id', $audit->id)->where('section', '4-3')->update(['lock_version' => 2]);
    $proof = enmsSave($w, 'evidence', ['rodzaj' => 'DOK', 'tytul' => 'Dowód do uzupełnienia']);
    $question = enmsSave($w, 'question', ['requirement_id' => $s->library->id('requirement', 'a'), 'pytanie' => 'Kto przyjął zakres?']);
    $before = $w->records();
    $originalReadiness = $s->readiness($w);
    $readiness = $s->readiness($w, withTargets: true);
    expect(array_diff_key($readiness, ['missingTargets' => true]))->toBe($originalReadiness)
        ->and($readiness['missingTargets'])->toHaveCount(count($readiness['missing']));
    $anchors = array_column($readiness['missingTargets'], 'anchor');
    expect($anchors)->toContain('req-b-plan-termin', 'req-c-plan-opis', 'req-PRZ-14-plan-opis', 'req-a-evidence', 'explanation-NS-prz00', 'question-'.$question['id'], 'edit-evidence-field-data');

    foreach ([[$client, 'client.audits.enms.'], [$staff, 'audits.enms.']] as [$actor, $prefix]) {
        $page = $this->actingAs($actor)->get(route($prefix.'show', [$audit, $profile, 'tab' => 'spojnosc']))->assertOk();
        $links = enmsReadinessLinks($page->getContent());
        expect($links)->toHaveCount(count($readiness['missing']));
        $pages = [];
        foreach ($links as $link) {
            [$url, $anchor] = explode('#', $link);
            $pages[$url] ??= $this->get($url)->assertOk()->getContent();
            expect(substr_count($pages[$url], 'id="'.$anchor.'"'))->toBe(1);
            expect($url)->toStartWith(route($prefix.'show', [$audit, $profile]));
        }
        $evidenceUrl = route($prefix.'show', [$audit, $profile, 'tab' => 'dowody', 'edit' => $proof['id']]);
        expect($pages[$evidenceUrl])->toContain('value="'.$proof['id'].'"', 'Dowód do uzupełnienia');
    }
    expect($w->records())->toBe($before)->and($s->readiness($w))->toBe($originalReadiness);
    if (getenv('ENMS_BROWSER_QA')) {
        enmsBrowserExport('enms-readiness-browser', [$audit, $client, $staff, $profile]);
    }
});

test('enms readiness navigation preserves read only and foreign audit boundaries', function () {
    [$audit, $client, $staff, $profile, $w, $s] = enmsFixture();
    $proof = enmsSave($w, 'evidence', ['tytul' => 'Dowód bez danych']);
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::findOrCreate('employee'));
    $viewer->givePermissionTo(Permission::findOrCreate('audits.view'));
    $page = $this->actingAs($viewer)->get(route('audits.enms.show', [$audit, $profile, 'tab' => 'spojnosc']))->assertOk();
    $links = enmsReadinessLinks($page->getContent());
    $url = route('audits.enms.show', [$audit, $profile, 'tab' => 'dowody']);
    expect($links)->toContain($url.'#record-'.$proof['id']);
    foreach ($links as $link) {
        expect($link)->not->toContain('edit=');
    }
    $this->get($url)->assertOk()->assertSee('id="record-'.$proof['id'].'"', false)->assertDontSee('data-enms-register', false);
    $this->get(route('audits.enms.show', [$audit, $profile, 'tab' => 'obowiazki']))->assertOk()->assertSee('<fieldset disabled>', false);
    $before = $w->records();
    [$otherAudit, $otherClient, $otherStaff, $otherProfile] = enmsFixture();
    $this->actingAs($otherClient)->get(route('client.audits.enms.show', [$audit, $profile, 'tab' => 'obowiazki']))->assertNotFound();
    $this->actingAs($client)->get(route('client.audits.enms.show', [$audit, $otherProfile, 'tab' => 'dowody', 'edit' => $proof['id']]))->assertNotFound();
    expect($w->records())->toBe($before);
});
