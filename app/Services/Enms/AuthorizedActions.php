<?php

namespace App\Services\Enms;

use App\Models\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AuthorizedActions
{
    public function __construct(private readonly LeadershipWorkflow $workflow, private readonly Authorization $authorization) {}

    public function apply(Workspace $w, int $revision, string $hash, string $operation, string $targetId, string $grantId, array $input): bool
    {
        abort_unless($w->writable, 403);
        try {
            return DB::transaction(function () use ($w, $revision, $hash, $operation, $targetId, $grantId, $input) {
                Audit::whereKey($w->audit->id)->lockForUpdate()->firstOrFail();
                $source = $this->workflow->sources($w);
                abort_unless($w->revision() === $revision && $source['hash'] === $hash, 409, 'Dane lub źródła zmieniły się.');
                if ($source['scope_version'] === 'scope-unavailable') {
                    $this->workflow->invalid('Brakuje zakresu EnMS. Uzupełnij zakres przed użyciem upoważnienia.');
                }
                $target = $w->record($targetId);
                $grant = $w->record($grantId, 'authority');
                $p = $target['payload'];
                $data = Validator::make($input, ['evidence_id' => 'required|uuid', 'text' => 'required|string|max:12000', 'effective_from' => 'nullable|date_format:Y-m-d', 'wynik' => ['nullable', Rule::in(['skuteczne', 'nieskuteczne', 'zawczesnie'])], 'metoda' => 'nullable|string|max:12000', 'competence_id' => 'nullable|uuid', 'document_hash' => 'nullable|string|size:64', 'rule_state' => ['nullable', Rule::in(['przyjeta', 'nieprzyjeta'])], 'decision_state' => ['nullable', Rule::in(['podjeta', 'odroczona', 'odrzucona'])]])->validate();
                $evidence = $w->record($data['evidence_id'], 'evidence');
                $kind = match ($operation) {
                    'resolve_rule' => 'rule',
                    'approve_deadline' => 'deadline', 'resolve_decision' => 'decision',
                    'issue_document' => 'document_draft',
                    'approve_document', 'activate_document', 'withdraw_document' => 'document',
                    'revoke_authority' => 'authority', 'assess_effectiveness', 'complete_task', 'approve_task_result' => 'task', 'approve_effectiveness' => 'effectiveness',
                    default => abort(404),
                };
                abort_unless($target['kind'] === $kind, 422);
                $actionType = $kind === 'deadline' ? ($w->record($p['target_id'])['payload']['action_type'] ?? null) : ($p['action_type'] ?? null);
                if ($operation === 'approve_task_result') {
                    $actionType = $p['result_action_type'] ?? null;
                }
                $class = match ($operation) {
                    'resolve_rule', 'approve_document', 'activate_document', 'withdraw_document', 'revoke_authority' => 'USTR',
                    'assess_effectiveness', 'approve_effectiveness', 'issue_document' => 'SPRAW',
                    default => config('enms.action_type_classes.'.$actionType),
                };
                if (! $class) {
                    $this->workflow->invalid('Rodzaj działania nie ma klasy w bibliotece.');
                }
                if ($class === 'NAKL' && $grant['payload']['class'] === 'NAKL+') {
                    $class = 'NAKL+';
                }
                $contextTarget = $kind === 'deadline' ? $w->record($p['target_id'])['payload'] : $p;
                $required = (int) $grant['payload']['required_steps'];
                $grantPayload = $this->grantPayload($grant['payload']);
                $basis = empty($grantPayload['evidence_id']) ? null : $w->record($grantPayload['evidence_id'], 'evidence');
                $verification = $basis ? $this->workflow->one($w, 'verification', 'evidence_id', $basis['id']) : null;
                $status = $this->authorization->status($basis, $verification['payload'] ?? null);
                $amount = $this->cents($contextTarget['amount'] ?? null);
                $used = 0;
                foreach ($w->records('authorized_action') as $action) {
                    if (in_array($grantId, $action['payload']['authorization_ids'] ?? [], true) && str_starts_with($action['created_at'], now()->format('Y'))) {
                        $used += $action['payload']['amount_cents'] ?? 0;
                    }
                }
                $context = ['class' => $class, 'activity' => $operation === 'complete_task' ? 'wykonanie' : 'zatwierdzenie_rezultatu', 'user_id' => $w->actor->id, 'date' => today()->toDateString(), 'scope_version' => $source['scope_version'], 'area' => $contextTarget['area'] ?? null, 'amount_cents' => $amount, 'currency' => $contextTarget['currency'] ?? null, 'spent_cents' => $used, 'approved_steps' => range(1, $required)];
                $check = $this->authorization->check($grantPayload, $status, $context);
                if (! $check['allowed']) {
                    $this->workflow->invalid($check['reason'].$this->appropriateAuthorities($w, $context));
                }
                if ($operation === 'complete_task' && ($p['stan'] ?? '') === 'wykonane') {
                    $this->workflow->invalid('Wykonanie tego zadania już zapisano.');
                }
                if ($operation === 'complete_task' && empty($p['result_action_type'])) {
                    $this->workflow->invalid('Przed zapisaniem wykonania określ skutek zatwierdzenia rezultatu w zadaniu. Umożliwi to wskazanie właściwej klasy upoważnienia.');
                }
                if ($operation === 'approve_task_result' && (($p['stan'] ?? '') !== 'wykonane' || ! empty($p['result_approved']))) {
                    $this->workflow->invalid('Zatwierdzenie rezultatu wymaga wykonanego, jeszcze niezatwierdzonego zadania.');
                }
                if ($operation === 'approve_deadline' && $p['origin'] === 'zatwierdzony') {
                    $this->workflow->invalid('Ten termin już zatwierdzono.');
                }
                if ($operation === 'resolve_decision' && $p['state'] !== 'dopodjecia') {
                    $this->workflow->invalid('To postanowienie jest już rozstrzygnięte.');
                }
                if ($operation === 'revoke_authority' && ! empty($p['revocation'])) {
                    $this->workflow->invalid('Upoważnienie już cofnięto.');
                }
                if ($operation === 'resolve_rule' && (empty($data['rule_state']) || ! empty($p['resolution']))) {
                    $this->workflow->invalid('Wybierz rozstrzygnięcie nierozstrzygniętej zasady. Zmiana przyjętej zasady wymaga nowej propozycji treści.');
                }
                if ($kind === 'document') {
                    if ($p['type'] !== 'karta') {
                        $this->workflow->invalid('Ten dokument nie jest Kartą przywództwa.');
                    }
                    if (($data['document_hash'] ?? '') !== $p['file_hash']) {
                        $this->workflow->invalid('Potwierdź, że dowód decyzji obejmuje dokładnie wskazaną wersję i sumę kontrolną pliku.');
                    }
                    if ($this->documentState($w, $target)['status'] === 'Wycofany') {
                        $this->workflow->invalid('Wersję wycofano. Wydaj nową wersję.');
                    }
                    if ($operation === 'approve_document' && ($evidence['payload']['data'] ?? '') < substr($target['created_at'], 0, 10)) {
                        $this->workflow->invalid('Dowód wcześniejszy niż wydanie nie zatwierdza treści tej wersji.');
                    }
                    if ($operation === 'approve_document') {
                        $rules = array_filter($p['snapshot']['records'], fn ($r) => $r['kind'] === 'rule');
                        foreach ($this->workflow->library->requirements() as $id => $requirement) {
                            if ($requirement['normative'] && ! collect($rules)->contains(fn ($r) => $r['payload']['requirement_id'] === $id && isset($r['payload']['resolution']))) {
                                $this->workflow->invalid('Wydana wersja nie zawiera rozstrzygnięcia zasady '.$requirement['iso'].'. Każda zasada wymaga decyzji, także odmownej. Przygotuj nowe wydanie po rozstrzygnięciu zasad.');
                            }
                        }
                    }
                    if ($operation === 'activate_document' && ! $this->documentState($w, $target)['approved']) {
                        $this->workflow->invalid('Najpierw zatwierdź tę wersję dokumentu.');
                    }
                }
                if ($operation === 'assess_effectiveness') {
                    if (($p['stan'] ?? '') !== 'wykonane') {
                        $this->workflow->invalid('Skuteczność ocenia się po wykonaniu zadania.');
                    }
                    $mode = config('enms.effectiveness.'.$p['istotnosc'].'.'.$p['risk']);
                    if (! $mode) {
                        $this->workflow->invalid('Nie ustalono trybu oceny dla istotności i ryzyka zadania.');
                    }
                    $p['area_responsible_ids'] = $this->areaOwners($w, $p);
                    $conflicts = $this->authorization->effectivenessConflicts($w->actor->id, $p);
                    if ($mode === 'niezalezna' && $conflicts) {
                        $this->workflow->invalid('Konflikt oceniającego: '.implode(', ', $conflicts).'.');
                    }
                    if (empty($data['competence_id']) || empty($data['wynik']) || empty($data['metoda'])) {
                        $this->workflow->invalid('Podaj wynik, metodę i dowód kompetencji.');
                    }
                    $w->record($data['competence_id'], 'evidence');
                    $data['mode'] = $mode;
                    $data['supervised'] = $mode !== 'samoocena';
                }
                if ($operation === 'approve_effectiveness' && (($p['mode'] ?? '') !== 'samoocena' || ! empty($p['supervised']) || $target['actor_id'] === $w->actor->id)) {
                    $this->workflow->invalid('Nadzór nad samooceną wymaga zatwierdzenia przez inną uprawnioną osobę.');
                }
                $key = hash('sha256', json_encode([$operation, $targetId, $target['version'], $data, $source['scope_version']], JSON_THROW_ON_ERROR));
                if ($this->workflow->one($w, 'authorized_action', 'key', $key)) {
                    $this->workflow->invalid('Ta czynność została już wykonana.');
                }
                $approval = ['key' => $key, 'target_id' => $targetId, 'target_version' => $target['version'], 'operation' => $operation, 'grant_id' => $grantId, 'grant_version' => $grant['version'], 'step' => (int) $grantPayload['step'], 'required_steps' => $required, 'evidence_id' => $data['evidence_id'], 'data' => $data];
                $existing = $this->workflow->one($w, 'approval', 'actor_key', $key.'/'.$grantId);
                $approval['actor_key'] = $key.'/'.$grantId;
                $w->append($revision++, 'approval', $existing['id'] ?? null, 'ZG-'.$key.'-'.substr(hash('sha256', $grantId), 0, 8), $approval, config('enms.library'), $source['scope_version'], $grantId);
                $steps = [];
                foreach ($w->records('approval') as $a) {
                    $ap = $a['payload'];
                    if ($ap['key'] !== $key || $ap['required_steps'] !== $required) {
                        continue;
                    }
                    $ag = $w->record($ap['grant_id'], 'authority');
                    if ($ag['version'] !== $ap['grant_version']) {
                        continue;
                    }
                    $eb = empty($ag['payload']['evidence_id']) ? null : $w->record($ag['payload']['evidence_id'], 'evidence');
                    $ve = $eb ? $this->workflow->one($w, 'verification', 'evidence_id', $eb['id']) : null;
                    $spent = 0;
                    foreach ($w->records('authorized_action') as $action) {
                        if (in_array($ag['id'], $action['payload']['authorization_ids'] ?? [], true) && str_starts_with($action['created_at'], now()->format('Y'))) {
                            $spent += $action['payload']['amount_cents'] ?? 0;
                        }
                    }
                    $ctx = array_replace($context, ['user_id' => $a['actor_id'], 'spent_cents' => $spent]);
                    if ($this->authorization->check($this->grantPayload($ag['payload']), $this->authorization->status($eb, $ve['payload'] ?? null), $ctx)['allowed']) {
                        $steps[(int) $ap['step']] = $ag['id'];
                    }
                }
                if (array_diff(range(1, $required), array_keys($steps))) {
                    return false;
                }
                if ($operation === 'issue_document') {
                    app(LeadershipDocuments::class)->issue($w, $target, $grantId);
                    $revision = $w->revision();
                } elseif ($operation === 'approve_deadline') {
                    $p['origin'] = 'zatwierdzony';
                    $p['evidence_id'] = $data['evidence_id'];
                    $p['authorization_ids'] = array_values($steps);
                    $w->append($revision++, $kind, $targetId, $target['symbol'], $p, config('enms.library'), $source['scope_version'], $grantId);
                } elseif ($operation === 'resolve_decision') {
                    if (empty($data['decision_state'])) {
                        $this->workflow->invalid('Wybierz wynik decyzji.');
                    }
                    $p['state'] = $data['decision_state'];
                    $p['resolution'] = $data['text'];
                    $p['evidence_id'] = $data['evidence_id'];
                    $w->append($revision++, $kind, $targetId, $target['symbol'], $p, config('enms.library'), $source['scope_version'], $grantId);
                } elseif ($operation === 'resolve_rule') {
                    $p['resolution'] = ['state' => $data['rule_state'], 'text' => $data['text'], 'evidence_id' => $data['evidence_id'], 'actor_id' => $w->actor->id, 'authorization_ids' => array_values($steps), 'date' => now()->toIso8601String()];
                    $w->append($revision++, $kind, $targetId, $target['symbol'], $p, config('enms.library'), $source['scope_version'], $grantId);
                } elseif ($operation === 'revoke_authority') {
                    $p['revocation'] = ['date' => today()->toDateString(), 'reason' => $data['text'], 'evidence_id' => $data['evidence_id'], 'actor_id' => $w->actor->id];
                    $w->append($revision++, $kind, $targetId, $target['symbol'], $p, config('enms.library'), $source['scope_version'], $grantId);
                } elseif ($operation === 'complete_task' || $operation === 'approve_task_result') {
                    if ($operation === 'complete_task') {
                        $p['stan'] = 'wykonane';
                        $p['executor_id'] = $w->actor->id;
                        $p['evidence_id'] = $data['evidence_id'];
                        $p['execution_authorization_ids'] = array_values($steps);
                    } else {
                        $p['result_approved'] = ['actor_id' => $w->actor->id, 'evidence_id' => $data['evidence_id'], 'authorization_ids' => array_values($steps), 'date' => now()->toIso8601String()];
                    }
                    $w->append($revision++, $kind, $targetId, $target['symbol'], $p, config('enms.library'), $source['scope_version'], $grantId);
                } elseif ($operation === 'approve_effectiveness') {
                    $p['supervised'] = true;
                    $p['supervision'] = ['actor_id' => $w->actor->id, 'authorization_ids' => array_values($steps), 'evidence_id' => $data['evidence_id'], 'date' => now()->toIso8601String()];
                    $w->append($revision++, $kind, $targetId, $target['symbol'], $p, config('enms.library'), $source['scope_version'], $grantId);
                } elseif ($operation === 'assess_effectiveness') {
                    $w->append($revision++, 'effectiveness', null, 'SKU-'.(count($w->records('effectiveness')) + 1), $data + ['task_id' => $targetId, 'task_version' => $target['version'], 'authorization_ids' => array_values($steps)], config('enms.library'), $source['scope_version'], $grantId);
                } else {
                    if ($operation === 'activate_document' && empty($data['effective_from'])) {
                        $this->workflow->invalid('Podaj datę wejścia w życie.');
                    }
                    $w->append($revision++, 'document_approval', null, 'AKT-'.(count($w->records('document_approval')) + 1), $data + ['document_id' => $targetId, 'operation' => $operation, 'file_hash' => $p['file_hash']], config('enms.library'), $source['scope_version'], $grantId);
                }
                $w->append($revision, 'authorized_action', null, 'CZYN-'.(count($w->records('authorized_action')) + 1), ['key' => $key, 'operation' => $operation, 'target_id' => $targetId, 'grant_id' => $grantId, 'amount_cents' => $operation === 'resolve_decision' && ($data['decision_state'] ?? '') === 'podjeta' ? $amount : 0, 'authorization_ids' => array_values($steps), 'evidence_id' => $data['evidence_id']], config('enms.library'), $source['scope_version'], $grantId);

                return true;
            });
        } catch (ValidationException $exception) {
            // A denial must survive rollback of the attempted mutation.
            $w->denial($operation, $targetId, $grantId, implode(' ', $exception->validator->errors()->all()));
            throw $exception;
        }
    }

    public function grantPayload(array $p): array
    {
        $p['limit_cents'] = $this->cents($p['limit_amount'] ?? null);
        $p['areas'] = empty($p['area']) ? [] : [$p['area']];
        $p['exclusions'] = empty($p['exclusion']) ? [] : [$p['exclusion']];
        if (! empty($p['substitute_id'])) {
            $p['substitute'] = ['user_id' => $p['substitute_id'], 'from' => $p['substitute_from'], 'until' => $p['substitute_until'], 'evidence_id' => $p['substitute_evidence_id']];
        }

        return $p;
    }

    private function areaOwners(Workspace $w, array $task): array
    {
        $owners = $task['area_responsible_ids'] ?? [];
        foreach ($w->records('authority') as $grant) {
            $p = $grant['payload'];
            if (filled($task['area'] ?? null) && ($p['area'] ?? null) === $task['area'] && ! isset($p['revocation'])) {
                $owners[] = (int) $p['user_id'];
            }
        }

        return array_values(array_unique($owners));
    }

    private function appropriateAuthorities(Workspace $w, array $context): string
    {
        $candidates = [];
        $users = $this->workflow->options($w)['users'];
        foreach ($w->records('authority') as $record) {
            $p = $record['payload'];
            $financial = in_array($context['class'], ['NAKL', 'NAKL+']) && in_array($p['class'], ['NAKL', 'NAKL+']);
            if ((! $financial && $p['class'] !== $context['class']) || $p['activity'] !== $context['activity']) {
                continue;
            }
            $evidence = empty($p['evidence_id']) ? null : $w->record($p['evidence_id'], 'evidence');
            $verification = $evidence ? $this->workflow->one($w, 'verification', 'evidence_id', $evidence['id']) : null;
            $spent = 0;
            foreach ($w->records('authorized_action') as $action) {
                if (in_array($record['id'], $action['payload']['authorization_ids'] ?? [], true) && str_starts_with($action['created_at'], now()->format('Y'))) {
                    $spent += $action['payload']['amount_cents'] ?? 0;
                }
            }
            $candidateContext = array_replace($context, ['class' => $p['class'], 'user_id' => $p['user_id'], 'spent_cents' => $spent, 'approved_steps' => range(1, (int) $p['required_steps'])]);
            if (! $this->authorization->check($this->grantPayload($p), $this->authorization->status($evidence, $verification['payload'] ?? null), $candidateContext)['allowed']) {
                continue;
            }
            $candidates[] = $record['symbol'].' ('.($users[$p['user_id']] ?? 'osoba wskazana w upoważnieniu').')';
        }

        return $candidates ? ' Sprawdź właściwe upoważnienia: '.implode(', ', $candidates).'.' : ' Brak innego zgłoszonego upoważnienia tej klasy i czynności w tym zakresie.';
    }

    private function cents(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $parts = explode('.', str_replace(',', '.', (string) $value));

        return ((int) $parts[0]) * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public function documentState(Workspace $w, array $document): array
    {
        $state = ['approved' => false, 'status' => 'Wydany, niezatwierdzony', 'effective_from' => null];
        $acts = array_reverse(array_values($w->records('document_approval')));
        foreach ($acts as $act) {
            $p = $act['payload'];
            if ($p['document_id'] !== $document['id'] || $p['file_hash'] !== $document['payload']['file_hash']) {
                continue;
            }
            if ($p['operation'] === 'approve_document') {
                $state = ['approved' => true, 'status' => 'Zatwierdzony', 'effective_from' => null];
            }
            if ($p['operation'] === 'activate_document' && $state['approved']) {
                $state = ['approved' => true, 'status' => $p['effective_from'] <= today()->toDateString() ? 'Obowiązujący' : 'Zatwierdzony — oczekuje na wejście w życie', 'effective_from' => $p['effective_from']];
            }
            if ($p['operation'] === 'withdraw_document') {
                $state = ['approved' => false, 'status' => 'Wycofany', 'effective_from' => null];
            }
        }

        return $state;
    }
}
