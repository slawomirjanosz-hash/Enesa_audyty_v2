<?php

use App\Services\Enms\Authorization;
use App\Services\Enms\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

function enmsGrant(): array
{
    return ['class' => 'ORG', 'activity' => 'zatwierdzenie_rezultatu', 'user_id' => 17, 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31', 'scope_version' => 'scope-1', 'required_steps' => 1];
}

function enmsContext(): array
{
    return ['class' => 'ORG', 'activity' => 'zatwierdzenie_rezultatu', 'user_id' => 17, 'date' => '2026-11-15', 'scope_version' => 'scope-1', 'approved_steps' => ['approval-1']];
}

test('enms evidence status follows verification of the exact evidence version', function () {
    $service = new Authorization;
    $verified = ['evidence_version' => 1, 'dostepnosc' => 'okazany', 'wiarygodnosc' => 'potwierdzona'];
    expect($service->status(null, $verified))->toBe('zgloszone')
        ->and($service->status(['version' => 1], null))->toBe('udokumentowane')
        ->and($service->status(['version' => 1], $verified))->toBe('zweryfikowane')
        ->and($service->status(['version' => 2], $verified))->toBe('udokumentowane')
        ->and($service->status(['version' => 1], array_replace($verified, ['dostepnosc' => 'nieokazany'])))->toBe('udokumentowane');
});

test('enms minimum authority follows annex rather than the superseded proposal', function (string $class, string $status, bool $allowed) {
    $grant = array_replace(enmsGrant(), ['class' => $class, 'limit_cents' => 10000, 'currency' => 'PLN']);
    $context = array_replace(enmsContext(), ['class' => $class, 'amount_cents' => 10000, 'currency' => 'PLN']);
    expect((new Authorization)->check($grant, $status, $context)['allowed'])->toBe($allowed);
})->with([
    ['USTR', 'udokumentowane', false], ['USTR', 'zweryfikowane', true],
    ['NAKL+', 'udokumentowane', false], ['NAKL+', 'zweryfikowane', true],
    ['NAKL', 'udokumentowane', false], ['NAKL', 'zweryfikowane', true],
    ['ORG', 'udokumentowane', false], ['ORG', 'zweryfikowane', true],
    ['OPER', 'zgloszone', false], ['OPER', 'udokumentowane', true],
    ['SPRAW', 'zgloszone', false], ['SPRAW', 'udokumentowane', true],
]);

test('enms rejects expired revoked foreign or insufficient authority with reasons', function (array $grantChanges, array $contextChanges) {
    $result = (new Authorization)->check(array_replace(enmsGrant(), $grantChanges), 'zweryfikowane', array_replace(enmsContext(), $contextChanges));
    expect($result['allowed'])->toBeFalse()->and($result['reason'])->not->toBeEmpty();
})->with([
    [['valid_until' => '2026-11-14'], []],
    [['valid_from' => '2026-11-16'], []],
    [['revocation' => ['date' => '2026-11-15']], []],
    [[], ['user_id' => 18]],
    [[], ['scope_version' => 'scope-2']],
    [[], ['activity' => 'wykonanie']],
    [['areas' => ['boiler']], ['area' => 'production']],
    [['exclusions' => ['production']], ['area' => 'production']],
    [['required_steps' => 2], ['approved_steps' => ['same', 'same']]],
]);

test('enms revocation does not retroactively invalidate a historical action', function () {
    $grant = array_replace(enmsGrant(), ['revocation' => ['date' => '2026-11-16']]);
    expect((new Authorization)->check($grant, 'zweryfikowane', enmsContext())['allowed'])->toBeTrue();
});

test('enms financial authorization checks currency annual usage and the exact limit', function () {
    $grant = array_replace(enmsGrant(), ['class' => 'NAKL', 'limit_cents' => 10000, 'limit_period' => 'year', 'currency' => 'PLN']);
    $context = array_replace(enmsContext(), ['class' => 'NAKL', 'amount_cents' => 2000, 'currency' => 'PLN', 'spent_cents' => 8000]);
    $service = new Authorization;
    expect($service->check($grant, 'zweryfikowane', $context)['allowed'])->toBeTrue()
        ->and($service->check($grant, 'zweryfikowane', array_replace($context, ['amount_cents' => 2001]))['allowed'])->toBeFalse()
        ->and($service->check($grant, 'zweryfikowane', array_replace($context, ['currency' => 'EUR']))['allowed'])->toBeFalse()
        ->and($service->check($grant, 'zweryfikowane', array_replace($context, ['spent_cents' => null]))['allowed'])->toBeFalse();
});

test('enms independent assessment excludes the author executor owner and area manager', function () {
    $task = ['owner_id' => 1, 'recommendation_author_id' => 2, 'executor_id' => 3, 'area_responsible_ids' => [4]];
    $service = new Authorization;
    foreach ([1, 2, 3, 4] as $user) {
        expect($service->effectivenessConflicts($user, $task))->toHaveCount(1);
    }
    expect($service->effectivenessConflicts(5, $task))->toBe([]);
});

test('enms workspace keeps historical versions and never changes old questionnaire answers', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $original = $profile->fresh()->getRawOriginal();
    $workspace = Workspace::for($staff, $audit, $profile, false);
    expect($workspace->revision())->toBe(0)->and($workspace->records())->toBe([]);
    $record = $workspace->append(0, 'task', null, 'ZAD-1', ['requirements' => ['5.1/a', '6.2/goal'], 'state' => 'planned'], '2026-11-15', 'scope-1');
    $workspace->append(1, 'task', $record['id'], 'ZAD-1', ['requirements' => ['5.1/a', '6.2/goal'], 'state' => 'done'], '2026-11-15', 'scope-2');
    expect($workspace->revision())->toBe(2)
        ->and($workspace->records('task'))->toHaveCount(1)
        ->and($workspace->record($record['id'])['payload']['state'])->toBe('done')
        ->and($workspace->records('task', 1)[$record['id']]['payload']['state'])->toBe('planned')
        ->and($workspace->records('task', 1)[$record['id']]['scope_version'])->toBe('scope-1')
        ->and($workspace->history())->toHaveCount(2)
        ->and($profile->fresh()->getRawOriginal())->toBe($original);
    expect(fn () => $workspace->append(1, 'task', $record['id'], 'ZAD-1', [], '2026-11-15', 'scope-2'))->toThrow(HttpException::class);
    expect($workspace->revision())->toBe(2)->and(DB::table('enms_record_versions')->count())->toBe(2);
});

test('enms workspace denies foreign company and foreign record identifiers', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    [$otherAudit, $otherClient, $otherStaff, $otherProfile] = systemFixture();
    $first = Workspace::for($staff, $audit, $profile, false);
    $second = Workspace::for($otherClient, $otherAudit, $otherProfile, true);
    $record = $first->append(0, 'evidence', null, 'DOW-1', ['title' => 'Private'], '2026-11-15', 'scope-1');
    // Both tenants must have persisted workspaces; an empty workspace returns before the scoped query.
    $otherRecord = $second->append(0, 'evidence', null, 'DOW-1', ['title' => 'Other private'], '2026-11-15', 'scope-1');
    expect(array_keys($second->records()))->toBe([$otherRecord['id']])
        ->and(array_keys($first->records()))->toBe([$record['id']]);
    expect(fn () => $second->record($record['id']))->toThrow(HttpException::class);
    expect(fn () => $second->append(1, 'evidence', $record['id'], 'DOW-1', [], '2026-11-15', 'scope-1'))->toThrow(HttpException::class);
    expect(fn () => Workspace::for($otherClient, $audit, $profile, true))->toThrow(ModelNotFoundException::class);
    expect(fn () => Workspace::for($staff, $audit, $otherProfile, false))->toThrow(HttpException::class);
    expect(DB::table('enms_workspaces')->count())->toBe(2);
});

test('enms rolls back the entire mutation when journal persistence fails', function () {
    [$audit, $client, $staff, $profile] = systemFixture();
    $workspace = Workspace::for($staff, $audit, $profile, false);
    $armed = true;
    DB::listen(function (QueryExecuted $event) use (&$armed) {
        if ($armed && str_contains($event->sql, 'insert into "enms_journal"')) {
            throw new RuntimeException('Simulated journal failure');
        }
    });
    try {
        $workspace->append(0, 'assessment', null, 'OC-1', ['state' => 'wtoku'], '2026-11-15', 'scope-1');
        test()->fail('The injected persistence failure was not raised.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulated journal failure');
    } finally {
        $armed = false;
    }
    expect($workspace->revision())->toBe(0)
        ->and(DB::table('enms_workspaces')->count())->toBe(0)
        ->and(DB::table('enms_records')->count())->toBe(0)
        ->and(DB::table('enms_record_versions')->count())->toBe(0)
        ->and(DB::table('enms_journal')->count())->toBe(0);
});

test('enms renumbering preserves the reference graph and historical symbols', function () {
    [$audit,$client,$staff,$profile] = systemFixture();
    $w = Workspace::for($staff, $audit, $profile, false);
    $proof = $w->append(0, 'evidence', null, 'DOW-1', ['title' => 'Dokument'], '2026-11-15', 'scope-1');
    $task = $w->append(1, 'task', null, 'ZAD-1', ['evidence_id' => $proof['id'], 'requirements' => ['requirement-a', 'requirement-b']], '2026-11-15', 'scope-1');
    $coverage = $w->append(2, 'coverage', null, 'POK-1', ['evidence_id' => $proof['id'], 'criterion_id' => 'criterion-a'], '2026-11-15', 'scope-1');
    $before = $w->records();
    foreach ($before as $r) {
        $w->append($w->revision(), $r['kind'], $r['id'], 'NOWY-'.$r['symbol'], $r['payload'], $r['library_version'], $r['scope_version']);
    }
    foreach ($before as $id => $r) {
        expect($w->record($id)['payload'])->toBe($r['payload'])
            ->and($w->records(null, 3)[$id]['symbol'])->toBe($r['symbol']);
    }
    expect($w->record($task['id'])['payload']['evidence_id'])->toBe($proof['id'])
        ->and($w->record($coverage['id'])['payload']['evidence_id'])->toBe($proof['id'])
        ->and(count($w->records()))->toBe(count($before));
});
