<?php

namespace App\Services\Enms;

use App\Models\Audit;
use App\Models\IsoSectionDocument;
use App\Models\IsoSystemReview;
use App\Models\User;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class LeadershipWorkflow
{
    public function __construct(public readonly LeadershipLibrary $library, public readonly Registers $registers) {}

    public function sources(Workspace $w): array
    {
        $w->profile->refresh();
        $rows = IsoSystemReview::where('audit_id', $w->audit->id)->where('site_id', $w->profile->site_id)->whereIn('section', ['4-3', '4-4', '5-3', '6-2', '6-3', '6-4', '6-5', '9-1-1', '9-1-2'])->get()->keyBy('section');
        $map = ['ZAKRES' => ['4-3', 'ZAKRES'], 'CELE' => ['6-2', 'CELE'], 'ENPI' => ['6-4', 'KARTY'], 'ENB' => ['6-5', 'KARTY'], 'WYNIKI' => ['9-1-1', 'WSKAZNIKI'], 'ROLE' => ['5-3', 'ROLE'], 'SEU' => ['6-3', 'SEU']];
        $citations = [];
        foreach ($map as $key => [$section, $root]) {
            $row = $rows[$section] ?? null;
            $document = $row?->document_id ? IsoSectionDocument::where('audit_id', $w->audit->id)->where('section_id', $section)->find($row->document_id) : null;
            $current = $row && $row->source_hash === app(IsoSystemQuestionnaire::class)->sources($w->profile, $section)['hash'];
            $citations[$key] = ['section' => $section, 'value' => $row?->answers[$root] ?? null, 'source_id' => $row?->id, 'source_version' => $row?->lock_version, 'document_id' => $document?->id, 'document_version' => $document?->version_number, 'read_status' => $row ? 'potwierdzony' : 'niepotwierdzony', 'approved' => (bool) ($current && $document && $row?->client_approval && $row?->auditor_approval && $row?->status === 'approved')];
        }
        $scope = $rows['4-3'] ?? null;
        $fingerprints = $rows->map(fn ($r) => $r->only(['id', 'lock_version', 'source_hash', 'status', 'document_id']))->all();

        return ['citations' => $citations, 'scope_version' => $scope ? 'scope-'.$scope->id.'-'.$scope->lock_version : 'scope-unavailable', 'scope' => $scope?->answers['ZAKRES'] ?? null, 'hash' => hash('sha256', json_encode([$fingerprints, $citations, $w->profile->id, $w->profile->lock_version], JSON_THROW_ON_ERROR)), 'system_published' => (bool) (($rows['4-4'] ?? null)?->document_id)];
    }

    public function options(Workspace $w): array
    {
        $options = ['requirements' => [], 'criteria' => [], 'users' => [], 'documents' => [], 'sources' => [], 'targets' => []];
        foreach ($this->library->positions() as $id => $r) {
            $options['requirements'][$id] = ($r['iso'] ?? $r['kod']).' · '.$r['title'];
            if ($r['normative']) {
                $options['normative_requirements'][$id] = $options['requirements'][$id];
            }
        }
        foreach ($this->library->criteria() as $id => $r) {
            $options['criteria'][$id] = $r['id'].' · '.$r['nazwa'];
        }
        $options['task_requirements'] = $options['requirements'] + app(TaskReferences::class)->external();
        $options['citations'] = [];
        foreach ($w->records('declaration') as $row) {
            foreach ($row['payload']['citations'] ?? [] as $field => $citation) {
                $label = collect($this->library->requirement($row['payload']['requirement_id'])['pola'])->firstWhere('klucz', $field)['etykieta'] ?? 'Cytat';
                $options['citations'][$row['payload']['requirement_id'].'/'.$field] = ($options['requirements'][$row['payload']['requirement_id']] ?? 'Pozycja').' · '.$label.' (punkt '.str_replace('-', '.', $citation['section']).')';
            }
        }
        foreach ($w->records() as $id => $r) {
            $p = $r['payload'];
            $options[$r['kind']][$id] = $r['symbol'].' · '.($p['tytul'] ?? $p['przedmiot'] ?? $p['opis'] ?? ($options['criteria'][$p['criterion_id'] ?? ''] ?? ''));
            if (! $w->client && in_array($r['kind'], ['finding', 'decision'])) {
                $options['sources'][$id] = $options[$r['kind']][$id];
            }
            if ($r['kind'] === 'task' || (! $w->client && $r['kind'] === 'decision')) {
                $options['targets'][$id] = $options[$r['kind']][$id];
            }
        }
        $options['users'] = User::whereHas('companies', fn ($q) => $q->where('companies.id', $w->audit->company_id))->orWhere('id', $w->actor->id)->orderBy('name')->pluck('name', 'id')->all();
        $options['documents'] = IsoSectionDocument::where('audit_id', $w->audit->id)->where('section_id', '5-1')->where('scope', 'client')->pluck('title', 'id')->all();

        return $options;
    }

    public function one(Workspace $w, string $kind, string $field, string $value): ?array
    {
        return collect($w->records($kind))->first(fn ($r) => ($r['payload'][$field] ?? null) === $value);
    }

    public function save(Workspace $w, int $revision, string $hash, string $kind, ?string $id, array $input): array
    {
        abort_unless(isset(Registers::TITLES[$kind]), 404);
        // The reference uses the Polish label as its value; keep the platform's stable stored key.
        if ($kind === 'finding' && ($input['priorytet'] ?? null) === 'średni') {
            $input['priorytet'] = 'sredni';
        }
        abort_if($w->client && ! in_array($kind, ['evidence', 'task', 'deadline', 'rule']), 403);
        if ($kind === 'rule') {
            abort_unless($w->writable, 403);
        } else {
            $this->assertEditing($w);
        }

        return DB::transaction(function () use ($w, $revision, $hash, $kind, $id, $input) {
            Audit::whereKey($w->audit->id)->lockForUpdate()->firstOrFail();
            $source = $this->sources($w);
            abort_unless($source['hash'] === $hash && $w->revision() === $revision, 409, 'Dane lub źródła zmieniły się. Odśwież ankietę.');
            $rules = [];
            foreach ($this->registers->fields($kind, $this->options($w)) as $field) {
                $rules[$field['key']] = [$field['required'] ? 'required' : 'nullable'];
                if ($field['type'] === 'multi') {
                    $input[$field['key']] ??= [];
                    $rules[$field['key']][] = 'array';
                    $rules[$field['key']][] = 'max:100';
                    $rules[$field['key'].'.*'] = [Rule::in(array_keys($field['choices']))];
                } elseif ($field['type'] === 'select') {
                    $rules[$field['key']][] = Rule::in(array_keys($field['choices']));
                } elseif ($field['type'] === 'date') {
                    $rules[$field['key']][] = 'date_format:Y-m-d';
                } elseif ($field['type'] === 'integer') {
                    $rules[$field['key']] = [...$rules[$field['key']], 'integer', 'min:1', 'max:20'];
                } elseif ($field['type'] === 'number') {
                    $rules[$field['key']] = [...$rules[$field['key']], 'regex:/^\d{1,12}([.,]\d{1,2})?$/'];
                } else {
                    $rules[$field['key']] = [...$rules[$field['key']], 'string', 'max:12000'];
                }
            }
            $payload = Validator::make($input, $rules)->validate();
            $old = $id ? $w->record($id, $kind) : null;
            $payload = array_replace($old['payload'] ?? [], $payload);
            $this->checkRecord($w, $kind, $payload, $old, $source);
            $natural = match ($kind) {
                'citation_verification' => $payload['citation_key'],
                'verification' => $payload['evidence_id'], 'coverage' => $payload['evidence_id'].'/'.$payload['criterion_id'],
                'closure' => $payload['criterion_id'], 'assessment', 'rule' => $payload['requirement_id'],
                default => null,
            };
            if ($natural) {
                $payload['key'] = $natural;
                $existing = $this->one($w, $kind, 'key', $natural);
                if ($old && ($old['payload']['key'] ?? '') !== $natural) {
                    $this->invalid('Nie można zmienić przedmiotu istniejącego zapisu. Dodaj nowy zapis.');
                }
                $id ??= $existing['id'] ?? null;
            }
            $prefix = ['citation_verification' => 'CYT', 'evidence' => 'DOW', 'verification' => 'WER', 'coverage' => 'POK', 'closure' => 'ZAM', 'assessment' => 'OCE', 'finding' => 'UST', 'question' => 'ZAP', 'decision' => 'POST', 'task' => 'ZAD', 'deadline' => 'TER', 'effectiveness' => 'SKU', 'authority' => 'UPR', 'rule' => 'ZAS'][$kind];
            $symbol = $id ? $w->record($id)['symbol'] : $prefix.'-'.(count($w->records($kind)) + 1);

            return $w->append($revision, $kind, $id, $symbol, $payload, config('enms.library'), $source['scope_version']);
        });
    }

    private function checkRecord(Workspace $w, string $kind, array &$p, ?array $old, array $source): void
    {
        if ($kind === 'evidence') {
            $p['created_by_client'] = $old['payload']['created_by_client'] ?? $w->client;
        }
        if ($kind === 'citation_verification') {
            [$id,$field] = explode('/', $p['citation_key'], 2);
            $declaration = $this->one($w, 'declaration', 'requirement_id', $id);
            $p['citation'] = $declaration['payload']['citations'][$field];
            if ($p['state'] === 'zweryfikowana' && blank($p['citation']['value'])) {
                $this->invalid('Brak treści cytatu do zweryfikowania.');
            }
        }
        if ($kind === 'verification') {
            $p['evidence_version'] = $w->record($p['evidence_id'], 'evidence')['version'];
            // Source ustawWeryfikacje persists partial work; brakiWeryfikacji reports gaps separately.
        }
        if ($kind === 'coverage') {
            $p['evidence_version'] = $w->record($p['evidence_id'], 'evidence')['version'];
            if ($p['zakres'] !== 'zadne' && blank($p['coPotwierdza'] ?? null)) {
                $this->invalid('Wskaż, co dowód potwierdza.');
            }
            if ($p['zakres'] !== 'pelne' && blank($p['czegoBrakuje'] ?? null)) {
                $this->invalid('Wskaż, czego dowód nie pokrywa.');
            }
        }
        if ($kind === 'closure') {
            $basis = $this->basis($w, $p['criterion_id']);
            if ($p['stan'] === 'zamkniete' && ! $basis['coverage']) {
                $this->invalid('Brak wiarygodnego pokrycia kryterium.');
            }
            $p['basis'] = $basis;
            $p['partial_only'] = $p['stan'] === 'zamkniete' && $basis['partial_only'];
        }
        if ($kind === 'rule') {
            if (! $this->library->requirement($p['requirement_id'])['normative']) {
                $this->invalid('Zasady Karty dotyczą obowiązków a–m.');
            }
            if (filled($p['tresc'] ?? null) && empty($p['evidence_id'])) {
                $this->invalid('Treść zasady wymaga podstawy: dokumentu albo zapisu organizacji.');
            }
            // Editing a proposal cannot carry forward a management resolution.
            unset($p['resolution']);
        }
        if ($kind === 'assessment') {
            $requirement = $this->library->requirement($p['requirement_id']);
            if (! $requirement['normative']) {
                $this->invalid('Ocena wymagania dotyczy trzynastu obowiązków a–m. Pozycje metodyczne nie są wymaganiami normy.');
            }
            if ($requirement['normative'] && $p['stan'] === 'pozazakresem') {
                $this->invalid('Wymagania normy nie można wyłączyć z zakresu.');
            }
            if ($p['mode'] === 'okresowa' && (empty($p['period_from']) || empty($p['period_until']) || $p['period_until'] < $p['period_from'])) {
                $this->invalid('Podaj prawidłowy okres oceny.');
            }
            if ($p['mode'] === 'zdarzeniowa' && blank($p['event'] ?? null)) {
                $this->invalid('Opisz zdarzenie będące podstawą oceny.');
            }
            if ($p['stan'] === 'wykazane') {
                $eligible = false;
                foreach ($p['coverage_ids'] ?? [] as $coverageId) {
                    $coverage = $w->record($coverageId, 'coverage')['payload'];
                    if (($this->library->criteria()[$coverage['criterion_id']]['requirement_id'] ?? '') === $p['requirement_id'] && isset($this->basis($w, $coverage['criterion_id'])['coverage'][$coverageId])) {
                        $eligible = true;
                    }
                }
                if (! $eligible) {
                    $this->invalid('Ocena „wykazane” wymaga wskazania wiarygodnego pokrycia tego obowiązku.');
                }
                foreach ($requirement['kryteria'] ?? [] as $criterion) {
                    if ($criterion['rodzaj'] !== 'normatywny' || $criterion['waga'] !== 'rdzeniowe') {
                        continue;
                    }
                    $cid = $this->library->id('criterion', $criterion['id']);
                    $closure = $this->one($w, 'closure', 'criterion_id', $cid);
                    if (($closure['payload']['stan'] ?? '') !== 'zamkniete' || ! SnapshotComparison::same($closure['payload']['basis'] ?? null, $this->basis($w, $cid))) {
                        $this->invalid('Kryterium '.$criterion['id'].' wymaga aktualnego zamknięcia przez audytora.');
                    }
                }
            }
        }
        if ($kind === 'finding' && ! empty($p['criterion_id']) && $this->library->criteria()[$p['criterion_id']]['requirement_id'] !== $p['requirement_id']) {
            $this->invalid('Kryterium należy do innego obowiązku.');
        }
        if ($kind === 'question') {
            $p['state'] ??= 'open';
            $p['question_hash'] = hash('sha256', json_encode([$p['requirement_id'], $p['pytanie']], JSON_THROW_ON_ERROR));
            if ($old && ($old['payload']['question_hash'] ?? null) !== $p['question_hash']) {
                $p['state'] = 'open';
                unset($p['closure']);
            }
        }
        if ($kind === 'decision') {
            if (($old['payload']['state'] ?? 'dopodjecia') !== 'dopodjecia') {
                $this->invalid('Rozstrzygniętego postanowienia nie można nadpisać. Dodaj nowe postanowienie z nową podstawą decyzji.');
            }
            $p['state'] ??= 'dopodjecia';
            $p['recommendation_author_id'] = $old['payload']['recommendation_author_id'] ?? $w->actor->id;
        }
        if ($kind === 'task') {
            if (($old['payload']['stan'] ?? '') === 'wykonane' || $p['stan'] === 'wykonane') {
                $this->invalid('Wykonanie zadania zapisuje osobna czynność z upoważnieniem. Wykonanego zadania nie można nadpisać; dodaj nowe wystąpienie.');
            }
            if ($p['stan'] === 'wykonane' && (empty($p['evidence_id']) || empty($p['executor_id']))) {
                $this->invalid('Wykonanie wymaga dowodu i wskazania wykonawcy.');
            }
            if (! empty($p['source_id'])) {
                $origin = $w->record($p['source_id']);
                $p['recommendation_author_id'] = $origin['payload']['recommendation_author_id'] ?? $origin['actor_id'];
            }
            $p['similar_ids'] = array_keys(array_filter($w->records('task'), fn ($r) => $r['id'] !== ($old['id'] ?? null) && $r['payload']['przedmiot'] === $p['przedmiot'] && $r['payload']['owner_id'] == $p['owner_id'] && ($r['payload']['cycle'] ?? null) === ($p['cycle'] ?? null) && ($r['payload']['occurrence'] ?? null) === ($p['occurrence'] ?? null)));
        }
        if ($kind === 'deadline') {
            if (($old['payload']['origin'] ?? '') === 'zatwierdzony') {
                $this->invalid('Zatwierdzonego terminu nie można nadpisać. Dodaj nową propozycję.');
            }
            if ($p['origin'] === 'zatwierdzony') {
                $this->invalid('Zapisz propozycję terminu, a następnie użyj osobnej czynności zatwierdzenia.');
            }
            if ($w->client && $p['origin'] !== 'organizacja') {
                $this->invalid('Klient zapisuje termin jako deklarację organizacji.');
            }
        }
        if ($kind === 'authority') {
            if (in_array($p['subject_kind'] ?? '', ['rola', 'organ']) && blank($p['subject_name'] ?? null)) {
                $this->invalid('Wskaż nazwę roli lub organu oraz konto osoby działającej w jego imieniu.');
            }
            if (isset($old['payload']['revocation'])) {
                $this->invalid('Cofniętego upoważnienia nie można przywrócić przez edycję. Dodaj nowe.');
            }
            if (! empty($p['valid_until']) && $p['valid_until'] < $p['valid_from']) {
                $this->invalid('Koniec upoważnienia poprzedza początek.');
            }
            if ((int) $p['step'] > (int) $p['required_steps']) {
                $this->invalid('Stopień przekracza liczbę wymaganych zgód.');
            }
            $p['scope_version'] = $source['scope_version'];
            if (! empty($p['substitute_id']) && (empty($p['substitute_from']) || empty($p['substitute_until']) || empty($p['substitute_evidence_id']) || $p['substitute_until'] < $p['substitute_from'])) {
                $this->invalid('Zastępstwo wymaga okresu i dowodu.');
            }
        }
        if ($kind === 'effectiveness') {
            $this->invalid('Ocenę skuteczności zapisuje osobna czynność z kontrolą upoważnienia.');
        }
    }

    public function basis(Workspace $w, string $criterionId): array
    {
        $eligible = [];
        $all = [];
        foreach ($w->records('coverage') as $id => $row) {
            $p = $row['payload'];
            if ($p['criterion_id'] !== $criterionId) {
                continue;
            }
            $evidence = $w->record($p['evidence_id'], 'evidence');
            $verification = $this->one($w, 'verification', 'evidence_id', $p['evidence_id']);
            $all[$id] = [$row['version'], $evidence['version'], $verification['version'] ?? null, $p['zakres']];
            if (! in_array($p['zakres'], ['pelne', 'czesciowe']) || (new Authorization)->status($evidence, $verification['payload'] ?? null) !== 'zweryfikowane' || $p['evidence_version'] !== $evidence['version']) {
                continue;
            }
            $eligible[$id] = [$row['version'], $evidence['version'], $verification['version'], $p['zakres']];
        }
        ksort($eligible);
        ksort($all);
        $findings = [];
        foreach ($w->records('finding') as $id => $finding) {
            if (($finding['payload']['criterion_id'] ?? null) === $criterionId) {
                $findings[$id] = $finding['version'];
            }
        }
        ksort($findings);

        return ['coverage' => $eligible, 'all_coverage' => $all, 'partial_only' => (bool) $eligible && ! array_filter($eligible, fn ($r) => $r[3] === 'pelne'), 'findings' => $findings, 'scope_version' => $this->sources($w)['scope_version']];
    }

    public function verificationMissing(array $p): array
    {
        if (($p['dostepnosc'] ?? 'niezweryfikowany') === 'niezweryfikowany') {
            return ['Dowód nie był jeszcze weryfikowany.'];
        }
        $missing = [];
        if ($p['dostepnosc'] === 'okazany') {
            foreach (['wiarygodnosc' => 'wiarygodność', 'zakresInformacji' => 'co dowód faktycznie zawiera', 'sposob' => 'sposób sprawdzenia'] as $key => $label) {
                if (blank($p[$key] ?? null)) {
                    $missing[] = 'Uzupełnij: '.$label.'.';
                }
            }
            if (($p['sposob'] ?? '') === 'próbka' && blank($p['probka'] ?? null)) {
                $missing[] = 'Opisz liczebność i dobór próbki.';
            }
        }
        if (! empty($p['wiarygodnosc']) && $p['wiarygodnosc'] !== 'potwierdzona' && blank($p['uwaga'] ?? null)) {
            $missing[] = 'Wyjaśnij wątpliwości dotyczące dowodu.';
        }

        return $missing;
    }

    public function invalid(string $message): never
    {
        throw ValidationException::withMessages(['enms' => $message]);
    }

    public function saveDeclaration(Workspace $w, int $revision, string $hash, string $requirementId, array $input, bool $syncEvidence = true): void
    {
        $this->assertEditing($w);
        $requirement = $this->library->requirement($requirementId);
        $rules = ['stan' => ['nullable', Rule::in(($requirement['normative'] || ! ($requirement['dopuszczaNiedotyczy'] ?? false)) ? ['jest', 'czesciowo', 'niema'] : ['jest', 'czesciowo', 'niema', 'niedotyczy'])], 'plan.opis' => 'nullable|string|max:12000', 'plan.termin' => 'nullable|string|max:200', 'plan.kto' => 'nullable|string|max:500', 'uzasadnienieNiedotyczy' => 'nullable|string|max:3000', 'opis' => 'nullable|string|max:12000', 'evidence_ids' => 'nullable|array|max:100', 'evidence_ids.*' => [Rule::in(array_keys($w->records('evidence')))]];
        foreach ($requirement['pola'] as $field) {
            if ($field['typ'] === 'odwolanie') {
                continue;
            }
            $rules['pola.'.$field['klucz']] = ['nullable', 'string', 'max:12000'];
            if ($field['typ'] === 'wybor') {
                $rules['pola.'.$field['klucz']][] = Rule::in($field['opcje']);
            }
            if ($field['typ'] === 'data') {
                $rules['pola.'.$field['klucz']][] = 'date_format:Y-m-d';
            }
            if ($field['typ'] === 'liczba') {
                $rules['pola.'.$field['klucz']][] = 'regex:/^-?\d{1,12}([.,]\d{1,6})?$/';
            }
        }
        $data = Validator::make($input, $rules)->validate();
        DB::transaction(function () use ($w, $revision, $hash, $requirementId, $requirement, $data, $syncEvidence) {
            Audit::whereKey($w->audit->id)->lockForUpdate()->firstOrFail();
            $source = $this->sources($w);
            abort_unless($w->revision() === $revision && $source['hash'] === $hash, 409, 'Dane lub źródła zmieniły się.');
            $existing = $this->one($w, 'declaration', 'requirement_id', $requirementId);
            $payload = array_replace_recursive($existing['payload'] ?? [], $data);
            // This is the organization's explicit selection, distinct from evidence added later by the consultant.
            // Replace the list; recursive merging would preserve removed selections by numeric array index.
            $payload['evidence_ids'] = array_values(($syncEvidence ? $data['evidence_ids'] ?? [] : $existing['payload']['evidence_ids'] ?? []));
            $payload['requirement_id'] = $requirementId;
            $payload['citations'] = [];
            foreach ($requirement['pola'] as $field) {
                if ($field['typ'] === 'odwolanie') {
                    $payload['citations'][$field['klucz']] = $source['citations'][$field['zrodlo']['obiekt']];
                }
            }
            $w->append($revision++, 'declaration', $existing['id'] ?? null, $requirement['kod'], $payload, config('enms.library'), $source['scope_version']);
            if (! $syncEvidence) {
                return;
            }
            foreach ($w->records('evidence') as $id => $evidence) {
                $refs = $evidence['payload']['requirements'] ?? [];
                $has = in_array($requirementId, $refs, true);
                $wanted = in_array($id, $data['evidence_ids'] ?? [], true);
                if ($has === $wanted) {
                    continue;
                }
                $evidence['payload']['requirements'] = $wanted ? [...$refs, $requirementId] : array_values(array_diff($refs, [$requirementId]));
                $w->append($revision++, 'evidence', $id, $evidence['symbol'], $evidence['payload'], config('enms.library'), $source['scope_version']);
            }
        });
    }

    public function saveSelfAssessment(Workspace $w, int $revision, string $hash, string $questionId, array $input): void
    {
        $data = Validator::make(['question_id' => $questionId] + $input, [
            'question_id' => ['required', Rule::in(array_column($this->library->data()['PYTANIA_4'], 'id'))],
            'stan' => ['nullable', Rule::in(array_keys(LeadershipLibrary::SELF_ASSESSMENT_STATES))],
            'komentarz' => 'nullable|string|max:12000',
        ])->validate();
        $payload = ['question_id' => $questionId, 'stan' => $data['stan'] ?? '', 'komentarz' => $data['komentarz'] ?? ''];

        DB::transaction(function () use ($w, $revision, $hash, $questionId, $payload) {
            Audit::whereKey($w->audit->id)->lockForUpdate()->firstOrFail();
            $this->assertEditing($w);
            $source = $this->sources($w);
            abort_unless($w->revision() === $revision && $source['hash'] === $hash, 409, 'Dane lub źródła zmieniły się.');
            $existing = $this->one($w, 'self_assessment', 'question_id', $questionId);
            if ($existing && SnapshotComparison::same($existing['payload'], $payload)) {
                return;
            }
            $w->append($revision, 'self_assessment', $existing['id'] ?? null, $existing['symbol'] ?? 'SAMO-'.$questionId, $payload, config('enms.library'), $source['scope_version']);
        });
    }

    public function clientHash(Workspace $w): string
    {
        $selectedEvidence = [];
        foreach ($w->records('declaration') as $r) {
            $selectedEvidence = [...$selectedEvidence, ...($r['payload']['evidence_ids'] ?? [])];
        }
        $records = array_values(array_filter($w->records(), fn ($r) => in_array($r['kind'], ['declaration', 'self_assessment', 'explanation', 'answer']) || ($r['kind'] === 'evidence' && (($r['payload']['created_by_client'] ?? false) || in_array($r['id'], $selectedEvidence, true)))));

        return hash('sha256', json_encode([$records, $this->sources($w)['hash']], JSON_THROW_ON_ERROR));
    }

    public function readiness(Workspace $w): array
    {
        $missing = [];
        $gaps = [];
        $inconsistencies = [];
        $source = $this->sources($w);
        foreach ($this->library->requirements() as $id => $requirement) {
            $declaration = $this->one($w, 'declaration', 'requirement_id', $id)['payload'] ?? [];
            $state = $declaration['stan'] ?? '';
            if ($state === 'niedotyczy' && blank($declaration['uzasadnienieNiedotyczy'] ?? null)) {
                $missing[] = ($requirement['iso'] ?? $requirement['kod']).': odpowiedź „nie dotyczy” wymaga uzasadnienia.';
            }
            $label = $requirement['iso'] ?? $requirement['kod'];
            if (! $state && $requirement['normative']) {
                $missing[] = "$label: nie ma odpowiedzi na pytanie.";
            }
            if (in_array($state, ['czesciowo', 'niema'])) {
                $gaps[] = "$label: ".($declaration['plan']['opis'] ?? 'Brak planu');
                if (blank($declaration['plan']['opis'] ?? null) || blank($declaration['plan']['termin'] ?? null)) {
                    $missing[] = "$label: uzupełnij plan i termin.";
                }
            }
            if ($state === 'jest') {
                if (! array_filter($w->records('evidence'), fn ($e) => in_array($id, $e['payload']['requirements'] ?? [], true))) {
                    $missing[] = "$label: wskaż dowód z rejestru.";
                }
                foreach ($requirement['pola'] as $field) {
                    if (! $field['wymagane']) {
                        continue;
                    }
                    $value = $field['typ'] === 'odwolanie' ? ($source['citations'][$field['zrodlo']['obiekt']]['value'] ?? null) : ($declaration['pola'][$field['klucz']] ?? null);
                    if (blank($value) && $value !== 0 && $value !== '0') {
                        $missing[] = "$label: nie wypełniono — ".$field['etykieta'];
                    }
                }
            }
            foreach ($declaration['citations'] ?? [] as $key => $citation) {
                $field = collect($requirement['pola'])->firstWhere('klucz', $key);
                if (! SnapshotComparison::same($citation, $source['citations'][$field['zrodlo']['obiekt']] ?? null)) {
                    $missing[] = "$label: źródło pola „".$field['etykieta'].'” zmieniło się. Sprawdź i zapisz odpowiedź ponownie.';
                }
            }
        }
        foreach ($w->records('evidence') as $evidence) {
            $p = $evidence['payload'];
            $required = ['rodzaj', 'tytul', 'data', 'gdzie', 'requirements'];
            if (in_array($p['rodzaj'] ?? '', ['DOK', 'ZAP', 'SYS', 'UMO'])) {
                $required[] = 'identyfikator';
            }
            if (in_array($p['rodzaj'] ?? '', ['DOK', 'UMO'])) {
                $required[] = 'wydal';
            }
            if (($p['rodzaj'] ?? '') === 'ROZ') {
                $required[] = 'uczestnicy';
            }
            foreach ($required as $key) {
                if (blank($p[$key] ?? null)) {
                    $missing[] = $evidence['symbol'].': brak pola '.$key.'.';
                }
            }
        }
        $declaration = fn ($code) => $this->one($w, 'declaration', 'requirement_id', $this->library->id('requirement', $code))['payload'] ?? [];
        $budget = $declaration('e');
        if (($budget['stan'] ?? '') === 'jest' && ! empty($budget['pola']['budzetStan']) && $budget['pola']['budzetStan'] !== 'zatwierdzony') {
            $inconsistencies['NS-e-budzet'] = 'Zasoby zadeklarowano jako zapewnione, ale budżet nie jest zatwierdzony.';
        }
        if (blank($declaration('PRZ-00')['pola']['podmiot'] ?? null) && array_filter($w->records('declaration'), fn ($r) => ($r['payload']['stan'] ?? '') === 'jest')) {
            $inconsistencies['NS-prz00'] = 'Wskaż, kto jest najwyższym kierownictwem dla systemu, albo wyjaśnij brak ustalenia.';
        }
        foreach (['d' => ['postep', 'NS-d-realizacja', 'Plan jest zatwierdzony, ale nie opisano realizacji.'], 'f' => ['doKogo', 'NS-f-adresaci', 'Nie wskazano odbiorców komunikacji.']] as $code => [$field, $key, $message]) {
            if (($declaration($code)['stan'] ?? '') === 'jest' && blank($declaration($code)['pola'][$field] ?? null)) {
                $inconsistencies[$key] = $message;
            }
        }
        $goals = $declaration('b');
        if (($goals['stan'] ?? '') === 'jest' && blank($source['citations']['CELE']['value'] ?? null) && filled($goals['pola']['celeKosztowe'] ?? null)) {
            $inconsistencies['NS-b-cele'] = 'Podano cel kosztowy, ale brakuje celu energetycznego.';
        }
        foreach ($inconsistencies as $key => $message) {
            if (! $this->one($w, 'explanation', 'key', $key)) {
                $missing[] = $message.' Wymagane wyjaśnienie.';
            }
        }
        foreach ($w->records('question') as $question) {
            if (($question['payload']['state'] ?? '') === 'open' && ! $this->hasCurrentAnswer($w, $question)) {
                $missing[] = $question['symbol'].': odpowiedz na pytanie audytora.';
            }
        }

        $warnings = [];
        if (($declaration('i')['stan'] ?? '') === 'jest' && filled($declaration('i')['pola']['wsparcieZewn'] ?? null) && ($declaration('i')['pola']['jednaOsoba'] ?? '') === 'jedna upoważniona osoba') {
            $warnings['NS-i-zespol'] = 'Jedna upoważniona osoba korzysta ze wsparcia zewnętrznego. Odpowiedzialność za system pozostaje w organizacji.';
        }
        if (($declaration('l')['stan'] ?? '') === 'jest' && ($declaration('l')['pola']['jakoscDanych'] ?? '') === 'szacunek') {
            $warnings['NS-l-szacunek'] = 'Wskaźniki wyliczane z szacunku wymagają wskazania ograniczeń oceny.';
        }
        if (($declaration('g')['stan'] ?? '') === 'jest' && ($declaration('l')['pola']['jakoscDanych'] ?? '') === 'szacunek') {
            $warnings['NS-g-szacunek'] = 'Sam szacunek wyniku energetycznego nie wystarcza do stwierdzenia poprawy.';
        }
        if (($declaration('m')['stan'] ?? '') === 'jest' && ($declaration('m')['pola']['kiedyOcena'] ?? '') === 'po zmianie') {
            $warnings['NS-m-moment'] = 'Przy przewidywalnych zmianach wpływ należy ocenić przed decyzją. Przy awarii lub zmianie przepisów ocena po fakcie może być właściwa.';
        }
        $accepted = $declaration('a')['pola']['zatwierdzil'] ?? null;
        $leader = $declaration('PRZ-00')['pola']['rola'] ?? null;
        if (($declaration('a')['stan'] ?? '') === 'jest' && $accepted && $leader && $accepted !== $leader) {
            $warnings['NS-a-rola'] = 'Zakres przyjęła inna rola niż wskazane najwyższe kierownictwo. Sprawdź upoważnienie.';
        }

        return compact('missing', 'gaps', 'inconsistencies', 'warnings');
    }

    public function status(Workspace $w): array
    {
        $submission = $this->one($w, 'submission', 'key', 'current');
        $completion = $this->one($w, 'completion', 'key', 'current');
        $submitted = ($submission['payload']['state'] ?? '') === 'submitted' && ($submission['payload']['client_hash'] ?? '') === $this->clientHash($w);
        $assessed = $submitted && ($completion['payload']['state_hash'] ?? '') === $this->assessmentHash($w);

        return ['submitted' => $submitted, 'assessed' => $assessed, 'submission' => $submission, 'completion' => $completion, 'changed' => $submission && ! $submitted, 'label' => $assessed ? 'Ocena audytora zakończona' : ($submitted ? 'Zatwierdzone przez klienta — do oceny audytora' : 'W opracowaniu')];
    }

    public function changedSinceSubmission(Workspace $w, array $record): bool
    {
        $submission = $this->one($w, 'submission', 'key', 'current');
        $baseline = $submission['payload']['baseline'] ?? null;

        return $baseline !== null && ($baseline[$record['id']] ?? null) !== $record['version'];
    }

    public function hasCurrentAnswer(Workspace $w, array $question): bool
    {
        $answer = $this->one($w, 'answer', 'question_id', $question['id']);

        return $answer && ($answer['payload']['question_hash'] ?? null) === ($question['payload']['question_hash'] ?? null);
    }

    private function assertEditing(Workspace $w): void
    {
        abort_unless($w->writable, 403);
        if ($w->client && $this->status($w)['submitted']) {
            $this->invalid('Najpierw wycofaj zatwierdzenie ankiety. Historia zatwierdzenia zostanie zachowana.');
        }
    }

    public function transition(Workspace $w, int $revision, string $hash, string $operation, array $input): void
    {
        DB::transaction(function () use ($w, $revision, $hash, $operation, $input) {
            Audit::whereKey($w->audit->id)->lockForUpdate()->firstOrFail();
            $source = $this->sources($w);
            abort_unless($revision === $w->revision() && $hash === $source['hash'], 409, 'Dane lub źródła zmieniły się.');
            $payload = Validator::make($input, ['text' => 'nullable|string|max:12000', 'key' => 'nullable|string|max:100', 'question_id' => 'nullable|uuid'])->validate();
            $kind = $operation;
            if ($operation === 'submission') {
                abort_unless($w->client && $w->actor->hasRole('client_admin'), 403);
                if ($missing = $this->readiness($w)['missing']) {
                    $this->invalid(implode(' ', $missing));
                }
                $payload = ['key' => 'current', 'client_hash' => $this->clientHash($w), 'state' => 'submitted', 'baseline' => array_map(fn ($r) => $r['version'], $w->records())];
            } elseif ($operation === 'completion') {
                abort_if($w->client, 403);
                if (! $this->status($w)['submitted']) {
                    $this->invalid('Ocena wymaga aktualnego zatwierdzenia ankiety przez klienta.');
                }
                if (blank($payload['text'] ?? null)) {
                    $this->invalid('Zakończenie oceny wymaga uzasadnienia.');
                }
                foreach ($this->library->requirements() as $id => $r) {
                    if ($r['normative'] && ! $this->one($w, 'assessment', 'requirement_id', $id)) {
                        $this->invalid('Brakuje oceny '.$r['iso'].'.');
                    }
                }
                foreach ($w->records('question') as $q) {
                    if (($q['payload']['state'] ?? '') === 'open' && ! $this->hasCurrentAnswer($w, $q)) {
                        $this->invalid('Pozostały otwarte pytania do klienta.');
                    }
                }
                foreach ($w->records('closure') as $c) {
                    if ($c['payload']['stan'] === 'zamkniete' && ! SnapshotComparison::same($c['payload']['basis'], $this->basis($w, $c['payload']['criterion_id']))) {
                        $this->invalid('Zamknięcia kryteriów wymagają ponownego sprawdzenia.');
                    }
                }
                $payload += ['key' => 'current', 'state_hash' => $this->assessmentHash($w)];
            } elseif ($operation === 'explanation') {
                $this->assertEditing($w);
                abort_unless(isset($this->readiness($w)['inconsistencies'][$payload['key'] ?? '']), 422);
                if (blank($payload['text'] ?? null)) {
                    $this->invalid('Wyjaśnienie nie może być puste.');
                }
            } elseif ($operation === 'answer') {
                $this->assertEditing($w);
                $q = $w->record($payload['question_id'] ?? '', 'question');
                abort_unless(($q['payload']['state'] ?? '') === 'open', 422, 'Pytanie zostało zamknięte lub wycofane.');
                if (blank($payload['text'] ?? null)) {
                    $this->invalid('Odpowiedź nie może być pusta.');
                }
                $payload['key'] = $q['id'];
                $payload['question_hash'] = $q['payload']['question_hash'];
            } elseif (in_array($operation, ['close_question', 'withdraw_question'])) {
                abort_if($w->client, 403);
                $question = $w->record($payload['question_id'] ?? '', 'question');
                if (blank($payload['text'] ?? null)) {
                    $this->invalid('Podaj przyczynę zamknięcia lub wycofania pytania.');
                }
                if ($operation === 'close_question' && ! $this->hasCurrentAnswer($w, $question)) {
                    $this->invalid('Pytanie bez aktualnej odpowiedzi może czekać albo zostać wycofane z podaniem przyczyny.');
                }
                $data = array_replace($question['payload'], ['state' => $operation === 'close_question' ? 'closed' : 'withdrawn', 'closure' => ['text' => $payload['text'], 'actor_id' => $w->actor->id, 'date' => now()->toIso8601String()]]);
                $w->append($revision, 'question', $question['id'], $question['symbol'], $data, config('enms.library'), $source['scope_version']);

                return;
            } elseif ($operation === 'withdraw' || $operation === 'return') {
                abort_unless($operation === 'withdraw' ? ($w->client && $w->actor->hasRole('client_admin')) : ! $w->client, 403);
                if (blank($payload['text'] ?? null)) {
                    $this->invalid('Podaj przyczynę.');
                }
                $kind = 'submission';
                $payload += ['key' => 'current', 'state' => $operation];
            } else {
                abort(404);
            }
            $existing = $this->one($w, $kind, 'key', $payload['key']);
            $w->append($revision, $kind, $existing['id'] ?? null, $kind.'-'.$payload['key'], $payload, config('enms.library'), $source['scope_version']);
        });
    }

    public function assessmentHash(Workspace $w): string
    {
        return hash('sha256', json_encode([array_values(array_filter($w->records(), fn ($r) => ! in_array($r['kind'], ['submission', 'completion', 'document', 'document_draft', 'document_approval', 'approval', 'authorized_action']))), $this->sources($w)['hash']], JSON_THROW_ON_ERROR));
    }
}
