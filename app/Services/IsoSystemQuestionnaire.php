<?php

namespace App\Services;

use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use App\Models\IsoSectionDocument;
use App\Models\IsoStakeholderReview;
use App\Models\IsoSystemReview;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IsoSystemQuestionnaire
{
    private static int $sourceDepth = 0;

    private static array $sourceCache = [];

    public function schema(string $section): array
    {
        if (IsoExecutionQuestionnaire::handles($section)) {
            return app(IsoExecutionQuestionnaire::class)->schema($section);
        }
        if (str_starts_with($section, '7-')) {
            return app(IsoSupportQuestionnaire::class)->schema($section);
        }
        if (str_starts_with($section, '6-')) {
            return app(IsoPlanningQuestionnaire::class)->schema($section);
        }
        $name = match ($section) {
            '4-3' => 'scope', '4-4' => 'system', '5-1' => 'leadership', '5-2' => 'policy', '5-3' => 'roles'
        };

        return json_decode(file_get_contents(resource_path('iso50001/'.$name.'-v1.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function sources(IsoPlantProfile $profile, string $section): array
    {
        if (self::$sourceDepth === 0) {
            self::$sourceCache = [];
        }
        self::$sourceDepth++;
        try {
            $key = $profile->audit_id.':'.$profile->site_id.':'.$profile->id.':'.$profile->lock_version.':'.$section;

            return self::$sourceCache[$key] ??= $this->resolveSources($profile, $section);
        } finally {
            self::$sourceDepth--;
            if (self::$sourceDepth === 0) {
                self::$sourceCache = [];
            }
        }
    }

    private function resolveSources(IsoPlantProfile $profile, string $section): array
    {
        if (IsoExecutionQuestionnaire::handles($section)) {
            return app(IsoExecutionQuestionnaire::class)->sources($profile, $section);
        }
        if (str_starts_with($section, '7-')) {
            return app(IsoSupportQuestionnaire::class)->sources($profile, $section);
        }
        if (str_starts_with($section, '6-')) {
            return app(IsoPlanningQuestionnaire::class)->sources($profile, $section);
        }
        if (str_starts_with($section, '5-')) {
            return app(IsoLeadershipQuestionnaire::class)->sources($profile, $section);
        }
        $factor = IsoFactorReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->first();
        $party = IsoStakeholderReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->first();
        $scope = $section === '4-4' ? IsoSystemReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->where('section', '4-3')->first() : null;
        $f = app(IsoFactorQuestionnaire::class);
        $p = app(IsoStakeholderQuestionnaire::class);
        $profileReady = $profile->status === 'approved' && $profile->client_approval && $profile->auditor_approval;
        $factorReady = $profileReady && $factor?->status === 'approved' && $factor->client_approval && $factor->auditor_approval && $factor->source_hash === $f->hash($profile);
        $partyReady = $factorReady && $party?->status === 'approved' && $party->client_approval && $party->auditor_approval && $party->source_hash === $p->hash($profile, $factor);
        $rows = ['4-1' => $factor, '4-2' => $party];
        $approved = ['4-1' => (bool) $factorReady, '4-2' => (bool) $partyReady];
        if ($section === '4-4') {
            $scopeSources = $this->sources($profile, '4-3');
            $rows['4-3'] = $scope;
            $approved['4-3'] = $partyReady && $scope?->status === 'approved' && $scope->client_approval && $scope->auditor_approval && $scope->source_hash === $scopeSources['hash'];
        }
        $published = [];
        foreach ($rows as $key => $row) {
            $published[$key] = $approved[$key] && $row?->document_id && IsoSectionDocument::whereKey($row->document_id)->where('audit_id', $profile->audit_id)->where('section_id', $key)->exists();
        }
        $facts = $f->facts($profile);
        $confirmed = [];
        if ($factorReady) {
            foreach ($f->factors($facts, $factor->answers ?? []) as $item) {
                if ($item['visible'] && ($item['rodzaj'] === 'AUTO' || data_get($factor->answers, 'factors.'.$item['kod'].'.decyzja') === 'potwierdzony')) {
                    $confirmed[] = $item['kod'];
                }
            }
        }
        $parties = $partyReady ? $p->register($party->answers ?? [], $p->parties($profile, $factor), $party->consultant ?? []) : [];
        $snapshot = ['profile' => $profile->id, 'profile_hash' => $f->hash($profile), 'facts' => $facts, 'approved' => $approved, 'published' => $published,
            'factors' => $confirmed, 'parties' => $parties, 'factor_analysis' => $factor?->swot, 'scope' => $scope?->answers,
            'versions' => collect($rows)->map(fn ($row) => $row?->only(['id', 'lock_version', 'status', 'source_hash', 'document_id']))->all()];

        return $snapshot + ['hash' => hash('sha256', json_encode([$snapshot, $this->schema($section)], JSON_THROW_ON_ERROR)), 'ready' => $section === '4-3' ? (bool) $partyReady : ! in_array(false, $published, true)];
    }

    public function consequences(array $sources): array
    {
        $out = [];
        foreach ($this->schema('4-3')['konsekwencje_reguly'] as $index => $rule) {
            $matches = true;
            foreach ($rule['gdy'] as $key => $expected) {
                $actual = $sources['facts'][$key] ?? null;
                $matches = $matches && match ($key) {
                    '_TJ_powyzej' => is_numeric($sources['facts']['ZUZYCIE_TJ'] ?? null) && $sources['facts']['ZUZYCIE_TJ'] > $expected,
                    '_lokalizacji_wiecej_niz' => is_numeric($sources['facts']['FAKT_LOKALIZACJE'] ?? null) && $sources['facts']['FAKT_LOKALIZACJE'] > $expected,
                    default => is_array($expected) ? in_array($actual, $expected, true) : $actual === $expected,
                };
            }
            if (preg_match('/KTX-[A-Z]+-\d+/', $rule['zrodlo'], $match)) {
                $matches = $matches && in_array($match[0], $sources['factors'] ?? [], true);
            }
            if (preg_match('/STK-[A-Z]-\d+/', $rule['zrodlo'], $match)) {
                $matches = $matches && in_array($match[0], array_map(fn ($row) => $row['strona_kod'] ?? $row['kod'], $sources['parties'] ?? []), true);
            }
            if ($matches) {
                // Stable index from versioned source, never from the filtered list.
                $out['K-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = $rule;
            }
        }

        return $out;
    }

    public function seed(string $section, array $sources): array
    {
        if (IsoExecutionQuestionnaire::handles($section)) {
            return app(IsoExecutionQuestionnaire::class)->seed($section, $sources);
        }
        if (str_starts_with($section, '7-')) {
            return app(IsoSupportQuestionnaire::class)->seed($section, $sources);
        }
        if (str_starts_with($section, '6-')) {
            return app(IsoPlanningQuestionnaire::class)->seed($section, $sources);
        }
        if (str_starts_with($section, '5-')) {
            return app(IsoLeadershipQuestionnaire::class)->seed($section, $sources);
        }
        if ($section === '4-4') {
            return [];
        }
        $out = ['GRANICE' => [], 'UPRAW' => [[]], 'POZA' => []];
        foreach ($sources['facts']['FAKT_LOKALIZACJE_LISTA'] ?? [] as $row) {
            if (($row['c3'] ?? '') !== 'nie') {
                $out['GRANICE'][] = ['nazwa' => $row['c0'] ?? '', 'adres' => $row['c1'] ?? ''];
            }
        }
        $out['GRANICE'] = $out['GRANICE'] ?: [[]];
        foreach ($this->consequences($sources) as $key => $rule) {
            $out['KON'][$key]['tresc'] = $rule['konsekwencja'];
        }
        foreach ($this->energy($sources) as $key => $item) {
            if ($item['suggested']) {
                $out['ENERGIA'][$key] = ['status' => 'wystepuje', 'zrodlo' => 'Profil zakładu — do sprawdzenia z fakturami'];
            }
        }

        return $out;
    }

    public function energy(array $sources): array
    {
        $facts = $sources['facts'] ?? [];
        $names = array_column($facts['FAKT_NOSNIKI'] ?? [], 'c0');
        $out = [];
        foreach (['EE' => ['Energia elektryczna', ['energia elektryczna']], 'GAZ' => ['Gaz ziemny', ['gaz ziemny']], 'CIEPLO' => ['Ciepło', ['ciepło sieciowe']], 'OLEJ' => ['Olej opałowy', ['olej opałowy']], 'LPG' => ['LPG', ['LPG']], 'PALIWA' => ['Paliwa pojazdów', ['olej napędowy', 'benzyna', 'CNG']], 'INNE' => ['Inne', []]] as $code => [$label, $carriers]) {
            $suggested = count(array_intersect($names, $carriers)) > 0 || ($code === 'CIEPLO' && in_array($facts['FAKT_KOTLOWNIA'] ?? '', ['wlasne', 'siec', 'oba'])) || ($code === 'PALIWA' && ($facts['FAKT_FLOTA'] ?? '') === 'tak');
            $out[$code] = ['label' => $label, 'suggested' => $suggested];
        }

        return $out;
    }

    private function field(string $key, string $label, array $options = [], bool $required = true, ?array $when = null, string $type = 'text', string $help = ''): array
    {
        return compact('key', 'label', 'options', 'required', 'when', 'type', 'help');
    }

    public function sections(string $section, array $answers, array $sources): array
    {
        if (IsoExecutionQuestionnaire::handles($section)) {
            return app(IsoExecutionQuestionnaire::class)->sections($section, $answers, $sources);
        }
        if (str_starts_with($section, '7-')) {
            return app(IsoSupportQuestionnaire::class)->sections($section, $answers, $sources);
        }
        if (str_starts_with($section, '6-')) {
            return app(IsoPlanningQuestionnaire::class)->sections($section, $answers, $sources);
        }
        if (str_starts_with($section, '5-')) {
            return app(IsoLeadershipQuestionnaire::class)->sections($section, $answers, $sources);
        }
        $f = fn ($key, $label, $options = [], $required = true, $when = null, $type = 'text', $help = '') => $this->field($key, $label, $options, $required, $when, $type, $help);
        $yes = ['tak' => 'Tak', 'nie' => 'Nie'];
        $groups = [];
        if ($section === '4-3') {
            $fields = [];
            foreach ($this->consequences($sources) as $key => $rule) {
                $fields[] = $f("KON.$key.tresc", $rule['zrodlo'].' — konsekwencja dla zakresu', [], true, null, 'textarea', $rule['podstawa'].' Propozycja do weryfikacji przez konsultanta, nie automatyczna decyzja prawna.');
                $fields[] = $f("KON.$key.decyzja", 'Czy konsekwencja dotyczy zakładu?', ['potwierdzona' => 'Potwierdzam', 'odrzucona' => 'Nie dotyczy']);
                $fields[] = $f("KON.$key.powod", 'Uzasadnienie odrzucenia', [], true, ["KON.$key.decyzja", ['odrzucona']]);
            }
            $fields[] = $f('KON_WLASNE', 'Dodatkowe konsekwencje i źródła z 4.1 / 4.2', [], false, null, 'textarea');
            $groups['konsekwencje'] = ['title' => '1. Konsekwencje z 4.1 i 4.2', 'fields' => $fields];
            $groups['zakres'] = ['title' => '2. Zakres', 'fields' => [$f('ZAKRES.opis', 'Działania, procesy i jednostki objęte systemem', [], true, null, 'textarea'), $f('ZAKRES.dzialania', 'Obszary działalności', array_combine($areas = ['produkcja', 'utrzymanie ruchu', 'magazynowanie', 'transport wewnętrzny', 'biura', 'laboratorium', 'stołówka / socjalne', 'inne'], $areas), true, null, 'multi')]];
            foreach (['GRANICE' => '3. Granice', 'UPRAW' => '4. Uprawnienia', 'POZA' => '6. Poza granicami'] as $root => $title) {
                $fields = [];
                $rows = $answers[$root] ?? ($root === 'POZA' ? [] : [[]]);
                foreach (is_array($rows) ? array_slice($rows, 0, 20, true) : [] as $i => $row) {
                    foreach ($this->rowFields($root, (string) $i) as $field) {
                        $fields[] = $field;
                    }
                }
                $groups[strtolower($root)] = ['title' => $title, 'fields' => $fields, 'repeater' => $root];
            }
            $fields = [];
            foreach ($this->energy($sources) as $code => $item) {
                $base = "ENERGIA.$code";
                $fields[] = $f("$base.status", $item['label'].' — występowanie w granicach', ['wystepuje' => 'Występuje', 'nie' => 'Nie występuje'], $code !== 'INNE', null, 'text', $item['suggested'] ? 'Nośnik wynika z profilu zakładu. Nie wykluczaj go z powodu małego udziału.' : 'Ustal dla granic z sekcji 3.');
                foreach (['nazwa' => 'Jaki rodzaj energii', 'pochodzenie' => 'Pochodzenie', 'gdzie' => 'Gdzie wykorzystywany', 'zrodlo' => 'Źródło danych'] as $key => $label) {
                    if ($key === 'nazwa' && $code !== 'INNE') {
                        continue;
                    }
                    $fields[] = $f("$base.$key", $item['label'].' — '.$label, $key === 'pochodzenie' ? ['zakup' => 'Zakup', 'wlasne' => 'Wytwarzanie własne', 'oba' => 'Zakup i wytwarzanie własne'] : [], true, ["$base.status", ['wystepuje']]);
                }
            }
            $fields[] = $f('SPRAWDZONE.data', 'Data porównania listy nośników z fakturami', [], true, null, 'date');
            $fields[] = $f('SPRAWDZONE.czym', 'Faktury / ewidencja użyte do sprawdzenia');
            $groups['energia'] = ['title' => '5. Rodzaje energii', 'fields' => $fields];

            return array_replace(array_fill_keys(['konsekwencje', 'zakres', 'granice', 'upraw', 'energia', 'poza'], []), $groups);
        }
        $schema = $this->schema('4-4');
        foreach ($schema['pytania'] as $q) {
            $groups['decyzje']['fields'][] = $f('ODP.'.$q['kod'], $q['pytanie'], isset($q['opcje']) ? array_column($q['opcje'], 1, 0) : [], true, null, isset($q['opcje']) ? 'text' : 'textarea', $q['pomoc']);
        }
        foreach ($schema['czasowniki'] as $q) {
            $b = 'CZ.'.$q['kod'];
            $groups['czasowniki']['fields'][] = $f("$b.stan", $q['slowo'].' — '.$q['pytanie'], ['tak' => 'Tak', 'czesciowo' => 'Częściowo', 'nie' => 'Nie'], true, null, 'text', $q['opis']);
            $groups['czasowniki']['fields'][] = $f("$b.dowod", 'Dowody: '.implode(', ', $q['dowod']), [], true, ["$b.stan", ['tak', 'czesciowo']], 'textarea');
            $groups['czasowniki']['fields'][] = $f("$b.plan", 'Plan: co powstanie, kiedy i kto odpowiada', [], true, ["$b.stan", ['nie']], 'textarea');
            if ($q['wskazniki'] ?? false) {
                $groups['czasowniki']['fields'][] = $f("$b.wskazniki", 'Poprawa wyniku na wskaźnikach wobec linii bazowej', [], true, ["$b.stan", ['tak', 'czesciowo']], 'textarea');
            }
        }
        foreach ($schema['obieg'] as $q) {
            $groups['obieg']['fields'][] = $f('OB.'.$q['kod'].'.kto', $q['ogniwo'].' — '.$q['pyt'], [], true, null, 'text', $q['przyklad']);
            $groups['obieg']['fields'][] = $f('OB.'.$q['kod'].'.kiedy', $q['kiedy'], [], true);
        }
        foreach ($schema['progi'] as $q) {
            $groups['obieg']['fields'][] = $f('PG.'.$q['kod'], $q['etykieta'], isset($q['opcje']) ? array_column($q['opcje'], 1, 0) : [], true, null, 'text', $q['radatekst'] ?? '');
        }
        foreach ($schema['test'] as $q) {
            $b = 'TS.'.$q['kod'];
            $groups['test']['fields'][] = $f("$b.stan", $q['pyt'], ['jest' => 'Jest wyznaczona osoba', 'nikt' => 'Nikt', 'niewiem' => 'Nie wiem']);
            $groups['test']['fields'][] = $f("$b.kto", 'Osoba / funkcja i zastępstwo', [], true, ["$b.stan", ['jest']]);
            $groups['test']['fields'][] = $f("$b.start", 'Od czego zaczniecie — plan zapewnienia ciągłości', [], true, ["$b.stan", ['nikt', 'niewiem']], 'textarea');
        }
        foreach ($schema['procesy'] as $q) {
            $b = 'PROC.'.$q['kod'];
            $when = ($q['pokrywaIso'] ?? false) ? ['ODP.SYS-01', ['procedury', 'jeden', '']] : null;
            $groups['procesy']['fields'][] = $f("$b.wlasciciel", $q['kod'].' '.$q['nazwa'].' — właściciel', [], true, $when, 'text', 'Wynik: '.$q['wynik'].'. '.($q['ksiazka'] ?? 'Numer procedury do uzgodnienia'));
            $groups['procesy']['fields'][] = $f("$b.zastepca", $q['kod'].' — zastępca', [], (bool) ($q['kluczowy'] ?? false), $when);
            $groups['procesy']['fields'][] = $f("$b.forma", $q['kod'].' — forma zapisu', ['procedura' => 'Procedura', 'instrukcja' => 'Instrukcja', 'system' => 'Opis w systemie', 'inne' => 'Inny zapis', 'brak' => 'Brak opisu'], true, $when);
            $groups['procesy']['fields'][] = $f("$b.uwagi", $q['kod'].' — uzasadnienie braku opisu', [], true, ["$b.forma", ['brak']]);
            $groups['procesy']['fields'][] = $f("$b.trafia", $q['kod'].' — komu przekazywany jest wynik', [], true, $when, 'text', $q['trafia']);
            if ($q['pokrywaIso'] ?? false) {
                $groups['procesy']['fields'][] = $f("$b.integracja", $q['kod'].' — odwołanie do istniejącej dokumentacji ISO 14001 / 9001', [], true, ['ODP.SYS-01', ['nadbudowa']], 'text', 'Proces nie znika z systemu — wykorzystuje istniejącą dokumentację.');
            }
        }
        foreach ($schema['powiazania'] as $i => $q) {
            $groups['powiazania']['fields'][] = $f("POW.$i.potwierdzone", $q['z'].' → '.$q['do'].' — '.$q['wynik'], ['tak' => 'Działa', 'planowane' => 'Zaplanowane we wdrożeniu'], true, null, 'text', $q['sposob']);
        }
        foreach (['decyzje' => '1. Decyzje o systemie', 'czasowniki' => '2. Cztery pytania kontrolne', 'obieg' => '3. Obieg informacji', 'test' => '4. Test ciągłości', 'procesy' => '5. Procesy systemu', 'powiazania' => '6. Mapa powiązań'] as $key => $title) {
            $groups[$key]['title'] = $title;
        }

        return $groups;
    }

    public function rowFields(string $root, string $i, string $section = '', array $answers = [], array $sources = []): array
    {
        if (str_starts_with($section, '7-')) {
            return app(IsoSupportQuestionnaire::class)->rowFields($section, $root, $i, $answers, $sources);
        }
        if (str_starts_with($section, '6-')) {
            return app(IsoPlanningQuestionnaire::class)->rowFields($section, $root, $i, $answers, $sources);
        }
        if (in_array($root, IsoLeadershipQuestionnaire::REPEATERS)) {
            return app(IsoLeadershipQuestionnaire::class)->rowFields($root, $i);
        }
        $labels = match ($root) {
            'GRANICE' => ['nazwa' => 'Lokalizacja / obiekt', 'adres' => 'Adres', 'fizyczna' => 'Granica fizyczna — budynki i teren', 'jednostki' => 'Jednostki organizacyjne'],
            'UPRAW' => ['obiekt' => 'Obiekt / instalacja / usługa', 'kto' => 'Kto decyduje o energii', 'podstawa' => 'Podstawa uprawnień', 'brakUprawnien' => 'Czy brakuje uprawnień?', 'dzialanie' => 'Co robicie, aby uzyskać uprawnienia?', 'usluga' => 'Usługa zlecona na terenie zakładu?', 'nadzor' => 'Nadzór nad usługą (8.1)', 'uwagi' => 'Uwagi'],
            default => ['co' => 'Działalność / obiekt poza granicami', 'przynaleznosc' => 'Przynależność do organizacji', 'uprawnienia' => 'Uprawnienia do sterowania energią', 'przeplywy' => 'Przepływy energii przez granicę', 'dlaczego' => 'Udział procentowy — tylko pomocniczo', 'relacja' => 'Relacja z działalnością objętą systemem'],
        };
        $out = [];
        foreach ($labels as $key => $label) {
            $options = in_array($key, ['brakUprawnien', 'usluga']) ? ['tak' => 'Tak', 'nie' => 'Nie'] : ($key === 'podstawa' ? ['wlasnosc' => 'Własność', 'najem' => 'Najem', 'umowa' => 'Umowa', 'porozumienie' => 'Porozumienie', 'leasing' => 'Leasing'] : []);
            $when = match ($key) {
                'dzialanie' => ["$root.$i.brakUprawnien", ['tak']], 'nadzor' => ["$root.$i.usluga", ['tak']], default => null
            };
            $out[] = $this->field("$root.$i.$key", $label, $options, ! in_array($key, ['uwagi', 'dlaczego']), $when, $options ? 'text' : 'textarea');
        }

        return $out;
    }

    public function visible(array $field, array $answers): bool
    {
        return ! $field['when'] || in_array(data_get($answers, $field['when'][0]) ?? '', $field['when'][1], true);
    }

    public function normalize(string $section, array $input, array $sources, bool $complete): array
    {
        if (IsoExecutionQuestionnaire::handles($section)) {
            return app(IsoExecutionQuestionnaire::class)->normalize($section, $input, $sources, $complete);
        }
        if (str_starts_with($section, '7-')) {
            app(IsoSupportQuestionnaire::class)->validateRows($section, $input);
        }
        if (str_starts_with($section, '6-')) {
            app(IsoPlanningQuestionnaire::class)->validateRows($section, $input);
        }
        if (str_starts_with($section, '5-')) {
            app(IsoLeadershipQuestionnaire::class)->validateRows($section, $input, $complete);
        }
        Validator::make($input, ['GRANICE' => 'sometimes|array|max:20', 'UPRAW' => 'sometimes|array|max:20', 'POZA' => 'sometimes|array|max:20', 'GRANICE.*' => 'array', 'UPRAW.*' => 'array', 'POZA.*' => 'array'])->validate();
        foreach (['GRANICE', 'UPRAW', 'POZA'] as $root) {
            foreach (array_keys($input[$root] ?? []) as $index) {
                if (! ctype_digit((string) $index) || (int) $index > 100000) {
                    throw ValidationException::withMessages([$root => 'Nieprawidłowy identyfikator wiersza.']);
                }
            }
        }
        $rules = [];
        foreach ($this->sections($section, $input, $sources) as $group) {
            foreach ($group['fields'] as $field) {
                $key = $field['key'];
                $required = $complete && $field['required'] && $this->visible($field, $input);
                $rules[$key] = [$required ? 'required' : 'nullable', $field['type'] === 'multi' ? 'array' : 'string'];
                if ($field['type'] === 'multi') {
                    $rules[$key][] = 'max:8';
                    $rules[$key.'.*'] = ['string', Rule::in(array_keys($field['options']))];
                } else {
                    $rules[$key][] = 'max:10000';
                    if ($field['options']) {
                        $rules[$key][] = Rule::in(array_keys($field['options']));
                    }
                    if ($field['type'] === 'date') {
                        $rules[$key][] = 'date_format:Y-m-d';
                        if (! str_starts_with($section, '6-') && ! (str_starts_with($section, '7-') && str_ends_with($key, '.termin')) && ($section !== '5-2' || $key !== 'ZATW.od')) {
                            $rules[$key][] = 'before_or_equal:today';
                        }
                    }
                }
            }
        }
        $validated = Validator::make($input, $rules)->validate();
        $out = [];
        foreach (array_keys($rules) as $key) {
            if (! str_contains($key, '*')) {
                Arr::set($out, $key, data_get($validated, $key));
            }
        }
        if ($complete) {
            $errors = [];
            if ($section === '4-3') {
                foreach (['GRANICE', 'UPRAW'] as $root) {
                    if (empty($input[$root])) {
                        $errors[$root] = 'Dodaj przynajmniej jedną pozycję: '.$root;
                    }
                }
            } elseif ($section === '4-4' && data_get($out, 'ODP.SYS-02') !== 'tak') {
                $errors['ODP.SYS-02'] = 'Popraw zakres w punkcie 4.3 przed zatwierdzeniem 4.4.';
            }
            if (! $sources['ready']) {
                $errors['sources'] = preg_match('/^[567]-/', $section) ? 'Najpierw zatwierdź aktualne punkty źródłowe wskazane nad ankietą.' : ($section === '4-3' ? 'Najpierw zatwierdź profil, 4.1 i 4.2.' : 'Najpierw zatwierdź i wygeneruj aktualne dokumenty 4.1, 4.2 i 4.3.');
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }
        if (str_starts_with($section, '5-')) {
            app(IsoLeadershipQuestionnaire::class)->validateContent($section, $out, $complete);
            if ($section === '5-3') {
                $out['MAT'] = [];
                foreach ($out['ROLE'] ?? [] as $role) {
                    foreach ($role['MAT'] ?? [] as $code => $value) {
                        if (filled($role['id'] ?? null)) {
                            $out['MAT'][$code.'|'.$role['id']] = $value;
                        }
                    }
                }
            }
        }
        if ($section === '4-3') {
            foreach (['GRANICE', 'UPRAW', 'POZA'] as $root) {
                $out[$root] ??= [];
            }
        }

        if (str_starts_with($section, '6-')) {
            app(IsoPlanningQuestionnaire::class)->validateContent($section, $out, $sources, $complete);
        }

        if (str_starts_with($section, '7-')) {
            app(IsoSupportQuestionnaire::class)->validateContent($section, $out, $complete);
        }

        return $out;
    }

    public function progress(string $section, array $answers, array $sources): array
    {
        if (IsoExecutionQuestionnaire::handles($section)) {
            return app(IsoExecutionQuestionnaire::class)->progress($section, $answers, $sources);
        }
        $done = $total = 0;
        if (str_starts_with($section, '7-')) {
            foreach (IsoSupportQuestionnaire::MINIMUM[$section] as $root) {
                if (empty($answers[$root])) {
                    $total++;
                }
            }
        }
        foreach ($this->sections($section, $answers, $sources) as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['required'] && $this->visible($field, $answers)) {
                    $total++;
                    $done += filled(data_get($answers, $field['key'])) ? 1 : 0;
                }
            }
        }
        if (str_starts_with($section, '6-')) {
            foreach ($this->schema($section)['form']['rows'] as $root => $definition) {
                if ($root !== 'REWIZJE' && empty($answers[$root])) {
                    $total++;
                }
            }
        }
        if ($section === '4-3') {
            foreach (['GRANICE', 'UPRAW'] as $root) {
                if (isset($answers[$root]) && $answers[$root] === []) {
                    $total++;
                }
            }
        }

        if (str_starts_with($section, '5-')) {
            $minimum = match ($section) {
                '5-1' => ['CZLON' => 1, 'DECYZJE' => 3], '5-2' => ['PRZEGLADY' => 1], '5-3' => ['ROLE' => 1, 'UPR' => 1]
            };
            foreach ($minimum as $root => $count) {
                $total += max(0, $count - count($answers[$root] ?? []));
            }
            if ($section === '5-3') {
                foreach ($this->schema($section)['MAT_ZADANIA'] as $task) {
                    $total++;
                    $owners = collect($answers['ROLE'] ?? [])->filter(fn ($r) => ($r['MAT'][$task['kod']] ?? '') === 'O')->count();
                    $done += ($owners === 1 || ($owners > 1 && filled(data_get($answers, 'UWAGI.'.$task['kod'])))) ? 1 : 0;
                }
            }
        }

        return app(QuestionnaireCompletion::class)->result($done, $total);
    }

    public function checklistState(string $kind, array $answers): string
    {
        $schema = $this->schema('4-4');
        $ok = match ($kind) {
            'czasowniki' => collect($schema['czasowniki'])->every(fn ($q) => data_get($answers, 'CZ.'.$q['kod'].'.stan') === 'tak' && filled(data_get($answers, 'CZ.'.$q['kod'].'.dowod'))),
            'wskazniki' => data_get($answers, 'CZ.CZ-04.stan') === 'tak' && filled(data_get($answers, 'CZ.CZ-04.wskazniki')),
            'obieg' => collect($schema['obieg'])->every(fn ($q) => filled(data_get($answers, 'OB.'.$q['kod'].'.kto')) && filled(data_get($answers, 'OB.'.$q['kod'].'.kiedy'))),
            'sprawdzenie' => filled(data_get($answers, 'OB.OB-5.kto')) && filled(data_get($answers, 'OB.OB-5.kiedy')),
            'progi' => collect($schema['progi'])->every(fn ($q) => filled(data_get($answers, 'PG.'.$q['kod']))),
            'test' => collect($schema['test'])->every(fn ($q) => data_get($answers, 'TS.'.$q['kod'].'.stan') === 'jest' && filled(data_get($answers, 'TS.'.$q['kod'].'.kto'))),
            'procesy' => collect($schema['procesy'])->every(fn ($q) => (($q['pokrywaIso'] ?? false) && data_get($answers, 'ODP.SYS-01') === 'nadbudowa') ? filled(data_get($answers, 'PROC.'.$q['kod'].'.integracja')) : (filled(data_get($answers, 'PROC.'.$q['kod'].'.wlasciciel')) && filled(data_get($answers, 'PROC.'.$q['kod'].'.forma')) && data_get($answers, 'PROC.'.$q['kod'].'.forma') !== 'brak' && (! ($q['kluczowy'] ?? false) || filled(data_get($answers, 'PROC.'.$q['kod'].'.zastepca'))))),
            default => false,
        };

        return $ok ? 'Opisano jako działające — audytor weryfikuje dowody.' : 'Wymaga uwagi: brak danych, działanie częściowe lub dopiero planowane.';
    }

    public function warnings(string $section, array $answers, array $sources): array
    {
        if (IsoExecutionQuestionnaire::handles($section)) {
            return app(IsoExecutionQuestionnaire::class)->warnings($section, $answers, $sources);
        }
        if (str_starts_with($section, '7-')) {
            return app(IsoSupportQuestionnaire::class)->warnings($section, $answers, $sources);
        }
        $out = [];
        if (in_array($section, ['6-4', '6-5'])) {
            foreach ($sources['cards'] ?? [] as $card) {
                if (preg_match('/przych[oó]d|obrot|zł|PLN/iu', $card['wzor'] ?? '')) {
                    $out[] = ($card['nr'] ?? 'Karta').': wskaźnik finansowy może zależeć od cen. Sprawdź, czy rzeczywiście mierzy wynik energetyczny.';
                }
            }
            if ($section === '6-4') {
                foreach ($sources['upstream']['6-3']['SEU'] ?? [] as $seu) {
                    if (! in_array($seu['nr'] ?? '', array_column($answers['KARTY'] ?? [], 'seu'), true)) {
                        $out[] = 'SEU '.($seu['nr'] ?? '').' nie ma przypisanego wskaźnika. Uzupełnij kartę lub uzasadnij metodę oceny wyniku.';
                    }
                }
            }
        }
        if ($section === '5-1') {
            foreach ($answers['INT'] ?? [] as $code => $row) {
                if (($row['stan'] ?? '') === 'zapis') {
                    $out[] = $code.': integracja na papierze — wskaż rzeczywistą decyzję uwzględniającą energię.';
                }
            }
        }
        if ($section === '5-3') {
            if (empty($answers['SEU'])) {
                $out[] = 'Brak właścicieli SEU. Uzupełnij po wyznaczeniu znaczących użyć energii w punkcie 6.3.';
            }
            foreach ($answers['ROLE'] ?? [] as $role) {
                if (($role['MAT']['MAT-14'] ?? '') === 'O' && ($role['MAT']['MAT-15'] ?? '') === 'O') {
                    $out[] = ($role['nazwa'] ?? 'Rola').': organizacja i przeprowadzenie audytów przypisane tej samej roli — sprawdź bezstronność audytora.';
                }
            }
        }
        if ($section === '4-3') {
            foreach ($this->energy($sources) as $code => $item) {
                if ($item['suggested'] && data_get($answers, "ENERGIA.$code.status") === 'nie') {
                    $out[] = $item['label'].': profil wskazuje ten nośnik. Wyjaśnij rozbieżność i ewentualną działalność poza granicami.';
                }
            }
            foreach ($answers['POZA'] ?? [] as $row) {
                if (preg_match('/%|niewielk|znikom|kotłown|sprężarkown/iu', implode(' ', $row))) {
                    $out[] = 'Sprawdź uzasadnienie działalności poza granicami. Mały udział energii lub trudny obszar nie uzasadnia wyłączenia.';
                }
            }
        } elseif (filled(data_get($answers, 'ODP.SYS-07')) && data_get($answers, 'ODP.SYS-07') !== 'tak') {
            $out[] = 'Wyjaśnij rozróżnienie odchylenia od niezgodności i zasady podejmowania działań.';
        }

        return $out;
    }
}
