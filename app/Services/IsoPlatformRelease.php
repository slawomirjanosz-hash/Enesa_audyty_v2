<?php

namespace App\Services;

use App\Models\IsoPlantProfile;
use Illuminate\Support\Str;

/** Reviewed data extracted from the supplied release; no source scripts are executed. */
class IsoPlatformRelease
{
    public function source(string $section): array
    {
        abort_unless(in_array($section, array_column(json_decode(file_get_contents(resource_path('iso50001/platform-2026-10-18/manifest.json')), true, 512, JSON_THROW_ON_ERROR), 'section'), true), 404);

        return json_decode(file_get_contents(resource_path('iso50001/platform-2026-10-18/'.$section.'.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function consultant(string $section): array
    {
        $source = $this->source($section);

        return ['WNIOSKI' => $source['variables']['WNIOSKI_PRZYKLAD'] ?? $source['data']['przyklad_41']['wnioski'] ?? []];
    }

    public function swot(): array
    {
        $example = $this->source('4-1')['data']['przyklad_41'];
        $out = ['wnioski' => $example['wnioski']];
        foreach (['S' => 'strengths', 'W' => 'weaknesses', 'O' => 'opportunities', 'T' => 'threats'] as $key => $field) {
            $out[$field] = $example['swot'][$key];
        }

        return $out;
    }

    public function profile(): IsoPlantProfile
    {
        return new IsoPlantProfile(['definition' => app(IsoPlantQuestionnaire::class)->definition(), 'answers' => $this->answers('intro'), 'status' => 'editing', 'lock_version' => 0, 'as_of_date' => '2026-02-10']);
    }

    public function demoSources(): array
    {
        $cards = collect($this->answers('6-4')['KARTY'])->keyBy('nr');
        foreach ($this->answers('6-5')['KARTY'] as $card) {
            $cards[$card['nr']] = array_replace($cards[$card['nr']], $card);
        }
        $parties = [];
        $mapping = json_decode(file_get_contents(resource_path('iso50001/platform-2026-10-18/requirement-ids.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($this->source('9-1-2')['data']['przyklad']['WYM'] as $row) {
            if ($row['kwalifikacja'] === 'nieprzyjete') {
                continue;
            }
            $code = $mapping[$row['wym42']] ?? $row['wym42'];
            $parties[] = ['kod' => $code, 'nazwa' => $row['strona'], 'wymagania' => $row['wymaganie'], 'zgodnosc' => 'tak', 'kwalifikacja' => $row['kwalifikacja'], 'consultant' => ['legal_basis' => $row['zrodlo']]];
        }

        return ['ready' => false, 'facts' => app(IsoPlantQuestionnaire::class)->facts($this->profile()->definition, $this->profile()->answers), 'approved' => [], 'published' => [], 'factors' => [], 'scope' => [], 'cards' => $cards->values()->all(), 'parties' => $parties, 'plan' => $this->answers('6-6')['POZYCJE'], 'upstream' => ['9-1-1' => $this->answers('9-1-1'), '6-3' => $this->answers('6-3')]];
    }

    public function answers(string $section, array $sources = []): array
    {
        $source = $this->source($section);
        $data = $source['data'];
        $vars = $source['variables'];
        $literal = $source['example_literals'];
        if ($section === 'intro') {
            $facts = array_replace($vars['SCHEMA']['przyklad'], $this->source('4-1')['data']['przyklad']);
            $out = [];
            foreach ($facts as $key => $value) {
                if (is_array($value) && array_is_list($value)) {
                    foreach ($value as &$row) {
                        if (is_array($row)) {
                            $row['id'] = (string) Str::uuid();
                        }
                    }
                    unset($row);
                }
                $out[$key] = ['value' => $value, 'unknown' => false, 'detail' => '', 'source' => 'PRZYKŁAD AutoParts — wydanie 18.10.2026'];
            }
            foreach (['organization.name' => 'AutoParts Polska Sp. z o.o.', 'site.name' => 'Zakład produkcyjny w Katowicach', 'site.address' => 'Katowice', 'profile.contact_person' => 'Energy Manager'] as $key => $value) {
                $out[$key] = ['value' => $value, 'unknown' => false, 'detail' => '', 'source' => 'PRZYKŁAD'];
            }
            foreach (app(IsoPlantQuestionnaire::class)->questions(app(IsoPlantQuestionnaire::class)->definition()) as $question) {
                $key = $question['key'];
                if (! array_key_exists($key, $out)) {
                    $out[$key] = ['value' => null, 'unknown' => true, 'detail' => 'Brak danych w przekazanym przykładzie.', 'source' => 'PRZYKŁAD AutoParts'];
                } elseif ($question['type'] === 'number' && is_string($out[$key]['value'])) {
                    $out[$key]['value'] = str_replace(',', '.', $out[$key]['value']);
                }
            }
            // The monthly analysis is documented in the supplied monitoring example (9.1.1).
            if (in_array($out['FAKT_ANALIZA_DANYCH']['value'] ?? '', ['tak', 'arkusz'], true)) {
                $out['FAKT_ANALIZA_DANYCH']['value'] = 'systematycznie';
            }

            return app(IsoPlantCalculations::class)->apply(app(IsoPlantQuestionnaire::class)->definition(), $out);
        }
        if ($section === '4-1') {
            $example = $data['przyklad_41'];
            $out = ['FAKT_KLIMAT_ISTOTNY' => $example['klimat']['odp'], 'climate_reason' => $example['klimat']['uzas'], 'PRAWO' => $example['prawo'], 'factors' => [], 'custom' => []];
            $facts = $sources['facts'] ?? app(IsoFactorQuestionnaire::class)->facts($this->profile());
            foreach (app(IsoFactorQuestionnaire::class)->factors($facts, $out) as $factor) {
                $out['factors'][$factor['kod']] = array_replace(['decyzja' => 'potwierdzony', 'istotnosc' => $example['istotnoscDomyslna'], 'tresc' => $factor['tresc']], $example['dec'][$factor['kod']] ?? []);
            }
            $evidence = json_decode(file_get_contents(resource_path('iso50001/contracts-2026-10-22/4-1.json')), true, 512, JSON_THROW_ON_ERROR)['example_evidence'];
            foreach ($evidence as $code => $basis) {
                $out['factors'][$code]['podstawa'] = $basis;
            }

            return $out;
        }
        if ($section === '4-2') {
            $example = $vars['PRZYKLAD_42'];
            $out = ['FAKT_KLIMAT_STRONY' => $example['klimat']['odp'], 'climate_reason' => $example['klimat']['lista'], 'review_interval' => 'rok', 'parties' => [], 'custom' => []];
            foreach ($data['wymagania'] as $requirement) {
                $row = $example['wym'][$requirement['id']] ?? [];
                $out['parties'][$requirement['id']] = array_replace([
                    'kwalifikacja' => $requirement['kwal_sug'] ?? 'przyjete', 'zr_dok' => $requirement['zrodlo_podp'] ?? '',
                    'zr_ozn' => 'Przykład — do sprawdzenia', 'zr_data' => '2026-02-10', 'data_brzmienia' => '2026-02-10',
                    'wlasciciel' => $requirement['wlasciciel_sug'] ?? 'Energy Manager', 'sprawdzanie' => $requirement['sprawdzanie_sug'] ?? '',
                ], $row, ['decyzja' => ($row['decyzja'] ?? 'dotyczy') === 'dotyczy' ? 'potwierdzona' : 'odrzucona', 'wymagania' => $requirement['wymaganie'], 'jak' => $row['sposob'] ?? $requirement['sposob_sug'] ?? '']);
            }
            $mapping = json_decode(file_get_contents(resource_path('iso50001/platform-2026-10-18/requirement-ids.json')), true, 512, JSON_THROW_ON_ERROR);
            $out['parties'] = collect($out['parties'])->mapWithKeys(fn ($row, $key) => [$mapping[$key] => $row])->all();

            return $out;
        }
        if ($section === '4-3') {
            $example = $vars['PRZYKLAD_43'];
            $out = [];
            foreach (['zakres', 'granice', 'upraw', 'energia', 'poza', 'sprawdzone'] as $key) {
                $out[strtoupper($key)] = $example[$key];
            }
            foreach (app(IsoSystemQuestionnaire::class)->consequences($sources) as $key => $rule) {
                $out['KON'][$key] = ['decyzja' => 'potwierdzona', 'tresc' => $example['kon'][$rule['zrodlo']] ?? $rule['konsekwencja']];
            }
            foreach (app(IsoSystemQuestionnaire::class)->energy($sources) as $key => $item) {
                $out['ENERGIA'][$key] ??= ['status' => 'nie'];
            }

            return $out;
        }
        if ($section === '4-4') {
            $example = $vars['PRZYKLAD_44'];
            // Explicit example action only; never a default for a client's answers.
            $latest = json_decode(file_get_contents(resource_path('iso50001/contracts-2026-10-22/4-4.json')), true, 512, JSON_THROW_ON_ERROR)['example_client'];
            $out = ['WYNIK' => $latest['wynik'], 'WLASNE' => $latest['wlasne']];
            foreach (['odp', 'cz', 'ob', 'pg', 'ts', 'proc'] as $key) {
                $out[strtoupper($key)] = $example[$key];
            }
            $out['POW_PLANOWANE'] = $example['powPlanowane'];
            foreach ($out['CZ'] as &$row) {
                if ($row['stan'] === 'jest') {
                    $row['stan'] = 'tak';
                }
            }
            unset($row);
            foreach ($out['PROC'] as $key => &$row) {
                $process = collect($data['procesy'])->firstWhere('kod', $key);
                if ($row['forma'] === 'nadbudowa') {
                    $row['forma'] = 'inne';
                    $row['integracja'] = 'Rozszerzenie istniejącej dokumentacji ISO 14001 — '.$process['nazwa'];
                } elseif ($row['forma'] === 'opis') {
                    $row['forma'] = 'system';
                }
                $row['uwagi'] = $process['ksiazka'] ?? $process['nazwa'];
                $row['trafia'] = $process['trafia'];
            }
            unset($row);
            foreach ($data['powiazania'] as $i => $row) {
                $out['POW'][$i]['potwierdzone'] = in_array($i, $example['powPlanowane'], true) ? 'planowane' : 'tak';
            }

            return $out;
        }
        if ($section === '5-2') {
            $literal['TRESC'] = array_column($vars['ELEMENTY'], 'przykladPelny', 'kod');
            $literal['ZATW']['sposob'] = 'podpis';
            foreach ($literal['PRZEGLADY'] as &$row) {
                $row['zapis'] = $row['wynik'];
                $row['wynik'] = 'bez_zmian';
            }
            unset($row);
        }
        if ($section === '5-3') {
            $literal['ROLE'] = [...$vars['ROLE_DOM'], ['id' => 'aud', 'skrot' => 'Aud.', 'nazwa' => 'Auditor wewnętrzny']];
            $matrix = array_replace($vars['MAT_PRZYKLAD'], ['MAT-15|aud' => 'O', 'MAT-15|em' => 'W', 'MAT-14|em' => 'O']);
            foreach ($literal['ROLE'] as &$role) {
                foreach ($vars['MAT_ZADANIA'] as $task) {
                    $role['MAT'][$task['kod']] = $matrix[$task['kod'].'|'.$role['id']] ?? '-';
                }
            }
            unset($role);
        }
        if ($section === '6-1') {
            $literal['RYZYKA'] = $vars['PRZYKLAD_R'];
            $literal['SZANSE'] = $vars['PRZYKLAD_S'];
            $literal['META'] = ['opracowal' => 'Energy Manager', 'zatwierdzil' => $vars['PRZYKLAD_R'][0]['zatwierdzil'], 'utworzony' => '2026-03-06'];
            foreach (['RYZYKA' => 'R', 'SZANSE' => 'S'] as $root => $prefix) {
                foreach ($literal[$root] as $i => &$row) {
                    $row['nr'] = $prefix.($i + 1);
                }
                unset($row);
            }
        }
        $out = $data['przyklad'] ?? $literal;
        if ($section === '6-3') {
            $out = [];
            foreach (['MET' => 'metody', 'NOSNIKI' => 'nosniki', 'TREND' => 'trend', 'OBSZARY' => 'obszary', 'SEU' => 'seu', 'MOZL' => 'mozliwosci', 'PROG' => 'prognoza', 'AKTUAL' => 'aktualizacja', 'META' => 'meta'] as $key => $name) {
                $out[$key] = $data['przyklad'][$name];
            }
            $out['SEU'] = [];
            foreach ($data['przyklad']['seu'] as $key => $row) {
                $row['nazwa'] = str_starts_with((string) $key, 'G:') ? substr($key, 2) : $out['OBSZARY'][$key]['nazwa'];
                $row['nr'] = str_starts_with((string) $key, 'G:') ? substr($key, 2) : 'O-'.($key + 1);
                $row['brakZmiennych'] ??= false;
                $out['SEU'][] = $row;
            }
            foreach ($out['OBSZARY'] as $i => &$row) {
                $row['nr'] = 'O-'.($i + 1);
                if ($row['metoda'] === 'Sz') {
                    $row['metoda'] = 'S';
                }
            }
            unset($row);
            foreach ($out['NOSNIKI'] as &$row) {
                $row['zrodlo'] = 'Faktury i odczyty liczników — przykład AutoParts';
            }
            unset($row);
            foreach ($out['MOZL'] as $i => &$row) {
                $row['nr'] = 'M-'.($i + 1);
            }
            unset($row);
        }
        if ($section === '5-1') {
            foreach (['INT', 'STYK'] as $root) {
                foreach ($out[$root] as &$row) {
                    $row['stan'] = $row['stan'] === 'jest' ? 'tak' : ($row['stan'] === 'czesciowo' ? 'zapis' : $row['stan']);
                    if ($root === 'STYK' && $row['stan'] === 'zapis') {
                        $row['stan'] = 'nie';
                    }
                }
                unset($row);
            }
            foreach ($out['ZAS'] as &$row) {
                if ($row['stan'] === 'jest') {
                    $row['stan'] = 'zapewnione';
                }
            }
            unset($row);
        }
        if ($section === '6-2') {
            foreach ($out['ZADANIA'] as &$row) {
                $row['WER'] = $out['WER'][$row['nr']] ?? [];
                $row['enpi'] = explode(' ', $row['enpi'])[0];
            }
            unset($row);
            foreach (['CELE', 'DZIAL'] as $root) {
                foreach ($out[$root] as &$row) {
                    $row['status'] = ['zatwierdzone' => 'plan', 'planowane' => 'plan', 'wykonane' => 'zakonczone'][$row['status']] ?? $row['status'];
                }
                unset($row);
            }
        }
        if ($section === '6-4') {
            foreach ($out['KARTY'] as &$card) {
                $card['seu'] = ['P-1' => 'O-1', 'L-1' => 'Lakiernia', 'S-1' => 'O-4', 'S-2' => 'O-4', 'K-1' => 'O-5', 'OŚ-1' => 'O-6'][$card['nr']] ?? '';
                preg_match_all('/\d+(?:[–-]\d+)?/u', preg_split('/plan/u', $card['zrodla'])[0], $matches);
                $ids = [];
                foreach ($matches[0] as $range) {
                    $ends = preg_split('/[–-]/u', $range);
                    array_push($ids, ...range((int) $ends[0], (int) ($ends[1] ?? $ends[0])));
                }
                $card['plan'] = implode(', ', $ids);
            }
            unset($card);
        }
        if ($section === '6-6') {
            foreach ($out['POZYCJE'] as $i => &$row) {
                $row['nr'] = (string) ($i + 1);
            }
            unset($row);
        }
        if ($section === '7-1') {
            $people = array_flip(array_column($out['OSOBY'], 'nazwa'));
            foreach ($out['ZADANIA'] as &$row) {
                $row['osoba'] = (string) $people[$row['osoba']];
            }
            unset($row);
        }
        if ($section === '7-5') {
            // The example is opt-in and never supplies production dependencies.
            $example = json_decode(file_get_contents(resource_path('iso50001/examples-2026-10-29/7-5.json')), true, 512, JSON_THROW_ON_ERROR);
            $out = ['WYKAZ' => array_map(fn ($row) => array_replace($row, ['kod' => $row['symbol'], 'punkt' => $row['punkty']]), $example['wykaz']),
                'ZASADY' => $example['zasady'], 'ZEWN' => $example['zewn'], 'META' => $example['meta']];
        }
        if ($section === '7-2') {
            $out['MAC'] = [];
            foreach ($data['przyklad']['MAC'] as $key => $value) {
                [$competency, $role] = explode('|', $key);
                [$required, $actual] = array_pad(explode('/', $value), 2, '0');
                $out['MAC'][$competency][$role] = ['W' => $required, 'A' => $actual];
            }
            foreach ($out['LUKI'] as &$row) {
                $description = $row['dotyczy'];
                foreach ($out['KOMPETENCJE'] as $i => $competency) {
                    if (str_contains($description, $competency['nazwa'])) {
                        foreach ($out['ROLE'] as $r => $role) {
                            if (str_starts_with($description, $role['nazwa'].' — ')) {
                                $row['dotyczy'] = $i.'|'.$r;
                                break;
                            }
                        }
                        break;
                    }
                }
            }
            unset($row);
        }
        if (in_array($section, ['8-2', '8-3', '10-1'])) {
            $root = ['8-2' => 'PROJEKTY', '8-3' => 'ZAKUPY', '10-1' => 'KARTY'][$section];
            $out['META']['brakZdarzen'] = empty($out[$root]) ? 'tak' : 'nie';
        }
        if ($section === '8-3') {
            foreach ($out['ZAKUPY'] as &$row) {
                $row['cenaEnergii'] = $row['cenaEnergii'] ?: $out['META']['cenaEnergii'];
                if (empty($row['rekomendacja']) && $row['porownanieNd']) {
                    $row['rekomendacja'] = $row['porownanieNdPowod'];
                }
            }
            unset($row);
        }
        if ($section === '9-3') {
            foreach ($out['DECYZJE'] as &$row) {
                if ($row['wlasna'] ?? false) {
                    $row['wlasna'] = $row['nazwa'];
                } else {
                    $row['nazwa'] = collect($data['OBSZARY_DEF'])->firstWhere('kod', $row['kod'])['nazwa'];
                }
            }
            unset($row);
        }
        if (in_array($section, ['9-1-1', '10-2'])) {
            $root = $section === '9-1-1' ? 'WSKAZNIKI' : 'POPRAWA';
            $out[$root] = collect($out[$root])->mapWithKeys(fn ($row) => ['card_'.substr(hash('sha256', $row['kod']), 0, 20) => $row])->all();
            if ($section === '9-1-1') {
                $key = 'card_'.substr(hash('sha256', 'S-2'), 0, 20);
                $task = $this->source('6-2')['data']['przyklad']['ZADANIA'][1];
                $out[$root][$key] = ['kod' => 'S-2', 'nazwa' => 'Przepływ sprężonego powietrza w postoju', 'kierunek' => 'nizej', 'biez' => '', 'biezOkres' => 'Brak pomiaru w przykładzie monitorowania', 'cel' => $task['cel'], 'celTermin' => $task['termin'], 'prog' => $task['cel'], 'ocena' => 'Brak pomiaru w przykładzie źródłowym — uzupełnij dane przed oceną poprawy.'];
            }
        }
        if ($section === '9-1-2') {
            $mapping = json_decode(file_get_contents(resource_path('iso50001/platform-2026-10-18/requirement-ids.json')), true, 512, JSON_THROW_ON_ERROR);
            $requirementExamples = $this->source('4-2')['variables']['PRZYKLAD_42']['wym'];
            $out['WYM'] = collect($out['WYM'])->filter(fn ($row) => $row['kwalifikacja'] !== 'nieprzyjete')->mapWithKeys(function ($row) use ($mapping, $requirementExamples) {
                $sourceCode = $row['wym42'];
                $code = $mapping[$sourceCode] ?? $sourceCode;
                $row['nr'] = $row['wym42'] = $code;
                $row['odp'] = $row['odp'] ?: ($requirementExamples[$sourceCode]['wlasciciel'] ?? 'Energy Manager');

                return [$code => $row];
            })->all();
        }
        unset($out['WNIOSKI']);

        // HTML numeric literals become form strings; the regular save validator remains authoritative.
        array_walk_recursive($out, function (&$value) {
            if (is_int($value) || is_float($value)) {
                $value = (string) $value;
            } elseif (is_bool($value)) {
                $value = $value ? 'tak' : 'nie';
            }
        });

        return $out;
    }
}
