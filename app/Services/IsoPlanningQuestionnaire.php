<?php

namespace App\Services;

use App\Models\IsoEnergyCards;
use App\Models\IsoPlantProfile;
use App\Models\IsoSystemReview;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Chapter-six identifiers are persisted verbatim; formulas are never executed. */
class IsoPlanningQuestionnaire
{
    public function sources(IsoPlantProfile $profile, string $section): array
    {
        $cache = [];

        return $this->resolveSources($profile, $section, $cache);
    }

    private function resolveSources(IsoPlantProfile $profile, string $section, array &$cache): array
    {
        if (isset($cache[$section])) {
            return $cache[$section];
        }
        $cache['base'] ??= app(IsoSystemQuestionnaire::class)->sources($profile, '4-4');
        $cache['reviews'] ??= IsoSystemReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->get()->keyBy('section');
        $cache['cards'] ??= IsoEnergyCards::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->first()?->cards ?? [];
        $base = $cache['base'];
        $dependencies = match ($section) {
            '6-1' => ['5-2'], '6-3' => [], '6-4', '6-5' => ['6-3'],
            '6-2' => ['6-1', '6-3', '6-4', '6-5'], '6-6' => ['6-2', '6-3'],
        };
        $base['upstream'] = [];
        $dependencyReady = true;
        foreach ($dependencies as $key) {
            $row = $cache['reviews']->get($key);
            $base['upstream'][$key] = $row?->answers ?? [];
            $current = str_starts_with($key, '6-') ? $this->resolveSources($profile, $key, $cache) : app(IsoSystemQuestionnaire::class)->sources($profile, $key);
            $approved = $row?->status === 'approved' && $row->client_approval && $row->auditor_approval && $row->source_hash === $current['hash'] && $current['ready'];
            $base['approved'][$key] = (bool) $approved;
            $base['published'][$key] = (bool) ($approved && $row?->document_id);
            $dependencyReady = $dependencyReady && $approved;
            // Content, not approval dates, forms the dependency fingerprint.
        }
        $base['cards'] = $cache['cards'];
        $base['planning_section'] = $section;
        $base['plan'] = $cache['reviews']->get('6-6')?->answers['POZYCJE'] ?? [];
        $hashInput = $base;
        unset($hashInput['hash'], $hashInput['ready'], $hashInput['approved'], $hashInput['published']);
        if (! in_array($section, ['6-2', '6-4'])) {
            unset($hashInput['plan']);
        }
        if (in_array($section, ['6-1', '6-3'])) {
            unset($hashInput['cards']);
        }
        $base['hash'] = hash('sha256', json_encode([$hashInput, $this->schema($section)], JSON_THROW_ON_ERROR));
        $base['ready'] = $base['ready'] && $dependencyReady;

        return $cache[$section] = $base;
    }

    public function projectCards(string $section, array $answers, array $sources): array
    {
        if (in_array($section, ['6-4', '6-5'])) {
            $keys = array_keys($this->schema($section)['form']['rows']['KARTY'][1]);
            $answers['KARTY'] = array_map(fn ($card) => array_intersect_key($card, array_flip($keys)), $sources['cards']);
        }

        return $answers;
    }

    /** Called under the audit lock: simultaneous edits cannot overwrite the other half. */
    public function saveCards(IsoPlantProfile $profile, string $section, array $answers): void
    {
        if (! in_array($section, ['6-4', '6-5'])) {
            return;
        }
        $catalog = IsoEnergyCards::firstOrNew(['audit_id' => $profile->audit_id, 'site_id' => $profile->site_id]);
        $old = collect($catalog->cards ?? [])->keyBy('nr')->all();
        $cards = $section === '6-5' ? $old : [];
        foreach ($answers['KARTY'] ?? [] as $card) {
            $nr = $card['nr'] ?? '';
            if (! filled($nr)) {
                throw ValidationException::withMessages(['answers.KARTY' => 'Nadaj każdej karcie stały identyfikator.']);
            }
            if ($section === '6-5' && ! isset($old[$nr])) {
                throw ValidationException::withMessages(['answers.KARTY' => 'Najpierw utwórz kartę wskaźnika w 6.4.']);
            }
            $cards[$nr] = array_replace($old[$nr] ?? [], $card);
        }
        if ($cards === $old) {
            return;
        }
        foreach (array_diff(array_keys($old), array_keys($cards)) as $removed) {
            $referenced = IsoSystemReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->whereIn('section', ['6-2', '6-4', '6-5'])->get();
            foreach ($referenced as $row) {
                foreach (['ZADANIA', 'WARTOSCI', 'REWIZJE'] as $root) {
                    foreach ($row->answers[$root] ?? [] as $item) {
                        if (($item['enpi'] ?? null) === $removed) {
                            throw ValidationException::withMessages(['answers.KARTY' => 'Karta '.$removed.' ma powiązane zapisy. Nie zmieniaj jej identyfikatora ani jej nie usuwaj.']);
                        }
                    }
                }
            }
        }
        $catalog->cards = array_values($cards);
        $catalog->save();
    }

    public function schema(string $section): array
    {
        $name = ['6-1' => 'risk', '6-2' => 'goals', '6-3' => 'energy-review', '6-4' => 'indicators', '6-5' => 'baseline', '6-6' => 'data-plan'][$section];

        return json_decode(file_get_contents(resource_path('iso50001/'.$name.'-v1.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function sections(string $section, array $answers, array $sources): array
    {
        $schema = $this->schema($section);
        $groups = [];
        foreach ($schema['form']['groups'] as $i => $group) {
            foreach ($group['fields'] as &$field) {
                if ($section === '6-1' && str_starts_with($field['key'], 'META.')) {
                    // These three extra fields are absent from the supplied 6.1 HTML.
                    // Account approval remains recorded by the existing review workflow;
                    // it must not be presented as a fabricated management signature.
                    $field['required'] = false;
                }
                if (preg_match('/\.(data|utworzony|ostatni|stan)$/', $field['key'])) {
                    $field['type'] = 'date';
                }
                if ($field['key'] === 'NIE_DOTYCZY') {
                    $field['required'] = false;
                }
                if (in_array($field['key'], ['PROG.bez', 'PROG.zDzial', 'META.stale'])) {
                    $field['example'] = '100';
                }
            }
            unset($field);
            $groups['planning-'.$i] = $group;
        }
        foreach ($schema['form']['rows'] as $root => [$title]) {
            $fields = [];
            foreach (array_keys($answers[$root] ?? []) as $i) {
                array_push($fields, ...$this->rowFields($section, $root, (string) $i, $answers, $sources));
            }
            $groups[strtolower($root)] = ['title' => $title, 'fields' => $fields, 'repeater' => $root];
        }
        foreach ($schema['DANE_WEJ'] ?? [] as $item) {
            $key = 'WEJ.'.$item['kod'];
            $groups['wej']['title'] = 'Dane wejściowe';
            $groups['wej']['fields'][] = $this->field($key.'.stan', $item['tresc'], ['tak' => 'Uwzględniono', 'nie' => 'Nie uwzględniono']);
            $groups['wej']['fields'][] = $this->field($key.'.dowod', 'Dowód / uzasadnienie — '.$item['tresc']);
        }
        foreach (['METODY' => 'MET', 'POLA_ZASAD' => 'ZASADY'] as $list => $root) {
            foreach ($schema[$list] ?? [] as $item) {
                $groups[strtolower($root)]['title'] = $root === 'MET' ? 'Metody i kryteria' : 'Zasady zbierania danych';
                $groups[strtolower($root)]['fields'][] = $this->field($root.'.'.$item['kod'], $item['nazwa'], [], true, $item['pomoc'] ?? '');
            }
        }

        return $groups;
    }

    private function field(string $key, string $label, array $options = [], bool $required = true, string $help = ''): array
    {
        return ['key' => $key, 'label' => $label, 'options' => $options, 'required' => $required, 'when' => null, 'type' => $options ? 'text' : 'textarea', 'help' => $help];
    }

    public function rowFields(string $section, string $root, string $i, array $answers = [], array $sources = []): array
    {
        $labels = $this->schema($section)['form']['rows'][$root][1] ?? [];
        $fields = [];
        foreach ($labels as $key => $label) {
            $options = match ($key) {
                'P', 'W' => ['1' => '1 — niski', '2' => '2 — średni', '3' => '3 — wysoki'],
                'kluczowa', 'brakZmiennych' => ['tak' => 'Tak', 'nie' => 'Nie'],
                'postac' => ['bezwzgledny' => 'Wartość bezwzględna', 'jednostkowy' => 'Wskaźnik jednostkowy', 'model' => 'Model'],
                'obszar' => ['organizacja' => 'Organizacja', 'seu' => 'Znaczące użycie energii', 'inny' => 'Inny obszar'],
                'metoda' => ['P' => 'Pomiar', 'O' => 'Obliczenie', 'S' => 'Oszacowanie'],
                'status' => ['plan' => 'Planowane', 'wtoku' => 'W toku', 'zakonczone' => 'Zakończone'],
                default => [],
            };
            if ($root === 'CELE' && $key === 'rodzaj') {
                $options = ['wynik' => 'Poprawa wyniku energetycznego', 'system' => 'Cel systemowy'];
            }
            $optional = ($section === '6-4' && $key === 'plan') || ($section === '6-5' && $root === 'REWIZJE' && $key === 'zatwierdzil') || in_array($key, ['wynikOceny', 'postep', 'adnotacja', 'grupa', 'uzasadnienieNie', 'modelBazowy', 'daneZmiennych', 'seu', 'zmienne', 'ocenaModelu']);
            if (in_array($root, ['RYZYKA', 'SZANSE']) && ! in_array($key, ['nr', 'zrodlo', 'opis', 'P', 'W'])) {
                $optional = true; // Required according to the computed priority in validateContent.
            }
            $field = $this->field("$root.$i.$key", $label, $options, ! $optional);
            if ($key === 'nr') {
                $field['example'] = $root.'-'.$i;
            }
            $linked = match ($key) {
                'doCelu' => array_column($answers['CELE'] ?? [], 'nr'),
                'enpi' => array_column($section === '6-4' ? ($answers['KARTY'] ?? []) : ($sources['cards'] ?? []), 'nr'),
                'plan' => array_column($sources['plan'] ?? [], 'nr'),
                'seu' => array_column($section === '6-3' ? ($answers['SEU'] ?? []) : ($sources['upstream']['6-3']['SEU'] ?? []), 'nr'),
                default => [],
            };
            if ($linked) {
                $field['help'] = 'Dostępne identyfikatory: '.implode(', ', array_filter($linked));
                $field['example'] = $linked[0];
            }
            if (in_array($key, ['mwh', 'zl', 'capex', 'biezacy', 'poprzedni', 'przelicznik'])) {
                $field['example'] = $key === 'przelicznik' ? '1000' : '100';
            }
            if (in_array($key, ['termin', 'terminOceny', 'data'])) {
                $field['type'] = 'date';
            }
            if ($key === 'kat') {
                $field['options'] = array_column($this->schema('6-6')['KATEGORIE'], 1, 0);
                $field['type'] = 'multi';
            }
            $fields[] = $field;
        }
        if ($section === '6-2' && $root === 'ZADANIA') {
            foreach ($this->schema($section)['POLA_WER'] as $item) {
                $fields[] = $this->field("ZADANIA.$i.WER.".$item['kod'], $item['nazwa'], [], $item['wymagane'], $item['pomoc']);
            }
        }

        return $fields;
    }

    public function seed(string $section, array $sources): array
    {
        $answers = [];
        foreach ($this->schema($section)['form']['rows'] as $root => $definition) {
            $answers[$root] = in_array($root, ['REWIZJE']) ? [] : [[]];
        }

        if ($section === '6-3' && ! empty($sources['facts']['FAKT_NOSNIKI'])) {
            $answers['NOSNIKI'] = array_slice(array_map(fn ($row) => ['nazwa' => $row['c0'] ?? ''], $sources['facts']['FAKT_NOSNIKI']), 0, 20);
        }

        return $answers;
    }

    public function validateRows(string $section, array $input): void
    {
        $rules = [];
        foreach ($this->schema($section)['form']['rows'] as $root => $definition) {
            $rules[$root] = 'sometimes|array|max:20';
            $rules[$root.'.*'] = 'array';
        }
        Validator::make($input, $rules)->validate();
        foreach (array_keys($rules) as $root) {
            if (str_contains($root, '*')) {
                continue;
            }
            foreach (array_keys($input[$root] ?? []) as $index) {
                if (! ctype_digit((string) $index) || (int) $index > 100000) {
                    throw ValidationException::withMessages([$root => 'Nieprawidłowy identyfikator wiersza.']);
                }
            }
        }
    }

    public function validateContent(string $section, array &$answers, array $sources, bool $complete): void
    {
        $errors = [];
        foreach ($this->schema($section)['form']['rows'] as $root => $definition) {
            $answers[$root] ??= [];
            $optionalRows = $root === 'REWIZJE' || ($root === 'ZADANIA' && ! in_array('wynik', array_column($answers['CELE'] ?? [], 'rodzaj'), true));
            if ($complete && ! $optionalRows && empty($answers[$root])) {
                $errors[$root] = 'Dodaj przynajmniej jedną pozycję.';
            }
            $ids = [];
            foreach ($answers[$root] as $i => &$row) {
                if (filled($row['nr'] ?? null)) {
                    if (! preg_match('/^[\pL\pN_.-]{1,40}$/u', $row['nr']) || in_array($row['nr'], $ids, true)) {
                        $errors["$root.$i.nr"] = 'Identyfikator musi być unikalny; użyj liter, cyfr, myślnika lub podkreślenia.';
                    }
                    $ids[] = $row['nr'];
                }
                $numeric = match ($root) {
                    'NOSNIKI' => ['poprzedni', 'biezacy', 'przelicznik'],
                    'OBSZARY' => ['mwh'], 'MOZL' => ['mwh', 'zl', 'capex'], default => [],
                };
                foreach ($numeric as $key) {
                    if (filled($row[$key] ?? null)) {
                        $value = str_replace([',', ' ', "\u{00a0}"], ['.', '', ''], $row[$key]);
                        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > 1.0e15 || ($key === 'przelicznik' && (float) $value <= 0)) {
                            $errors["$root.$i.$key"] = 'Wpisz prawidłową nieujemną liczbę (przelicznik musi być dodatni).';
                        } else {
                            $row[$key] = $value;
                        }
                    }
                }
                if ($complete && in_array($root, ['RYZYKA', 'SZANSE'])) {
                    $priority = (int) ($row['P'] ?? 0) * (int) ($row['W'] ?? 0);
                    foreach ($priority >= 3 ? ['dzialanie', 'zasoby', 'odp', 'termin', 'powiazanie', 'ocena', 'terminOceny'] : [] as $key) {
                        if (! filled($row[$key] ?? null)) {
                            $errors["$root.$i.$key"] = 'Priorytet ≥ 3 wymaga planu i metody oceny skuteczności.';
                        }
                    }
                    if ($priority >= 6 && ! filled($row['zatwierdzil'] ?? null)) {
                        $errors["$root.$i.zatwierdzil"] = 'Priorytet ≥ 6 wymaga decyzji kierownictwa.';
                    }
                    if (preg_match('/^wykonano[.! ]*$/iu', $row['ocena'] ?? '')) {
                        $errors["$root.$i.ocena"] = 'Samo wykonanie nie jest metodą oceny skuteczności.';
                    }
                }
            }
            unset($row);
        }
        $cards = collect($sources['cards'] ?? [])->keyBy('nr');
        if ($section === '6-2') {
            $goals = array_column($answers['CELE'], 'nr');
            foreach (['ZADANIA', 'DZIAL'] as $root) {
                foreach ($answers[$root] as $i => $row) {
                    if (filled($row['doCelu'] ?? null) && ! in_array($row['doCelu'], $goals, true)) {
                        $errors["$root.$i.doCelu"] = 'Wybierz istniejący identyfikator celu.';
                    }
                    if ($root === 'ZADANIA' && filled($row['enpi'] ?? null) && ! $cards->has($row['enpi'])) {
                        $errors["$root.$i.enpi"] = 'Brak karty o tym identyfikatorze w 6.4.';
                    }
                    if ($complete && $root === 'ZADANIA' && ! filled($cards->get($row['enpi'] ?? '')['wartoscEnB'] ?? null)) {
                        $errors["$root.$i.enpi"] = 'Uzupełnij linię bazową karty w 6.5.';
                    }
                }
            }
            $answers['WER'] = [];
            foreach ($answers['ZADANIA'] as $row) {
                if (filled($row['nr'] ?? null)) {
                    $answers['WER'][$row['nr']] = $row['WER'] ?? [];
                }
            }
            if ($complete) {
                foreach ($answers['CELE'] as $i => $goal) {
                    if (! in_array($goal['nr'], array_column($answers['DZIAL'], 'doCelu'), true)) {
                        $errors["CELE.$i.nr"] = 'Każdy cel wymaga planu działania.';
                    }
                    if ($goal['rodzaj'] === 'wynik' && ! in_array($goal['nr'], array_column($answers['ZADANIA'], 'doCelu'), true)) {
                        $errors["CELE.$i.nr"] = 'Cel poprawy wyniku wymaga mierzalnego zadania.';
                    }
                }
            }
        }
        $planIds = array_column($sources['plan'] ?? [], 'nr');
        foreach ($section === '6-3' ? ['bez', 'zDzial'] : [] as $key) {
            $raw = $answers['PROG'][$key] ?? null;
            if (filled($raw)) {
                $value = str_replace([',', ' ', "\u{00a0}"], ['.', '', ''], $raw);
                if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > 1.0e15) {
                    $errors['PROG.'.$key] = 'Wpisz nieujemną prognozę w MWh.';
                } else {
                    $answers['PROG'][$key] = $value;
                }
            }
        }
        if ($section === '6-4') {
            foreach ($answers['KARTY'] as $i => $card) {
                foreach (array_filter(array_map('trim', explode(',', $card['plan'] ?? ''))) as $id) {
                    if (! in_array($id, $planIds, true)) {
                        $errors["KARTY.$i.plan"] = 'Pozycja '.$id.' nie istnieje w planie 6.6. Zapisz najpierw plan (może być jeszcze niezatwierdzony).';
                    }
                }
            }
        }
        if ($section === '6-6') {
            $ids = array_column($answers['POZYCJE'], 'nr');
            foreach ($sources['cards'] ?? [] as $card) {
                foreach (array_filter(array_map('trim', explode(',', $card['plan'] ?? ''))) as $id) {
                    if (! in_array($id, $ids, true)) {
                        $errors['POZYCJE'] = 'Pozycja '.$id.' jest używana przez kartę '.$card['nr'].'. Najpierw zmień powiązanie w 6.4.';
                    }
                }
            }
        }
        if ($complete && $section === '6-3') {
            $totals = $this->calculations($section, $answers);
            if ($totals['energy'] <= 0 || $totals['difference'] > 5) {
                $errors['OBSZARY'] = 'Bilans obszarów musi być dodatni i zgodny z bilansem nośników (różnica do 5%).';
            }
            foreach ($answers['SEU'] as $i => $row) {
                if (($row['brakZmiennych'] ?? '') !== 'tak' && ! filled($row['zmienne'] ?? null)) {
                    $errors["SEU.$i.zmienne"] = 'Wskaż istotne zmienne albo potwierdź ich brak.';
                }
                $areaIds = array_merge(array_column($answers['OBSZARY'], 'nr'), array_column($answers['OBSZARY'], 'grupa'));
                if (! in_array($row['nr'], $areaIds, true)) {
                    $errors["SEU.$i.nr"] = 'SEU musi wskazywać identyfikator obszaru lub wspólnej grupy obszarów.';
                }
            }
            foreach ($answers['OBSZARY'] as $i => $row) {
                if (! in_array($row['nr'], array_column($answers['SEU'], 'nr'), true) && ! in_array($row['grupa'] ?? '', array_column($answers['SEU'], 'nr'), true) && ! filled($row['uzasadnienieNie'] ?? null)) {
                    $errors["OBSZARY.$i.uzasadnienieNie"] = 'Uzasadnij, dlaczego obszar nie jest SEU.';
                }
            }
        }
        if ($complete && $section === '6-4' && ! in_array('organizacja', array_column($answers['KARTY'], 'obszar'), true)) {
            $errors['KARTY'] = 'Dodaj wskaźnik dla całej organizacji.';
        }
        if ($complete && $section === '6-4') {
            foreach ($answers['KARTY'] as $i => $card) {
                if (! in_array($card['nr'], array_column($answers['WARTOSCI'], 'enpi'), true)) {
                    $errors["KARTY.$i.nr"] = 'Zapisz wartość tego wskaźnika wraz z okresem.';
                }
            }
        }
        foreach ($answers['KARTY'] ?? [] as $i => $card) {
            if ($complete && $section === '6-4' && ($card['postac'] ?? '') === 'model' && ! filled($card['ocenaModelu'] ?? null)) {
                $errors["KARTY.$i.ocenaModelu"] = 'Model wymaga oceny danych, błędów i wartości odstających — nie tylko R².';
            }
            if ($section === '6-5' && filled($card['nr'] ?? null) && ! $cards->has($card['nr'])) {
                $errors["KARTY.$i.nr"] = 'Wybierz istniejący identyfikator karty z 6.4.';
            }
            if ($complete && $section === '6-5' && filled($cards->get($card['nr'])['zmienne'] ?? null) && ! filled($card['daneZmiennych'] ?? null)) {
                $errors["KARTY.$i.daneZmiennych"] = 'Zachowaj dane istotnych zmiennych z okresu bazowego.';
            }
            if ($complete && $section === '6-4' && ($card['obszar'] ?? '') === 'seu' && filled($card['seu'] ?? null) && ! in_array($card['seu'] ?? '', array_column($sources['upstream']['6-3']['SEU'] ?? [], 'nr'), true)) {
                $errors["KARTY.$i.seu"] = 'Wskaż istniejący identyfikator SEU z 6.3.';
            }
        }
        foreach (['WARTOSCI', 'REWIZJE'] as $root) {
            foreach ($answers[$root] ?? [] as $i => $row) {
                $available = $section === '6-4' ? array_column($answers['KARTY'], 'nr') : $cards->keys()->all();
                if (filled($row['enpi'] ?? null) && ! in_array($row['enpi'], $available, true)) {
                    $errors["$root.$i.enpi"] = 'Nieznany identyfikator karty EnPI.';
                }
            }
        }
        if ($complete && $section === '6-6') {
            if (! in_array('tak', array_column($answers['POZYCJE'], 'kluczowa'), true)) {
                $errors['POZYCJE'] = 'Wskaż kluczowe charakterystyki oraz sposób kontroli pomiarów.';
            }
            $categories = array_unique(array_merge([], ...array_column($answers['POZYCJE'], 'kat')));
            if (array_diff(['a', 'b', 'c', 'd', 'e'], $categories) && ! filled($answers['NIE_DOTYCZY'] ?? null)) {
                $errors['NIE_DOTYCZY'] = 'Uzupełnij brakujące kategorie danych albo uzasadnij, dlaczego nie dotyczą zakładu.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function calculations(string $section, array $answers): array
    {
        $energy = 0;
        foreach ($answers['NOSNIKI'] ?? [] as $row) {
            $energy += (float) str_replace(',', '.', $row['biezacy'] ?? '0') * (float) str_replace(',', '.', $row['przelicznik'] ?? '0') / 1000;
        }
        $areas = array_sum(array_map(fn ($r) => (float) ($r['mwh'] ?? 0), $answers['OBSZARY'] ?? []));

        return ['energy' => $energy, 'areas' => $areas, 'difference' => $energy > 0 ? abs($areas - $energy) / $energy * 100 : 100];
    }
}
