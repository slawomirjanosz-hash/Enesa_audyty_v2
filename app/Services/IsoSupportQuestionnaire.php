<?php

namespace App\Services;

use App\Models\IsoPlantProfile;
use App\Models\IsoSystemReview;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Stable source identifiers from ENESA 7.1–7.3. No executable source formulas. */
class IsoSupportQuestionnaire
{
    public const MINIMUM = ['7-1' => ['OSOBY', 'ZADANIA', 'DECYZJE'], '7-2' => ['KOMPETENCJE', 'ROLE', 'PLAN'], '7-3' => ['GRUPY', 'KANALY', 'SPRAWDZENIA']];

    public function schema(string $section): array
    {
        $checklists = json_decode(file_get_contents(resource_path('iso50001/support-v1.json')), true, 512, JSON_THROW_ON_ERROR);

        return ['version' => 1, 'section' => $section, 'rows' => $this->rows($section), 'checklist' => $checklists[$section]];
    }

    public function sources(IsoPlantProfile $profile, string $section): array
    {
        $base = app(IsoSystemQuestionnaire::class)->sources($profile, '4-4');
        $keys = match ($section) {
            '7-1' => ['5-1', '5-3', '6-2', '6-6'],
            '7-2' => ['5-3', '6-1', '6-2', '6-3'],
            '7-3' => ['5-2', '6-2', '6-3', '7-2'],
        };
        $reviews = IsoSystemReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->whereIn('section', $keys)->get()->keyBy('section');
        $base['upstream'] = [];
        foreach ($keys as $key) {
            $row = $reviews->get($key);
            $base['upstream'][$key] = $row?->answers ?? [];
            $current = app(IsoSystemQuestionnaire::class)->sources($profile, $key);
            $base['support_dependencies'][$key] = $current['hash'];
            $ok = $row?->status === 'approved' && $row->client_approval && $row->auditor_approval && $row->source_hash === $current['hash'] && $current['ready'];
            $base['approved'][$key] = (bool) $ok;
            $base['published'][$key] = (bool) ($ok && $row?->document_id);
        }
        // No approval cycle: 7.1 can supply resources before competency actions are complete.
        $base['ready'] = $base['ready'] && ($base['approved'][$section === '7-3' ? '7-2' : '5-3'] ?? false);
        $base['support_section'] = $section;
        $fingerprint = $base;
        unset($fingerprint['hash'], $fingerprint['ready'], $fingerprint['approved'], $fingerprint['published']);
        $base['hash'] = hash('sha256', json_encode([$fingerprint, $this->schema($section)], JSON_THROW_ON_ERROR));

        return $base;
    }

    private function rows(string $section): array
    {
        return match ($section) {
            '7-1' => [
                'OSOBY' => ['Osoby i dostępny czas', ['nazwa' => 'Osoba / funkcja', 'rola' => 'Rola w systemie', 'dostepne' => 'Dostępne godziny w okresie', 'podstawa' => 'Podstawa przydzielenia czasu']],
                'ZADANIA' => ['Zadania i potrzebny czas', ['nazwa' => 'Zadanie', 'rodzaj' => 'Rodzaj zadania', 'osoba' => 'Osoba / funkcja', 'godziny' => 'Łączna liczba godzin w okresie', 'zrodlo' => 'Źródło zadania (np. 6.2 / 6.6)', 'zdjete' => 'Z jakich obowiązków zwolniono']],
                'DECYZJE' => ['Decyzje o zasobach', ['tresc' => 'Treść decyzji', 'rodzaj' => 'Rodzaj zasobu', 'kto' => 'Kto zdecydował', 'data' => 'Data decyzji', 'gdzie' => 'Dowód / miejsce zapisu', 'powiazanie' => 'Powiązanie z zadaniem / potrzebą']],
            ],
            '7-2' => [
                'ROLE' => ['Role objęte oceną', ['nazwa' => 'Rola / grupa', 'opis' => 'Zakres wpływu na energię', 'wykonawca' => 'Wykonawca zewnętrzny?', 'zrodlo' => 'Identyfikator roli z 5.3']],
                'KOMPETENCJE' => ['Kompetencje F-EnMS-24', ['nazwa' => 'Kompetencja opisana zadaniem', 'jakSprawdzamy' => 'Jak sprawdzamy umiejętność']],
                'LUKI' => ['Luki i działania', ['dotyczy' => 'Kompetencja / rola', 'osoba' => 'Funkcja / grupa objęta działaniem (bez danych osobowych)', 'luka' => 'Opis luki', 'dzialanie' => 'Działanie uzupełniające', 'forma' => 'Forma działania', 'termin' => 'Termin', 'ocena' => 'Metoda oceny skuteczności', 'wynik' => 'Wynik sprawdzenia', 'status' => 'Status', 'dowod' => 'Miejsce przechowywania dowodu kompetencji']],
                'PLAN' => ['Wspólny plan działań i szkoleń F-EnMS-12 (7.2 i 7.3)', ['nr' => 'Stały numer działania', 'temat' => 'Temat', 'punkty' => 'Punkty normy', 'grupa' => 'Grupa docelowa', 'forma' => 'Forma', 'prowadzacy' => 'Prowadzący / funkcja', 'czas' => 'Czas trwania', 'termin' => 'Termin', 'ocena' => 'Ocena skuteczności — metoda', 'status' => 'Status']],
            ],
            '7-3' => [
                'GRUPY' => ['Grupy i cztery elementy świadomości', ['nazwa' => 'Grupa', 'osoby' => 'Role / zmiany (bez danych osobowych)', 'wykonawca' => 'Wykonawca zewnętrzny?', 'polityka' => 'Co grupa wie o polityce energetycznej?', 'wklad' => 'Własny wkład i korzyści z poprawy', 'wplyw' => 'Wpływ własnych zachowań na zużycie energii', 'konsekwencje' => 'Konsekwencje niespełnienia wymagań']],
                'KANALY' => ['Kanały budowania świadomości', ['nazwa' => 'Kanał', 'tresc' => 'Treść', 'rytm' => 'Częstotliwość', 'kto' => 'Odpowiedzialna funkcja', 'grupy' => 'Grupy odbiorców', 'elementy' => 'Elementy świadomości']],
                'SPRAWDZENIA' => ['Historia sprawdzeń świadomości — nowe zapisy', ['data' => 'Data', 'kto' => 'Sprawdzająca funkcja', 'osoby' => 'Sprawdzana grupa / zmiana', 'wynik' => 'Wynik rozmowy / sprawdzenia', 'luki' => 'Stwierdzone luki', 'dzialanie' => 'Reakcja na luki / odwołanie do wcześniejszego zapisu']],
                'PRZESZKODY' => ['Zgłoszone przeszkody', ['opis' => 'Przeszkoda', 'grupa' => 'Grupa', 'zglosil' => 'Zgłaszająca funkcja', 'decyzja' => 'Decyzja', 'stan' => 'Stan', 'efekt' => 'Efekt / uzasadnienie odrzucenia']],
            ],
        };
    }

    private function field(string $key, string $label, array $options = [], bool $required = true, string $type = 'textarea', string $help = ''): array
    {
        return compact('key', 'label', 'options', 'required', 'type', 'help') + ['when' => null];
    }

    public function sections(string $section, array $answers, array $sources): array
    {
        $meta = match ($section) {
            '7-1' => ['okres' => 'Okres bilansowania (wszystkie godziny dotyczą tego okresu)', 'zatwierdzil' => 'Osoba zatwierdzająca zasoby', 'data' => 'Data decyzji', 'przeglad' => 'Termin / rytm przeglądu wystarczalności'],
            '7-2' => ['utworzono' => 'Data utworzenia', 'aktualizacja' => 'Data aktualizacji', 'opracowal' => 'Opracował', 'zatwierdzil' => 'Osoba zatwierdzająca matrycę', 'przeglad' => 'Częstotliwość przeglądu'],
            '7-3' => ['zatwierdzil' => 'Osoba odpowiedzialna', 'data' => 'Data ustalenia zasad', 'rytmSprawdzen' => 'Rytm sprawdzeń świadomości'],
        };
        $groups = ['metryka' => ['title' => 'Metryka i zasady', 'fields' => []]];
        foreach ($meta as $key => $label) {
            $groups['metryka']['fields'][] = $this->field('META.'.$key, $label, [], true, in_array($key, ['data', 'utworzono', 'aktualizacja']) ? 'date' : 'text');
        }
        foreach ($this->rows($section) as $root => [$title]) {
            $fields = [];
            foreach (array_keys($answers[$root] ?? []) as $i) {
                array_push($fields, ...$this->rowFields($section, $root, (string) $i, $answers, $sources));
            }
            $groups[strtolower($root)] = ['title' => $title, 'fields' => $fields, 'repeater' => $root];
        }
        if ($section === '7-1') {
            $groups['zasoby'] = ['title' => 'Potrzeby i zapewnione zasoby', 'fields' => []];
            foreach (['ludzie' => 'Ludzie i ich czas', 'pieniadze' => 'Pieniądze', 'infra' => 'Infrastruktura i narzędzia', 'zewn' => 'Wiedza z zewnątrz'] as $code => $label) {
                foreach (['potrzeba' => 'Potrzeba', 'decyzja' => 'Co zapewniono i na jakiej podstawie', 'luka' => 'Luka / brak zasobów'] as $key => $suffix) {
                    $groups['zasoby']['fields'][] = $this->field("ZASOBY.$code.$key", "$label — $suffix", [], $key !== 'luka');
                }
            }
        }
        if ($section === '7-2') {
            $groups['mac'] = ['title' => 'Matryca W/A — wymagane i aktualne kompetencje', 'fields' => []];
            foreach ($answers['KOMPETENCJE'] ?? [] as $c => $competency) {
                foreach ($answers['ROLE'] ?? [] as $r => $role) {
                    foreach (['W' => 'Wymagany poziom', 'A' => 'Aktualny poziom'] as $key => $label) {
                        $groups['mac']['fields'][] = $this->field("MAC.$c.$r.$key", ($competency['nazwa'] ?? 'Kompetencja').' / '.($role['nazwa'] ?? 'Rola').' — '.$label, [0 => '0 — nie dotyczy', 1 => '1 — wie, gdzie szukać', 2 => '2 — pod nadzorem', 3 => '3 — samodzielnie', 4 => '4 — uczy innych'], true, 'text');
                    }
                }
            }
        }

        return $groups;
    }

    public function rowFields(string $section, string $root, string $i, array $answers, array $sources): array
    {
        $fields = [];
        foreach ($this->rows($section)[$root][1] as $key => $label) {
            $options = match ($key) {
                'wykonawca' => ['nie' => 'Nie', 'tak' => 'Tak'],
                'punkty' => ['7.2' => '7.2 Kompetencje', '7.3' => '7.3 Świadomość'],
                'elementy' => ['polityka' => 'Polityka', 'wklad' => 'Wkład', 'wplyw' => 'Wpływ', 'konsekwencje' => 'Konsekwencje'],
                'status' => ['planowane' => 'Planowane', 'wtoku' => 'W toku', 'zaliczone' => 'Zaliczone', 'niezaliczone' => 'Niezaliczone', 'odwolane' => 'Odwołane'],
                'stan' => ['zgloszona' => 'Zgłoszona', 'wtoku' => 'W toku', 'usunieta' => 'Usunięta', 'odrzucona' => 'Odrzucona'],
                'forma' => ['spotkanie' => 'Spotkanie', 'instruktaz' => 'Instruktaż', 'mentoring' => 'Mentoring', 'warsztat' => 'Warsztat', 'szkolenie_zewn' => 'Szkolenie zewnętrzne', 'szkolenie_wewn' => 'Szkolenie wewnętrzne', 'zmiana' => 'Zmiana organizacji pracy', 'zatrudnienie' => 'Zatrudnienie', 'zlecenie' => 'Zlecenie', 'umowa' => 'Umowa'],
                'rodzaj' => $root === 'ZADANIA' ? ['stale' => 'Stałe', 'plan' => 'Plan działań', 'wdrozenie' => 'Wdrożenie'] : ['czas' => 'Czas', 'budzet' => 'Budżet', 'sprzet' => 'Sprzęt', 'zewn' => 'Zewnętrzne'],
                default => [],
            };
            if ($root === 'ZADANIA' && $key === 'osoba') {
                foreach ($answers['OSOBY'] ?? [] as $id => $person) {
                    $options[(string) $id] = $person['nazwa'] ?? 'Osoba '.$id;
                }
            }
            if ($root === 'LUKI' && $key === 'dotyczy') {
                foreach ($answers['KOMPETENCJE'] ?? [] as $c => $competency) {
                    foreach ($answers['ROLE'] ?? [] as $r => $role) {
                        $options[$c.'|'.$r] = ($competency['nazwa'] ?? '').' / '.($role['nazwa'] ?? '');
                    }
                }
            }
            $optional = in_array($key, ['zdjete', 'luki', 'dzialanie', 'wynik', 'efekt', 'zrodlo']) && ! in_array($root, ['PLAN', 'LUKI']);
            if ($root === 'LUKI' && $key === 'wynik') {
                $optional = true;
            }
            if ($root === 'SPRAWDZENIA' && $key === 'wynik') {
                $optional = false;
            }
            $type = in_array($key, ['punkty', 'elementy']) ? 'multi' : (in_array($key, ['data', 'termin']) ? 'date' : ($options ? 'text' : 'textarea'));
            if (($root === 'ZADANIA' && $key === 'osoba') || ($root === 'LUKI' && $key === 'dotyczy')) {
                $type = 'select';
            }
            $field = $this->field("$root.$i.$key", $label, $options, ! $optional, $type);
            if (in_array($key, ['godziny', 'dostepne'])) {
                $field['example'] = '8';
            }
            $fields[] = $field;
        }

        return $fields;
    }

    public function seed(string $section, array $sources): array
    {
        $answers = [];
        foreach ($this->rows($section) as $root => $_) {
            $answers[$root] = in_array($root, self::MINIMUM[$section]) ? [0 => []] : [];
        }
        if (in_array($section, ['7-1', '7-2'])) {
            $roles = data_get($sources, 'upstream.5-3.ROLE', []);
            foreach ($roles as $i => $role) {
                $root = $section === '7-1' ? 'OSOBY' : 'ROLE';
                $answers[$root][$i] = ['nazwa' => $role['nazwa'] ?? '', 'rola' => $role['nazwa'] ?? '', 'zrodlo' => $role['id'] ?? '', 'wykonawca' => 'nie'];
            }
        }

        return $answers;
    }

    public function validateRows(string $section, array $input): void
    {
        $rules = [];
        foreach ($this->rows($section) as $root => $_) {
            $rules[$root] = 'sometimes|array|max:20';
            $rules[$root.'.*'] = 'array';
            foreach (array_keys(is_array($input[$root] ?? null) ? $input[$root] : []) as $id) {
                if (! ctype_digit((string) $id) || (int) $id > 100000) {
                    throw ValidationException::withMessages([$root => 'Nieprawidłowy identyfikator pozycji.']);
                }
            }
        }
        Validator::make($input, $rules)->validate();
    }

    public function validateContent(string $section, array &$answers, bool $complete): void
    {
        $errors = [];
        foreach ($answers['SPRAWDZENIA'] ?? [] as $i => $row) {
            if (! collect($row)->contains(fn ($value) => filled($value))) {
                unset($answers['SPRAWDZENIA'][$i]);

                continue;
            }
            foreach (['data', 'kto', 'osoby', 'wynik'] as $key) {
                if (! filled($row[$key] ?? null)) {
                    $errors["SPRAWDZENIA.$i.$key"] = 'Uzupełnij zapis sprawdzenia przed utrwaleniem historii.';
                }
            }
        }
        if ($complete) {
            foreach (self::MINIMUM[$section] as $root) {
                if (empty($answers[$root])) {
                    $errors[$root] = 'Dodaj przynajmniej jedną pozycję.';
                }
            }
        }
        foreach (['OSOBY' => 'dostepne', 'ZADANIA' => 'godziny'] as $root => $key) {
            foreach ($answers[$root] ?? [] as $i => $row) {
                $v = $row[$key] ?? null;
                if (filled($v)) {
                    $v = str_replace(',', '.', trim($v));
                    if (! is_numeric($v) || ! is_finite((float) $v) || (float) $v < 0 || (float) $v > 1000000) {
                        $errors["$root.$i.$key"] = 'Podaj nieujemną liczbę godzin (maks. 1 000 000).';
                    } else {
                        $answers[$root][$i][$key] = $v;
                    }
                }
            }
        }
        foreach ($answers['ZADANIA'] ?? [] as $i => $row) {
            if (filled($row['osoba'] ?? null) && ! array_key_exists($row['osoba'], $answers['OSOBY'] ?? [])) {
                $errors["ZADANIA.$i.osoba"] = 'Wybierz osobę z listy dostępnego czasu.';
            }
        }
        foreach ($answers['LUKI'] ?? [] as $i => $row) {
            [$c, $r] = array_pad(explode('|', $row['dotyczy'] ?? ''), 2, '');
            if (filled($row['dotyczy'] ?? null) && (! isset($answers['KOMPETENCJE'][$c]) || ! isset($answers['ROLE'][$r]))) {
                $errors["LUKI.$i.dotyczy"] = 'Powiązana kompetencja lub rola nie istnieje. Zachowaj ją dla historii działania.';
            }
            if ($complete && ($row['status'] ?? '') === 'zaliczone' && ! filled($row['wynik'] ?? null)) {
                $errors["LUKI.$i.wynik"] = 'Zaliczenie wymaga wyniku oceny skuteczności.';
            }
        }
        if ($complete && $section === '7-2') {
            foreach ($answers['MAC'] ?? [] as $c => $roles) {
                foreach ($roles as $r => $cell) {
                    if ((int) $cell['W'] - (int) $cell['A'] >= 2 && ! collect($answers['LUKI'] ?? [])->contains(fn ($row) => $row['dotyczy'] === $c.'|'.$r && filled($row['dzialanie'] ?? null))) {
                        $errors["MAC.$c.$r.A"] = 'Luka co najmniej 2 poziomów wymaga działania w części Luki (kryterium metodyki ENESA).';
                    }
                }
            }
        }
        if ($complete) {
            foreach ($answers['SPRAWDZENIA'] ?? [] as $i => $row) {
                if (filled($row['luki'] ?? null) && ! filled($row['dzialanie'] ?? null)) {
                    $errors["SPRAWDZENIA.$i.dzialanie"] = 'Wskaż reakcję na stwierdzone luki.';
                }
            }
        }
        $numbers = [];
        foreach ($answers['PLAN'] ?? [] as $i => $row) {
            $nr = trim($row['nr'] ?? '');
            if ($nr !== '' && in_array($nr, $numbers, true)) {
                $errors["PLAN.$i.nr"] = 'Numer działania musi być unikalny we wspólnym planie.';
            }
            $numbers[] = $nr;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function protectHistory(string $section, array $before, array $after): void
    {
        if ($section === '7-3') {
            foreach ($before['SPRAWDZENIA'] ?? [] as $i => $row) {
                if ($row !== ($after['SPRAWDZENIA'][$i] ?? null)) {
                    throw ValidationException::withMessages(['answers.SPRAWDZENIA' => 'Zapisane sprawdzenia są historią. Dodaj nowy zapis korygujący zamiast zmieniać lub usuwać poprzedni.']);
                }
            }
        }
    }

    public function balance(array $answers): array
    {
        $rows = [];
        foreach ($answers['OSOBY'] ?? [] as $i => $person) {
            $required = 0;
            foreach ($answers['ZADANIA'] ?? [] as $task) {
                if ((string) ($task['osoba'] ?? '') === (string) $i) {
                    $required += (float) str_replace(',', '.', $task['godziny'] ?? '0');
                }
            }
            $available = filled($person['dostepne'] ?? null) ? (float) str_replace(',', '.', $person['dostepne']) : null;
            $rows[] = ['name' => $person['nazwa'] ?? '', 'required' => $required, 'available' => $available, 'difference' => $available === null ? null : $available - $required];
        }

        return $rows;
    }

    public function plan(array $sources): array
    {
        return array_filter(data_get($sources, 'upstream.7-2.PLAN', []), fn ($row) => in_array('7.3', $row['punkty'] ?? [], true));
    }

    public function warnings(string $section, array $answers, array $sources): array
    {
        $out = [];
        if ($section === '7-1') {
            if (! collect($answers['ZADANIA'] ?? [])->contains('rodzaj', 'stale')) {
                $out[] = 'Nie wskazano zadań stałych. Zapewnij zasoby także na utrzymanie systemu po wdrożeniu.';
            }
            foreach ($this->balance($answers) as $row) {
                if ($row['difference'] !== null && $row['difference'] < 0) {
                    $out[] = $row['name'].': brakuje '.abs($row['difference']).' h. Łączny bilans nie zastępuje oceny obciążenia każdej osoby.';
                } elseif ($row['available'] > 0 && $row['difference'] < .15 * $row['available']) {
                    $out[] = $row['name'].': czas na styk — rezerwa poniżej 15% (próg metodyki).';
                }
            }
        }
        if ($section === '7-2') {
            $out[] = 'Matryca i plan są narzędziami metodyki. Lista obecności nie zastępuje dowodu umiejętności. Dane indywidualnych ocen przechowuj w chronionej dokumentacji kadrowej; tutaj wpisuj funkcje i odwołania.';
        }
        if (in_array($section, ['7-2', '7-3']) && ! collect($answers[$section === '7-2' ? 'ROLE' : 'GRUPY'] ?? [])->contains('wykonawca', 'tak')) {
            $out[] = 'Sprawdź, czy trzeba uwzględnić wykonawców zewnętrznych pracujących pod nadzorem organizacji.';
        }
        if ($section === '7-3') {
            $last = collect($answers['SPRAWDZENIA'] ?? [])->pluck('data')->filter()->sortDesc()->first();
            if (! $last || $last < now()->subMonths(6)->toDateString()) {
                $out[] = 'Brak sprawdzenia świadomości w ostatnich 6 miesiącach (próg metodyki).';
            }
            if (! $this->plan($sources)) {
                $out[] = 'Brak działań oznaczonych 7.3 we wspólnym planie punktu 7.2.';
            }
            foreach ($answers['PRZESZKODY'] ?? [] as $row) {
                if (! filled($row['decyzja'] ?? null) || (($row['stan'] ?? '') === 'odrzucona' && ! filled($row['efekt'] ?? null))) {
                    $out[] = 'Przeszkoda wymaga decyzji lub uzasadnienia odrzucenia: '.($row['opis'] ?? '');
                }
            }
        }

        return $out;
    }
}
