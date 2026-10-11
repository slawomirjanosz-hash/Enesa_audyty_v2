<?php

namespace App\Services;

use App\Models\IsoPlantProfile;
use App\Models\IsoSystemReview;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Stable source identifiers from the chapter-five forms, never demo company data. */
class IsoLeadershipQuestionnaire
{
    public const REPEATERS = ['CZLON', 'DECYZJE', 'DODATKOWE', 'PRZEGLADY', 'ROLE', 'UPR', 'SEU'];

    public function sources(IsoPlantProfile $profile, string $section): array
    {
        $service = app(IsoSystemQuestionnaire::class);
        $base = $service->sources($profile, '4-4');
        $dependencies = $section === '5-3' ? ['4-4', '5-1'] : ['4-4'];
        $snapshot = $base;
        unset($snapshot['hash'], $snapshot['ready']);
        $snapshot['upstream'] = [];
        $snapshot['organization_name'] = $profile->answers['organization.name']['value'] ?? '';
        foreach ($dependencies as $key) {
            $row = IsoSystemReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->where('section', $key)->first();
            $source = $key === '4-4' ? $base : $this->sources($profile, $key);
            $snapshot['approved'][$key] = $source['ready'] && $row?->status === 'approved' && $row->client_approval && $row->auditor_approval && $row->source_hash === $source['hash'];
            $snapshot['published'][$key] = (bool) ($snapshot['approved'][$key] && $row?->document_id);
            // Only client answers feed later sections; private consultant notes never do.
            $snapshot['upstream'][$key] = $row?->answers ?? [];
            $snapshot['versions'][$key] = $row?->only(['id', 'lock_version', 'status', 'source_hash']);
        }

        return $snapshot + ['ready' => $base['ready'] && ! in_array(false, $snapshot['approved'], true), 'hash' => hash('sha256', json_encode([$snapshot, $service->schema($section)], JSON_THROW_ON_ERROR))];
    }

    public function seed(string $section, array $sources): array
    {
        return match ($section) {
            '5-1' => ['CZLON' => [[]], 'DECYZJE' => [[], [], []]],
            '5-2' => ['POL' => ['nazwa' => $sources['organization_name'] ?? ''], 'PRZEGLADY' => [[]]],
            '5-3' => ['ROLE' => array_map(fn ($r) => ['id' => $r['id'], 'skrot' => $r['skrot'], 'nazwa' => $r['nazwa']], app(IsoSystemQuestionnaire::class)->schema('5-3')['ROLE_DOM']), 'UPR' => [[]], 'SEU' => []],
        };
    }

    private function field(string $key, string $label, array $options = [], bool $required = true, ?array $when = null, string $type = 'textarea', string $help = ''): array
    {
        return compact('key', 'label', 'options', 'required', 'when', 'type', 'help');
    }

    public function sections(string $section, array $answers, array $sources): array
    {
        $schema = app(IsoSystemQuestionnaire::class)->schema($section);
        $f = fn ($key, $label, $options = [], $required = true, $when = null, $type = 'textarea', $help = '') => $this->field($key, $label, $options, $required, $when, $type, $help);
        $groups = [];
        $states = ['jest' => 'Jest — potrafimy to pokazać', 'czesciowo' => 'Częściowo', 'nie' => 'Jeszcze nie'];
        $hint = function (array $row) use ($sources): string {
            $text = $row['pomoc'] ?? $row['opis'] ?? $row['placeholder'] ?? '';
            if (isset($row['profil'])) {
                $value = $sources['facts'][$row['profil']] ?? null;
                $text .= ' Profil zakładu · '.$row['profil'].': '.(is_scalar($value) ? (string) $value : 'brak danych').'. '.($row['profilUwaga'] ?? '');
            }

            return $text;
        };
        if ($section === '5-1') {
            foreach ($schema['PYTANIA_4'] as $q) {
                $groups[$q['id']] = ['title' => $q['nazwa'], 'fields' => []];
            }
            foreach ($schema['ZOBOWIAZANIA'] as $q) {
                $b = 'ZOB.'.$q['kod'];
                $fields = &$groups[$q['pytanie']]['fields'];
                $fields[] = $f("$b.stan", $q['kod'].' · '.$q['tresc'], $states, true, null, 'text', $hint($q));
                $fields[] = $f("$b.dowod", 'Dowód — '.$q['kod'], [], true, ["$b.stan", ['jest', 'czesciowo']], 'textarea', $q['dowod']);
                $fields[] = $f("$b.kto", 'Osoba z kierownictwa — '.$q['kod'], [], true, ["$b.stan", ['jest', 'czesciowo']]);
                $fields[] = $f("$b.plan", 'Co i kiedy powstanie — '.$q['kod'], [], true, ["$b.stan", ['nie']]);
                unset($fields);
            }
            foreach ($schema['PROCESY_BIZ'] as $q) {
                $b = 'INT.'.$q['kod'];
                $groups['integracja']['fields'][] = $f("$b.stan", $q['nazwa'].' — '.$q['pytanie'], ['tak' => 'Tak — widać to w decyzjach', 'zapis' => 'Jest zapis, ale decyzje wyglądają tak samo', 'nie' => 'Nie'], true, null, 'text', $hint($q));
                $groups['integracja']['fields'][] = $f("$b.jak", $q['nazwa'].' — jak to działa', [], true, ["$b.stan", ['tak', 'zapis']]);
                $groups['integracja']['fields'][] = $f("$b.gdzie", $q['nazwa'].' — dokument / zapis', [], false);
            }
            $groups['integracja']['title'] = '5. Integracja z procesami firmy';
            $groups['zespol'] = ['title' => '6. Powołanie zespołu', 'fields' => [$f('ZESP.dokument', 'Dokument powołujący'), $f('ZESP.data', 'Data powołania', [], true, null, 'date'), $f('ZESP.organ', 'Kto powołał zespół', [], false)]];
            $groups['czlon'] = $this->repeater('CZLON', '7. Skład zespołu', $answers);
            foreach ($schema['ZASOBY'] as $q) {
                $b = 'ZAS.'.$q['kod'];
                $groups['zasoby']['fields'][] = $f("$b.stan", $q['nazwa'].' — dostępność', ['zapewnione' => 'Zapewnione', 'czesciowo' => 'Częściowo', 'brak' => 'Brak'], true, null, 'text', $hint($q));
                $groups['zasoby']['fields'][] = $f("$b.opis", $q['nazwa'].' — przydział / brak i plan', [], true, null, 'textarea', $q['placeholder']);
            }
            $groups['zasoby']['title'] = '8. Zasoby';
            foreach ($schema['NAWYKI'] as $q) {
                $b = 'STYK.'.$q['kod'];
                $groups['styk']['fields'][] = $f("$b.stan", $q['nazwa'], ['tak' => 'Tak', 'czesciowo' => 'Częściowo', 'nie' => 'Nie'], true, null, 'text', $hint($q));
                $groups['styk']['fields'][] = $f("$b.jak", 'Jak / kiedy — '.$q['kod'], [], true, ["$b.stan", ['tak', 'czesciowo']]);
            }
            $groups['styk']['title'] = '9. Punkty styku z kierownictwem';
            $groups['decyzje'] = $this->repeater('DECYZJE', '10. Dowody z decyzji', $answers);
        } elseif ($section === '5-2') {
            $groups['organizacja'] = ['title' => '1. Organizacja i wstęp polityki', 'fields' => [$f('POL.nazwa', 'Nazwa organizacji'), $f('POL.czym', 'Czym się zajmujecie i gdzie działacie'), $f('POL.rola', 'Rola energii w organizacji', [], false), $f('POL.wstep', 'Zdanie wstępne — zobowiązanie kierownictwa', [], false)]];
            foreach ($schema['ELEMENTY'] as $q) {
                $groups['elementy']['fields'][] = $f('TRESC.'.$q['kod'], $q['litera'].') '.$q['nazwa'], [], true, null, 'textarea', $q['musi'].' '.($q['mozna'] ?? '')) + ['example' => $q['przykladPelny']];
            }
            $groups['elementy']['title'] = '2. Treść polityki — elementy a–g';
            $groups['dodatkowe'] = $this->repeater('DODATKOWE', '3. Zobowiązania dodatkowe', $answers);
            foreach ($schema['UTRZYMANIE'] as $q) {
                $groups['utrzymanie']['fields'][] = $f('UTRZ.'.$q['kod'], $q['etykieta'], [], true, null, 'textarea', $q['pomoc']);
            }
            $groups['utrzymanie']['title'] = '4. Utrzymywanie polityki';
            $groups['zatwierdzenie'] = ['title' => '5. Zatwierdzenie przez kierownictwo', 'fields' => [$f('ZATW.wersja', 'Wersja'), $f('ZATW.od', 'Obowiązuje od', [], true, null, 'date'), $f('ZATW.kto', 'Kto zatwierdził — osoba / funkcja'), $f('ZATW.sposob', 'Sposób zatwierdzenia', ['podpis' => 'Podpis i data', 'uchwala' => 'Uchwała', 'elektronicznie' => 'Obieg elektroniczny']), $f('ZATW.uchwala', 'Numer i data uchwały', [], true, ['ZATW.sposob', ['uchwala']])]];
            $groups['przeglady'] = $this->repeater('PRZEGLADY', '6. Historia przeglądów polityki', $answers);
        } else {
            foreach ($schema['ZADANIA'] as $q) {
                $b = 'ZAD.'.$q['kod'];
                $groups['zadania']['fields'][] = $f("$b.kto", $q['kod'].' — '.$q['tresc'].' — wykonawca', [], true, null, 'textarea', $q['poludzku']);
                $groups['zadania']['fields'][] = $f("$b.jak", $q['kod'].' — sposób realizacji', [], true, null, 'textarea', $q['placeholder']);
                if ($q['kod'] === 'ZAD-D') {
                    $groups['zadania']['fields'][] = $f("$b.rytm", 'Ustalone odstępy raportowania kierownictwu');
                }
            }
            $groups['zadania']['title'] = '1. Pięć zadań zespołu';
            $groups['role'] = $this->repeater('ROLE', '2. Role i matryca Z / O / W / I', $answers);
            foreach ($schema['MAT_ZADANIA'] as $q) {
                $groups['uwagi']['fields'][] = $f('UWAGI.'.$q['kod'], $q['nazwa'].' — podział przy kilku osobach O', [], false);
            }
            $groups['uwagi']['title'] = '3. Wyjaśnienia do matrycy';
            $groups['upr'] = $this->repeater('UPR', '4. Uprawnienia kluczowych ról', $answers);
            $groups['seu'] = $this->repeater('SEU', '5. Właściciele znaczących użyć energii', $answers);
            $groups['komunikacja'] = ['title' => '6. Zakomunikowanie ról', 'fields' => [$f('KOM.sposob', 'Jak ogłoszono role i skład zespołu'), $f('KOM.data', 'Data ogłoszenia', [], true, null, 'date'), $f('KOM.koordynator', 'Koordynator zespołu', [], false), $f('KOM.bezKoordynatora', 'Bez koordynatora — jak rozdzielono jego zadania', [], true, ['KOM.koordynator', ['']])]];
        }

        return $groups;
    }

    private function repeater(string $root, string $title, array $answers): array
    {
        $rows = $answers[$root] ?? [];
        $fields = [];
        foreach (is_array($rows) ? array_slice($rows, 0, 20, true) : [] as $i => $row) {
            $fields = [...$fields, ...$this->rowFields($root, (string) $i)];
        }

        return ['title' => $title, 'fields' => $fields, 'repeater' => $root];
    }

    public function rowFields(string $root, string $i): array
    {
        $labels = match ($root) {
            'CZLON' => ['osoba' => 'Stanowisko / osoba', 'rola' => 'Rola w zespole', 'obszar' => 'Reprezentowany obszar', 'zastepca' => 'Zastępca', 'wie' => 'Czy zna swoje zadania?', 'czas' => 'Czas na zadania systemu'],
            'DECYZJE' => ['data' => 'Data decyzji', 'czego' => 'Czego dotyczyła', 'kto' => 'Kto zdecydował', 'skutek' => 'Wpływ na energię', 'zapis' => 'Dokument / zapis'],
            'DODATKOWE' => ['tresc' => 'Treść zobowiązania', 'liczbowe' => 'Czy liczbowe?', 'wskaznik' => 'Wskaźnik', 'kierunek' => 'Kierunek poprawy', 'porownanie' => 'Warunki porównania'],
            'PRZEGLADY' => ['data' => 'Data przeglądu', 'wynik' => 'Wynik', 'zapis' => 'Zapis / protokół'],
            'ROLE' => ['id' => 'Stały identyfikator roli (np. zarzad, em, R1)', 'skrot' => 'Skrót', 'nazwa' => 'Pełna nazwa roli'],
            'UPR' => ['rola' => 'Rola', 'decyzje' => 'Może zdecydować samodzielnie', 'dane' => 'Dostęp do danych', 'eskalacja' => 'Kiedy i do kogo przekazuje sprawę wyżej', 'sprawdzenie' => 'Jak sprawdzicie, że to działa'],
            'SEU' => ['nazwa' => 'Znaczące użycie energii', 'wlasciciel' => 'Właściciel', 'zakres' => 'Zakres odpowiedzialności', 'zastepca' => 'Zastępca'],
        };
        $fields = [];
        foreach ($labels as $key => $label) {
            $options = match ($root.'.'.$key) {
                'CZLON.rola' => ['kierujacy' => 'Koordynator', 'czlonek' => 'Członek', 'sponsor' => 'Sponsor kierownictwa', 'wsparcie' => 'Wsparcie zewnętrzne'],
                'CZLON.wie', 'DODATKOWE.liczbowe' => ['tak' => 'Tak', 'nie' => 'Nie'],
                'PRZEGLADY.wynik' => ['bez_zmian' => 'Bez zmian', 'zmiana' => 'Zmiana — nowa wersja'],
                default => [],
            };
            $required = ! in_array($key, ['obszar', 'zastepca', 'czas', 'sprawdzenie', 'zakres']) && ! ($root === 'DECYZJE' && $key === 'zapis');
            $when = $root === 'DODATKOWE' && in_array($key, ['wskaznik', 'kierunek', 'porownanie']) ? ["$root.$i.liczbowe", ['tak']] : null;
            $fields[] = $this->field("$root.$i.$key", $label, $options, $required, $when, $key === 'data' ? 'date' : 'text');
        }
        if ($root === 'ROLE') {
            foreach (app(IsoSystemQuestionnaire::class)->schema('5-3')['MAT_ZADANIA'] as $task) {
                $fields[] = $this->field("ROLE.$i.MAT.".$task['kod'], $task['kod'].' · '.$task['nazwa'], ['-' => '— Nie uczestniczy', 'Z' => 'Z — zatwierdza', 'O' => 'O — odpowiada', 'W' => 'W — współpracuje', 'I' => 'I — informowany'], false, null, 'text');
            }
        }

        return $fields;
    }

    public function validateRows(string $section, array $answers, bool $complete): void
    {
        $rules = [];
        foreach (self::REPEATERS as $root) {
            $min = $complete ? match ($section.'.'.$root) {
                '5-1.CZLON', '5-2.PRZEGLADY', '5-3.ROLE', '5-3.UPR' => 1, '5-1.DECYZJE' => 3, default => 0
            } : 0;
            $rules[$root] = [$min ? 'required' : 'sometimes', 'array', 'max:20', 'min:'.$min];
            $rules[$root.'.*'] = 'array';
        }
        Validator::make($answers, $rules)->validate();
        foreach (self::REPEATERS as $root) {
            foreach (array_keys($answers[$root] ?? []) as $i) {
                if (! ctype_digit((string) $i) || (int) $i > 100000) {
                    throw ValidationException::withMessages([$root => 'Nieprawidłowy identyfikator wiersza.']);
                }
            }
        }
    }

    public function validateContent(string $section, array $answers, bool $complete): void
    {
        if (! $complete) {
            return;
        }
        $errors = [];
        if ($section === '5-1') {
            foreach ($answers['CZLON'] ?? [] as $i => $row) {
                if (($row['wie'] ?? '') !== 'tak') {
                    $errors["CZLON.$i.wie"] = 'Przed zatwierdzeniem zakomunikuj tej osobie rolę i zadania.';
                }
            }
        }
        if ($section === '5-2') {
            foreach (app(IsoSystemQuestionnaire::class)->schema($section)['ELEMENTY'] as $element) {
                $text = data_get($answers, 'TRESC.'.$element['kod'], '') ?? '';
                if (mb_strlen(trim($text)) < $element['min']) {
                    $errors['TRESC.'.$element['kod']] = 'Rozwiń treść elementu polityki (minimum '.$element['min'].' znaków).';
                }
                foreach ($element['wymaga'] ?? [] as [$pattern, $message]) {
                    if (! preg_match('~'.$pattern.'~iu', $text)) {
                        $errors['TRESC.'.$element['kod']] = $message;
                    }
                }
                if (($element['zobowiazanie'] ?? false) && ! preg_match('/zobowiązuj|zobowiązani|deklaruj/iu', $text.' '.(data_get($answers, 'POL.wstep') ?? ''))) {
                    $errors['TRESC.'.$element['kod']] = 'Zapisz zobowiązanie kierownictwa w tym elemencie albo we wstępie polityki.';
                }
            }
        }
        if ($section === '5-3') {
            $ids = [];
            foreach ($answers['ROLE'] ?? [] as $i => $role) {
                $id = $role['id'] ?? '';
                if (! preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $id) || in_array($id, $ids, true)) {
                    $errors["ROLE.$i.id"] = 'Podaj unikalny identyfikator roli: litery, cyfry, - lub _.';
                }
                $ids[] = $id;
            }
            foreach (app(IsoSystemQuestionnaire::class)->schema($section)['MAT_ZADANIA'] as $task) {
                $owners = array_filter($answers['ROLE'] ?? [], fn ($role) => ($role['MAT'][$task['kod']] ?? '') === 'O');
                if (! $owners) {
                    $errors['ROLE'] = 'Każdy wiersz matrycy musi mieć osobę odpowiedzialną O. Brak: '.$task['kod'];
                } elseif (count($owners) > 1 && ! filled(data_get($answers, 'UWAGI.'.$task['kod']))) {
                    $errors['UWAGI.'.$task['kod']] = 'Wyjaśnij podział odpowiedzialności między kilkoma rolami O.';
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function checklist(string $section, array $answers, array $sources): array
    {
        $schema = app(IsoSystemQuestionnaire::class)->schema($section);
        $items = $schema['LISTA_'.str_replace('-', '', $section)];
        $all = fn (string $root, callable $test) => ! empty($answers[$root]) && collect($answers[$root])->every($test);
        foreach ($items as &$item) {
            $ok = match ($item['auto']) {
                'zob' => data_get($answers, 'ZOB.'.$item['zob'].'.stan') === 'jest' && filled(data_get($answers, 'ZOB.'.$item['zob'].'.dowod')),
                'integracja' => $all('INT', fn ($r) => ($r['stan'] ?? '') === 'tak'),
                'zasoby' => $section === '5-1' ? $all('ZAS', fn ($r) => ($r['stan'] ?? '') === 'zapewnione') : collect(data_get($sources, 'upstream.5-1.ZAS', []))->filter()->isNotEmpty() && collect(data_get($sources, 'upstream.5-1.ZAS', []))->every(fn ($r) => ($r['stan'] ?? '') === 'zapewnione'),
                'zespol' => filled(data_get($answers, 'ZESP.dokument')) && $all('CZLON', fn ($r) => filled($r['osoba'] ?? '') && ($r['wie'] ?? '') === 'tak'),
                'styk' => $all('STYK', fn ($r) => ($r['stan'] ?? '') === 'tak'),
                'decyzje' => count($answers['DECYZJE'] ?? []) >= 3,
                'elem' => filled(data_get($answers, 'TRESC.'.$item['elem'])),
                'rodzaje_d' => filled(data_get($answers, 'TRESC.d')),
                'liczbowe' => $all('DODATKOWE', fn ($r) => ($r['liczbowe'] ?? '') !== 'tak' || (filled($r['wskaznik'] ?? '') && filled($r['kierunek'] ?? '') && filled($r['porownanie'] ?? ''))),
                'udokumentowana' => filled(data_get($answers, 'UTRZ.gdzie')),
                'zatwierdzenie' => filled(data_get($answers, 'ZATW.kto')) && filled(data_get($answers, 'ZATW.sposob')),
                'komunikacja' => filled(data_get($answers, 'UTRZ.komunikacja')),
                'udostepnianie' => filled(data_get($answers, 'UTRZ.udostepnianie')),
                'przeglad' => filled(data_get($answers, 'UTRZ.kiedy')),
                'wynik_przegladu' => $all('PRZEGLADY', fn ($r) => filled($r['data'] ?? '') && filled($r['wynik'] ?? '')),
                'zad' => filled(data_get($answers, 'ZAD.'.$item['zad'].'.kto')) && filled(data_get($answers, 'ZAD.'.$item['zad'].'.jak')),
                'zakomunikowane' => filled(data_get($answers, 'KOM.sposob')) && filled(data_get($answers, 'KOM.data')),
                'koordynator' => filled(data_get($answers, 'KOM.koordynator')) || filled(data_get($answers, 'KOM.bezKoordynatora')),
                'uprawnienia' => $all('UPR', fn ($r) => filled($r['decyzje'] ?? '') && filled($r['dane'] ?? '') && filled($r['eskalacja'] ?? '')),
                'seu' => $all('SEU', fn ($r) => filled($r['wlasciciel'] ?? '')),
                default => null,
            };
            $item['state'] = $ok === true ? 'Opisano — sprawdź dowody' : ($ok === false ? 'Wymaga uwagi / uzupełnienia' : 'Do oceny konsultanta na podstawie zapisów i dowodów');
        }
        unset($item);

        return $items;
    }
}
