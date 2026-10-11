<?php

namespace App\Services;

use App\Models\IsoEnergyCards;
use App\Models\IsoPlantProfile;
use App\Models\IsoSystemReview;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Inert source schemas, scoped dependencies and calculations for chapters 8–10. */
class IsoExecutionQuestionnaire
{
    public const TITLES = [
        '8-1' => '8.1 Nadzór operacyjny D-EnMS-OPE-01', '8-2' => '8.2 Projektowanie D-EnMS-PRO-01', '8-3' => '8.3 Zakupy D-EnMS-ZKP-01',
        '9-1-1' => '9.1.1 Monitorowanie D-EnMS-MON-01', '9-1-2' => '9.1.2 Ocena zgodności D-EnMS-ZGD-01', '9-2' => '9.2 Audyt wewnętrzny D-EnMS-AUD-01', '9-3' => '9.3 Przegląd zarządzania D-EnMS-PZR-01',
        '10-1' => '10.1 Niezgodności D-EnMS-NKR-01', '10-2' => '10.2 Ciągłe doskonalenie D-EnMS-DOS-01',
    ];

    public static function handles(string $section): bool
    {
        return isset(self::TITLES[$section]);
    }

    public function schema(string $section): array
    {
        abort_unless(self::handles($section), 404);

        static $schemas = [];

        return $schemas[$section] ??= json_decode(file_get_contents(resource_path('iso50001/execution-'.$section.'-v1.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function sources(IsoPlantProfile $profile, string $section): array
    {
        $cache = [];

        return $this->resolve($profile, $section, $cache);
    }

    private function resolve(IsoPlantProfile $profile, string $section, array &$cache): array
    {
        if (isset($cache[$section])) {
            return $cache[$section];
        }
        $cache['base'] ??= app(IsoSystemQuestionnaire::class)->sources($profile, '4-4');
        $cache['rows'] ??= IsoSystemReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->get()->keyBy('section');
        $base = $cache['base'];
        $dependencies = match ($section) {
            '8-1' => ['6-2', '6-3', '6-4', '6-6'], '8-2' => ['6-2', '8-1'], '8-3' => ['8-1', '8-2'],
            '9-1-1' => ['6-2', '6-3', '6-4', '6-5', '6-6', '8-1'], '9-1-2' => [],
            '9-2' => ['9-1-1', '9-1-2'], '9-3' => ['9-1-1', '9-1-2', '9-2'],
            '10-1' => ['9-1-1', '9-1-2', '9-2'], '10-2' => ['6-3', '9-1-1', '9-2', '9-3', '10-1'],
        };
        $required = match ($section) {
            '8-1' => ['6-3'], '8-2', '8-3' => ['8-1'], '9-1-1' => ['6-4', '6-5'],
            '9-3' => ['9-1-1', '9-1-2', '9-2'], '10-2' => ['9-1-1'], default => [],
        };
        $base['upstream'] = [];
        $ready = $base['ready'];
        foreach ($dependencies as $key) {
            $row = $cache['rows']->get($key);
            $current = self::handles($key) ? $this->resolve($profile, $key, $cache) : ($cache['external'][$key] ??= app(IsoSystemQuestionnaire::class)->sources($profile, $key));
            $ok = $row?->status === 'approved' && $row->client_approval && $row->auditor_approval && $row->source_hash === $current['hash'] && $current['ready'];
            $base['approved'][$key] = (bool) $ok;
            $base['published'][$key] = (bool) ($ok && $row->document_id);
            $base['upstream'][$key] = $row?->answers ?? [];
            $base['dependency_hashes'][$key] = $current['hash'];
            if (in_array($key, $required)) {
                $ready = $ready && $ok;
            }
        }
        $base['required_sources'] = $required;
        $base['cards'] = in_array($section, ['9-1-1', '10-2']) ? (IsoEnergyCards::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->first()?->cards ?? []) : [];
        $base['execution_section'] = $section;
        $fingerprint = $base;
        unset($fingerprint['hash'], $fingerprint['ready'], $fingerprint['approved'], $fingerprint['published']);
        $base['hash'] = hash('sha256', json_encode([$fingerprint, $this->schema($section)], JSON_THROW_ON_ERROR));
        $base['ready'] = (bool) $ready;

        return $cache[$section] = $base;
    }

    public function tree(string $section): array
    {
        $tree = $this->schema($section)['tree'];
        // A register may truthfully contain no projects, purchases or nonconformities.
        foreach ($tree as $root => &$node) {
            if ($node['type'] === 'list') {
                $node['min'] = in_array($root, ['ODCH', 'RAPORTY', 'MOZLIWOSCI']) ? 0 : 1;
            }
        }
        unset($node);
        if ($section === '9-1-2') {
            $tree['WYM']['fixed'] = true;
            $tree['WYM']['min'] = 0;
        }
        if ($section === '9-1-1') {
            $tree['WSKAZNIKI']['fixed'] = true;
        }
        if ($section === '10-2') {
            $tree['POPRAWA']['fixed'] = true;
        }

        return ['META' => $tree['META']] + $tree;
    }

    public function seed(string $section, array $sources): array
    {
        $out = [];
        foreach ($this->tree($section) as $root => $node) {
            if ($node['type'] !== 'list') {
                continue;
            }
            $out[$root] = [];
            foreach ($node['codes'] ?? [] as $code => $label) {
                $out[$root][] = ['kod' => $code];
            }
            if (! $out[$root] && ($node['min'] ?? 0) && ! ($node['fixed'] ?? false)) {
                $out[$root] = [[]];
            }
        }
        if ($section === '8-1') {
            foreach (data_get($sources, 'upstream.6-3.SEU', []) as $i => $seu) {
                $out['KARTY'][$i] = ['seu' => $seu['nazwa'] ?? $seu['obszar'] ?? '', 'osoby' => $seu['osoby'] ?? ''];
            }
        }

        return $this->project($section, $out, $sources);
    }

    /** Authoritative upstream fields cannot be forged, copied across sites or silently edited. */
    public function project(string $section, array $answers, array $sources): array
    {
        if ($section === '9-1-2') {
            $old = $answers['WYM'] ?? [];
            $answers['WYM'] = [];
            foreach ($sources['parties'] ?? [] as $row) {
                if (($row['zgodnosc'] ?? '') !== 'tak') {
                    continue;
                }
                $code = $row['kod'];
                $answers['WYM'][$code] = array_replace($old[$code] ?? [], ['nr' => $code, 'strona' => $row['nazwa'] ?? $row['strona'] ?? $code,
                    'wymaganie' => $row['wymaganie'] ?? $row['wymagania'] ?? $row['oczekiwanie'] ?? '',
                    'zrodlo' => $row['consultant']['legal_basis'] ?? '', 'kwalifikacja' => $row['kwalifikacja'] ?? 'Wymaganie zgodności z 4.2']);
            }
        }
        if (in_array($section, ['9-1-1', '10-2'])) {
            $root = $section === '9-1-1' ? 'WSKAZNIKI' : 'POPRAWA';
            $old = $answers[$root] ?? [];
            $answers[$root] = [];
            foreach ($sources['cards'] ?? [] as $card) {
                $code = $card['nr'];
                $index = 'card_'.substr(hash('sha256', $code), 0, 20);
                $row = $old[$index] ?? [];
                $row = array_replace($row, ['kod' => $code, 'nazwa' => $card['nazwa'] ?? '', 'enb' => $card['wartoscEnB'] ?? '', 'enbOkres' => $card['okresBazowy'] ?? '', 'warunki' => $card['warunkiPorownania'] ?? '']);
                if ($section === '10-2') {
                    $monitor = data_get($sources, 'upstream.9-1-1.WSKAZNIKI.'.$index, []);
                    $row['biez'] = $monitor['biez'] ?? '';
                    $row['kierunek'] = $monitor['kierunek'] ?? '';
                }
                $answers[$root][$index] = $row;
            }
        }

        return $answers;
    }

    public function sections(string $section, array $answers, array $sources): array
    {
        $out = [];
        foreach ($this->tree($section) as $root => $node) {
            $fields = [];
            $this->flatten($node, $root, $answers, $fields, $section);
            $out[strtolower($root)] = ['title' => $node['label'], 'fields' => $fields, 'tree' => $node, 'root' => $root];
        }

        return $out;
    }

    private function flatten(array $node, string $path, array $answers, array &$fields, string $section): void
    {
        if ($node['type'] === 'list') {
            foreach (array_keys(is_array(data_get($answers, $path)) ? data_get($answers, $path) : []) as $i) {
                foreach ($node['children'] as $key => $child) {
                    $this->flatten($child, "$path.$i.$key", $answers, $fields, $section);
                }
            }
        } elseif ($node['type'] === 'object') {
            $start = count($fields);
            foreach ($node['children'] as $key => $child) {
                $this->flatten($child, "$path.$key", $answers, $fields, $section);
            }
            // The PDF consumes a flat list, so keep the question beside its answer.
            if (isset($node['children']['o'], $node['children']['u'])) {
                for ($i = $start; $i < count($fields); $i++) {
                    $fields[$i]['label'] = $node['label'].' — '.$fields[$i]['label'];
                }
            }
        } else {
            $fields[] = $this->field($section, $path, $node, $answers);
        }
    }

    public function field(string $section, string $path, array $node, array $answers): array
    {
        $leaf = Arr::last(explode('.', $path));
        $required = $node['required'] ?? false;
        $when = null;
        $parts = explode('.', $path);
        $rowPath = implode('.', array_slice($parts, 0, 2));
        $row = data_get($answers, $rowPath, []);
        $readonly = ($section === '9-1-2' && $parts[0] === 'WYM' && in_array($leaf, ['nr', 'strona', 'wymaganie', 'zrodlo', 'kwalifikacja']))
            || (in_array($parts[0], ['WSKAZNIKI', 'POPRAWA']) && in_array($leaf, ['kod', 'nazwa', 'enb', 'enbOkres', 'warunki']))
            || ($parts[0] === 'POPRAWA' && in_array($leaf, ['biez', 'kierunek']));
        if ($parts[0] === 'POPRAWA' && $leaf === 'powodBraku') {
            $required = ! filled($row['biez'] ?? null);
        }
        if ($leaf === 'wlasna') {
            $required = false;
        }
        if ($parts[0] === 'META' && in_array($leaf, ['cenaEnergii', 'cenaEl', 'cenaGaz'])) {
            $required = false;
        }
        if ($leaf === 'uzasadnienieBraku') {
            $when = ['META.brakZdarzen', ['tak']];
        }
        if (in_array($section, ['8-2', '8-3']) && in_array($parts[0], ['PROJEKTY', 'ZAKUPY'])) {
            $allNo = ! empty($row['A']) && collect($row['A'])->every(fn ($r) => ($r['o'] ?? '') === 'nie');
            if ($allNo && in_array($parts[2] ?? '', ['B', 'C', 'D', 'E', 'wnioski', 'oferty', 'wymagania', 'informacja', 'kryteriumWyboru', 'lata', 'cenaEnergii', 'zalozenia', 'rekomendacja', 'porownanieNd', 'porownanieNdPowod'])) {
                $required = false;
            }
            if ($leaf === 'uzasadnienieA') {
                $required = $allNo;
            }
        }
        if ($leaf === 'u') {
            $when = [substr($path, 0, -1).'o', ['nie', 'nd']];
        }
        if (str_contains($path, '.wnioski.')) {
            $base = substr($path, 0, strrpos($path, '.'));
            if ($leaf === 'gdzie') {
                $when = [$base.'.decyzja', ['przyjety']];
            }
            if ($leaf === 'dlaczego') {
                $when = [$base.'.decyzja', ['odrzucony']];
            }
        }
        if ($section === '8-3') {
            if (($row['porownanieNd'] ?? '') === 'tak' && in_array($leaf, ['lata', 'cenaEnergii', 'zalozenia'])) {
                $required = false;
            }
            if ($leaf === 'porownanieNdPowod') {
                $when = [$rowPath.'.porownanieNd', ['tak']];
            }
            if (str_contains($path, '.oferty.')) {
                $base = substr($path, 0, strrpos($path, '.'));
                $modes = ['roczne' => 'roczne', 'moc' => 'moc', 'godziny' => 'moc', 'jednostkowe' => 'jednostkowy', 'ilosc' => 'jednostkowy'];
                if (isset($modes[$leaf])) {
                    $when = [$base.'.tryb', [$modes[$leaf]]];
                }
            }
        }
        if ($section === '9-1-2' && $parts[0] === 'WYM') {
            $conditions = ['dowod' => ['zgodne'], 'przyczyna' => ['douzupelnienia', 'niezgodne'], 'dzialanie' => ['wtoku', 'douzupelnienia', 'niezgodne'], 'karta10' => ['niezgodne'], 'odp' => ['wtoku', 'douzupelnienia', 'niezgodne']];
            if (isset($conditions[$leaf])) {
                $when = [$rowPath.'.wynik', $conditions[$leaf]];
            }
        }
        if ($section === '9-2' && str_contains($path, '.ustalenia.')) {
            $base = substr($path, 0, strrpos($path, '.'));
            if (in_array($leaf, ['wymaganie', 'dzialanie', 'odp', 'karta10'])) {
                $when = [$base.'.rodzaj', $leaf === 'wymaganie' || $leaf === 'karta10' ? ['N'] : ['N', 'D']];
            }
        }
        if ($section === '10-1') {
            if ($leaf === 'zmianaOpis') {
                $when = [$rowPath.'.zmianaSystemu', ['tak']];
            }
            if (in_array($leaf, ['dataSprawdzenia', 'wynikSprawdzenia', 'sprawdzil'])) {
                $when = [$rowPath.'.decyzja', ['zamknac', 'bez']];
            }
            if ($leaf === 'wykonano') {
                $required = false;
            }
        }
        if ($section === '10-2' && $parts[0] === 'POZYCJE' && in_array($leaf, ['osiagniety', 'dowod'])) {
            $when = [$rowPath.'.stan', ['Z', 'A']];
        }
        if ($section === '10-2' && $parts[0] === 'MOZLIWOSCI' && $leaf === 'powod') {
            $when = [$rowPath.'.decyzja', ['odlozona', 'odrzucona']];
        }
        if ($section === '9-3' && $parts[0] === 'DECYZJE' && in_array($leaf, ['kto', 'termin']) && preg_match('/bez zmian|utrzymujemy|nie zmieniamy|bez rewizji/iu', $row['decyzja'] ?? '')) {
            $required = false;
        }
        $options = $node['options'] ?? [];
        if ($leaf === 'kod') {
            $options = $this->tree($section)[$parts[0]]['codes'] ?? $options;
        }

        return ['key' => $path, 'label' => $leaf === 'wlasna' ? 'Nazwa dodatkowego obszaru decyzji' : $node['label'], 'type' => $readonly ? 'text' : ($node['type'] === 'number' ? 'text' : $node['type']), 'numeric' => $node['type'] === 'number' && ! $readonly, 'readonly' => $readonly, 'required' => $required && ! $readonly, 'options' => $readonly ? [] : $options, 'when' => $when, 'help' => $readonly ? 'Wartość z powiązanego punktu. Zmieniaj ją w źródłowej ankiecie.' : ($node['help'] ?? ''), 'example' => $node['type'] === 'number' ? '1' : null];
    }

    private function shape(array $node, mixed $value, string $path, int &$leaves): array
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages([$path => 'Nieprawidłowa struktura odpowiedzi.']);
        }
        $out = [];
        if ($node['type'] === 'list') {
            if (count($value) > 50) {
                throw ValidationException::withMessages([$path => 'Maksymalnie 50 pozycji w jednej liście.']);
            }
            foreach ($value as $i => $row) {
                if (! preg_match('/^[\pL\pN_-]{1,60}$/u', (string) $i)) {
                    throw ValidationException::withMessages([$path => 'Nieprawidłowy identyfikator wiersza.']);
                }
                $out[$i] = $this->shape(['type' => 'object', 'children' => $node['children']], $row, "$path.$i", $leaves);
            }
        } else {
            foreach ($node['children'] as $key => $child) {
                if (in_array($child['type'], ['object', 'list'])) {
                    $out[$key] = $this->shape($child, $value[$key] ?? [], "$path.$key", $leaves);
                } else {
                    if (++$leaves > 8000) {
                        throw ValidationException::withMessages([$path => 'Formularz przekracza limit pól.']);
                    }
                    $out[$key] = $value[$key] ?? null;
                }
            }
        }

        return $out;
    }

    public function normalize(string $section, array $input, array $sources, bool $complete): array
    {
        $leaves = 0;
        $out = [];
        foreach ($this->tree($section) as $root => $node) {
            $out[$root] = $this->shape($node, $input[$root] ?? [], $root, $leaves);
        }
        $out = $this->project($section, $out, $sources);
        $rules = [];
        foreach ($this->sections($section, $out, $sources) as $group) {
            foreach ($group['fields'] as $field) {
                $key = $field['key'];
                if ($field['readonly']) {
                    continue;
                }
                $rules[$key] = [$complete && $field['required'] && app(IsoSystemQuestionnaire::class)->visible($field, $out) ? 'required' : 'nullable', 'string', 'max:10000'];
                if ($field['options']) {
                    $rules[$key][] = Rule::in(array_keys($field['options']));
                }
                if ($field['type'] === 'date') {
                    $rules[$key][] = 'date_format:Y-m-d';
                }
                $value = data_get($out, $key);
                if ($field['numeric'] && filled($value) && is_scalar($value)) {
                    $number = self::number($value);
                    if ($number === null || (abs($number) > 1e15) || ($number < 0 && ! in_array($key, ['MODEL.a', 'MODEL.b', 'MODEL.c']))) {
                        throw ValidationException::withMessages([$key => 'Wpisz poprawną liczbę; użyj przecinka lub kropki dziesiętnej.']);
                    }
                    Arr::set($out, $key, (string) $number);
                }
            }
        }
        Validator::make($out, $rules)->validate();
        $this->validateContent($section, $out, $sources, $complete);

        return $out;
    }

    public function validateContent(string $section, array $out, array $sources, bool $complete): void
    {
        $errors = [];
        if ($complete && ! ($sources['ready'] ?? false)) {
            $errors['sources'] = 'Najpierw zatwierdź aktualne punkty źródłowe wskazane nad ankietą.';
        }
        foreach ($this->tree($section) as $root => $node) {
            if ($node['type'] !== 'list') {
                continue;
            }
            $rows = $out[$root] ?? [];
            $none = data_get($out, 'META.brakZdarzen') === 'tak' && in_array($root, ['PROJEKTY', 'ZAKUPY', 'KARTY']);
            if ($complete && ! $none && count($rows) < ($node['min'] ?? 0)) {
                $errors[$root] = 'Dodaj przynajmniej jedną pozycję.';
            }
            if ($none && count($rows)) {
                $errors[$root] = 'Zadeklarowano brak zdarzeń, ale lista zawiera pozycje. Popraw deklarację albo usuń puste pozycje.';
            }
            if ($complete && isset($node['codes']) && array_diff(array_keys($node['codes']), array_column($rows, 'kod'))) {
                $errors[$root] = 'Uzupełnij wszystkie pozycje słownika.';
            }
            $ids = [];
            foreach ($rows as $i => $row) {
                $id = $row['nr'] ?? $row['kod'] ?? null;
                if (filled($id)) {
                    if (in_array($id, $ids, true)) {
                        $errors["$root.$i.".(isset($row['nr']) ? 'nr' : 'kod')] = 'Identyfikator musi być unikalny w tej liście.';
                    }
                    $ids[] = $id;
                }
                if ($complete) {
                    foreach ($this->nestedLists($section, $root, $row) as $key) {
                        if (empty($row[$key])) {
                            $errors["$root.$i.$key"] = 'Dodaj co najmniej jedną pozycję lub uzupełnij właściwe uzasadnienie braku zastosowania.';
                        }
                    }
                }
            }
        }
        if ($complete && $section === '9-2') {
            foreach ($out['RAPORTY'] ?? [] as $i => $row) {
                if (($row['wlasnaPraca'] ?? '') === 'tak') {
                    $errors["RAPORTY.$i.wlasnaPraca"] = 'Audytor nie może audytować własnej pracy. Zapewnij bezstronność.';
                }
                if (! in_array($row['pozycja'] ?? '', array_column($out['PROGRAM'] ?? [], 'nr'), true)) {
                    $errors["RAPORTY.$i.pozycja"] = 'Wskaż numer pozycji z programu audytów.';
                }
            }
            foreach ($out['PROGRAM'] ?? [] as $i => $row) {
                if (($row['status'] ?? '') === 'wykonany' && ! in_array($row['nr'], array_column($out['RAPORTY'] ?? [], 'pozycja'), true)) {
                    $errors["PROGRAM.$i.status"] = 'Wykonany audyt wymaga raportu.';
                }
            }
        }
        if ($complete && $section === '9-3' && data_get($out, 'META.najwyzszeKier') !== 'tak') {
            $errors['META.najwyzszeKier'] = 'Przegląd zarządzania wymaga udziału najwyższego kierownictwa.';
        }
        if ($section === '10-1') {
            foreach ($out['KARTY'] ?? [] as $i => $row) {
                if (in_array($row['decyzja'] ?? '', ['zamknac', 'bez']) && filled($row['terminDowodu'] ?? null) && (($row['dataSprawdzenia'] ?? '') < $row['terminDowodu'] || $row['terminDowodu'] > today()->toDateString())) {
                    $errors["KARTY.$i.decyzja"] = 'Nie można zamknąć przed terminem uzyskania dowodu i sprawdzeniem skuteczności.';
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function progress(string $section, array $answers, array $sources): array
    {
        $done = $total = 0;
        foreach ($this->sections($section, $answers, $sources) as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['required'] && app(IsoSystemQuestionnaire::class)->visible($field, $answers)) {
                    $total++;
                    $done += filled(data_get($answers, $field['key'])) ? 1 : 0;
                }
            }
        }
        foreach ($this->tree($section) as $root => $node) {
            if ($node['type'] === 'list' && empty($answers[$root]) && ($node['min'] ?? 0) && ! (data_get($answers, 'META.brakZdarzen') === 'tak' && in_array($root, ['PROJEKTY', 'ZAKUPY', 'KARTY']))) {
                $total++;
            }
            if ($node['type'] === 'list') {
                foreach ($answers[$root] ?? [] as $row) {
                    foreach ($this->nestedLists($section, $root, $row) as $nested) {
                        if (empty($row[$nested])) {
                            $total++;
                        }
                    }
                }
            }
        }

        return app(QuestionnaireCompletion::class)->result($done, $total);
    }

    private function nestedLists(string $section, string $root, array $row): array
    {
        $allNo = ! empty($row['A']) && collect($row['A'])->every(fn ($r) => ($r['o'] ?? '') === 'nie');

        return match (true) {
            $section === '8-1' && $root === 'KARTY' => ['kryteria', 'tryby', 'sygnaly', 'utrzymanie', 'przekazanie'],
            $section === '8-2' && $root === 'PROJEKTY' && ! $allNo => ['wnioski'],
            $section === '8-3' && $root === 'ZAKUPY' && ! $allNo => ($row['porownanieNd'] ?? '') === 'tak' ? ['wymagania'] : ['wymagania', 'oferty'],
            $section === '9-2' && $root === 'RAPORTY' => ['ustalenia'],
            $section === '10-1' && $root === 'KARTY' && ($row['decyzja'] ?? '') !== 'bez' => ['dzialania'],
            default => [],
        };
    }

    public function listMinimum(string $section, string $path, array $answers, array $node): int
    {
        $parts = explode('.', $path);
        if (count($parts) === 3) {
            return in_array($parts[2], $this->nestedLists($section, $parts[0], data_get($answers, $parts[0].'.'.$parts[1], []))) ? 1 : 0;
        }

        return $node['min'] ?? 0;
    }

    public static function number(mixed $value): ?float
    {
        if (! is_scalar($value) || ! filled($value)) {
            return null;
        }
        $value = str_replace([',', ' ', "\u{00a0}", "\u{202f}"], ['.', '', '', ''], (string) $value);

        return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
    }

    public function calculations(string $section, array $answers): array
    {
        $out = [];
        if ($section === '8-3') {
            foreach ($answers['ZAKUPY'] ?? [] as $purchase) {
                foreach ($purchase['oferty'] ?? [] as $offer) {
                    $get = fn ($key) => self::number($offer[$key] ?? null);
                    $energy = match ($offer['tryb'] ?? '') {
                        'roczne' => $get('roczne'),
                        'moc' => $get('moc') !== null && $get('godziny') !== null ? $get('moc') * $get('godziny') / 1000 : null,
                        'jednostkowy' => $get('jednostkowe') !== null && $get('ilosc') !== null ? $get('jednostkowe') * $get('ilosc') / 1000 : null,
                        default => null,
                    };
                    $price = self::number($purchase['cenaEnergii'] ?? null);
                    $years = self::number($purchase['lata'] ?? null);
                    $total = $energy !== null && $price !== null && $years > 0 && $get('cena') !== null && $get('inne') !== null && ($purchase['porownanieNd'] ?? '') !== 'tak' ? $get('cena') + $energy * $price * $years + $get('inne') : null;
                    $out[] = ['label' => ($purchase['przedmiot'] ?? '').' · '.($offer['nazwa'] ?? ''), 'value' => $total, 'unit' => 'zł — koszt całkowity', 'note' => ($offer['minimalne'] ?? '') === 'tak' ? 'Spełnia zadeklarowane wymagania minimalne' : 'Nie kwalifikuje się do wyboru / brak oceny wymagań'];
                }
            }
        }
        if ($section === '9-1-1') {
            foreach ($answers['OKRESY'] ?? [] as $row) {
                $a = self::number(data_get($answers, 'MODEL.a'));
                $b = self::number(data_get($answers, 'MODEL.b'));
                $c = self::number(data_get($answers, 'MODEL.c'));
                $x = self::number($row['x1'] ?? null);
                $y = self::number($row['x2'] ?? null);
                $actual = self::number($row['rzeczywiste'] ?? null);
                $expected = $a !== null && $b !== null && $c !== null && $x !== null && $y !== null ? $a * $x + $b * $y + $c : null;
                $out[] = ['label' => $row['okres'] ?? '', 'value' => $expected > 0 && $actual !== null ? 100 * ($actual - $expected) / $expected : null, 'unit' => '% odchylenia', 'note' => 'Względem modelu a × x1 + b × x2 + c. Wynik nie zastępuje oceny warunków porównania.'];
            }
        }
        if ($section === '10-2') {
            foreach ($answers['MOZLIWOSCI'] ?? [] as $row) {
                $energy = self::number($row['potencjalMWh'] ?? null);
                $cost = self::number($row['koszt'] ?? null);
                $price = self::number(data_get($answers, 'META.'.(($row['nosnik'] ?? '') === 'el' ? 'cenaEl' : 'cenaGaz')));
                $saving = $energy !== null && $price !== null && in_array($row['nosnik'] ?? '', ['el', 'gaz']) ? $energy * $price : null;
                $out[] = ['label' => $row['nazwa'] ?? '', 'value' => $cost !== null && $saving > 0 ? $cost / $saving : null, 'unit' => 'lat — SPBT', 'note' => 'Prosty okres zwrotu; nie jest analizą zdyskontowaną.'];
            }
        }

        return $out;
    }

    public function warnings(string $section, array $answers, array $sources): array
    {
        $out = [];
        if ($section === '9-1-2' && empty($answers['WYM'])) {
            $out[] = 'Brak przyjętych wymagań zgodności z punktu 4.2. Zweryfikuj rejestr źródłowy; brak pozycji nie oznacza potwierdzenia zgodności.';
        }
        if ($section === '10-1') {
            foreach ($answers['KARTY'] ?? [] as $row) {
                if (preg_match('/błąd ludzki|nieuważ|zapomnia/iu', $row['przyczyna'] ?? '')) {
                    $out[] = 'Sprawdź przyczynę systemową — sam błąd ludzki nie wyjaśnia, dlaczego zabezpieczenia nie zadziałały.';
                }
            }
        }
        if ($section === '8-3') {
            foreach ($answers['ZAKUPY'] ?? [] as $row) {
                if (count($row['oferty'] ?? []) < 2 && ($row['porownanieNd'] ?? '') !== 'tak') {
                    $out[] = 'Porównanie zakupu zawiera mniej niż dwie oferty. Wyjaśnij podstawę wyboru lub brak porównania.';
                }
            }
        }
        if ($section === '8-2') {
            foreach ($answers['PROJEKTY'] ?? [] as $row) {
                if (in_array($row['etapAnalizy'] ?? '', ['zatwierdzenie', 'realizacja'])) {
                    $out[] = 'Analizę energetyczną rozpoczęto późno — sprawdź, czy wnioski mogą jeszcze wpłynąć na projekt.';
                }
            }
        }

        return array_values(array_unique($out));
    }

    public function checklist(string $section): array
    {
        foreach ($this->schema($section)['definitions'] as $key => $rows) {
            if (str_starts_with($key, 'LISTA_')) {
                return $rows;
            }
        }

        return [];
    }
}
