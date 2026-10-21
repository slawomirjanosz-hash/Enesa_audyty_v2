<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Maps the original renderer's state to the existing, stable persistence keys. */
class IsoHtmlState
{
    public function ingest(Request $request, string $section, array $sources, array $previous = []): ?array
    {
        if (! $request->has('html_state')) {
            return null;
        }
        $request->validate(['html_state' => 'required|json|max:1000000']);
        try {
            $state = json_decode($request->string('html_state')->toString(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['html_state' => 'Nieprawidłowy format danych ankiety.']);
        }
        abort_unless(is_array($state), 422);
        $roots = $section === 'intro' ? ['ODP', 'STAN'] : collect(app(IsoHtmlQuestionnaire::class)->manifest())->firstWhere('section', $section)['state'];
        $state = array_intersect_key($state, array_flip($roots));
        foreach ($state as $value) {
            if (! is_array($value) && $value !== null) {
                throw ValidationException::withMessages(['html_state' => 'Nieprawidłowa struktura odpowiedzi ankiety.']);
            }
        }
        if ($request->routeIs('client.*')) {
            unset($state['SWOT'], $state['PODSUM']);
            if ($section !== '4-1') {
                unset($state['WNIOSKI']);
            }
        }
        if ($section === '4-1') {
            $ids = Validator::make(['ids' => $state['ID_SEQ'] ?? []], ['ids' => 'array:WL,WN', 'ids.*' => 'integer|min:0|max:1000000000'])->validate()['ids'];
            foreach (['WL', 'WN'] as $kind) {
                $state['ID_SEQ'][$kind] = max((int) ($previous['_html']['ID_SEQ'][$kind] ?? 0), (int) ($ids[$kind] ?? 0));
            }
            $request->merge(['factor_conclusions' => $state['WNIOSKI'] ?? []]);
        }
        if ($request->input('operation') === 'consultant' && $section !== '4-2') {
            $request->merge(['consultant' => ['WNIOSKI' => $state['WNIOSKI'] ?? []]]);
        } elseif (in_array($request->input('operation'), ['save_swot', 'publish']) && $section === '4-1') {
            $swot = ['wnioski' => $state['WNIOSKI'] ?? [], 'podsum' => $state['PODSUM'] ?? []];
            foreach (['S' => 'strengths', 'W' => 'weaknesses', 'O' => 'opportunities', 'T' => 'threats'] as $key => $field) {
                $swot[$field] = $state['SWOT'][$key] ?? '';
            }
            $request->merge(['swot' => $swot]);
        }
        try {
            $answers = $this->toAnswers($section, $state, $sources, $previous);
        } catch (\TypeError|\ErrorException) {
            throw ValidationException::withMessages(['html_state' => 'Nieprawidłowa struktura odpowiedzi ankiety.']);
        }
        $request->merge(['answers' => $answers, 'complete_form' => '1']);
        unset($state['WNIOSKI'], $state['SWOT'], $state['PROFIL'], $state['PODSUM']);
        $state['_ids'] = $previous['_html']['_ids'] ?? [];
        foreach ($this->numberedRoots($section) as $root => $prefix) {
            $state['_ids'][$root] = $this->maximumNumber($answers[$root] ?? [], $prefix, (int) ($state['_ids'][$root] ?? 0));
        }
        if ($section === '4-1') {
            unset($state['ODP']);
            $state['_schema'] = '4.1/3';
        }

        return $state;
    }

    public function toAnswers(string $section, array $state, array $sources = [], array $previous = []): array
    {
        $out = $this->merge($previous, $state, false);
        unset($out['_html']);
        unset($out['WNIOSKI'], $out['SWOT'], $out['PROFIL'], $out['ODP_PROFILE']);
        $out = $this->aliases($section, $out, false);
        if ($section === 'intro') {
            $out = $previous;
            unset($out['_html']);
            foreach (app(IsoPlantQuestionnaire::class)->questions(app(IsoPlantQuestionnaire::class)->definition()) as $question) {
                $key = $question['key'];
                if (! str_starts_with($key, 'FAKT_')) {
                    continue;
                }
                $value = $state['ODP'][$key] ?? null;
                $unknown = ! in_array($question['type'], ['select', 'multi']) && in_array($value, ['nie wiem', 'unknown'], true);
                if ($question['type'] === 'number' && is_string($value) && ! $unknown) {
                    $value = str_replace(',', '.', str_replace([' ', "\u{00a0}"], '', $value));
                }
                if ($question['type'] === 'rows' && is_array($value)) {
                    foreach ($value as &$row) {
                        $row['id'] ??= (string) Str::uuid();
                    }
                    unset($row);
                }
                $out[$key] = array_replace($previous[$key] ?? [], ['value' => $unknown ? null : $value, 'unknown' => $unknown]);
            }

            return $out;
        } elseif ($section === '4-1') {
            $out = ['FAKT_KLIMAT_ISTOTNY' => $state['KLIMAT']['odp'] ?? '', 'climate_reason' => $state['KLIMAT']['uzas'] ?? '', 'PRAWO' => $state['PRAWO'] ?? [], 'factors' => $state['DEC'] ?? [], 'custom' => $state['WLASNE'] ?? []];
            if (isset($state['WNIOSKI'])) {
                $out = app(IsoFactorConclusions::class)->legacyFields($out, array_map(fn ($row) => $row + ['scope' => 'client'], $state['WNIOSKI']));
            }
        } elseif ($section === '4-2') {
            $mapping = $this->requirementIds();
            $out = ['FAKT_KLIMAT_STRONY' => $state['KLIMAT']['odp'] ?? '', 'climate_reason' => $state['KLIMAT']['lista'] ?? '', 'review_interval' => $state['KLIMAT']['odstep'] ?? '', 'parties' => [], 'custom' => $state['WLASNE'] ?? []];
            foreach ($state['W'] ?? [] as $code => $row) {
                $row['decyzja'] = ['dotyczy' => 'potwierdzona', 'nie_dotyczy' => 'odrzucona'][$row['decyzja'] ?? ''] ?? ($row['decyzja'] ?? '');
                $row['jak'] = $row['sposob'] ?? '';
                $requirement = collect(app(IsoPlatformRelease::class)->source('4-2')['data']['wymagania'])->firstWhere('id', $code);
                $row['wymagania'] = $row['wymaganie'] ?? $requirement['wymaganie'] ?? '';
                $out['parties'][$mapping[$code] ?? $code] = $row;
            }
        } elseif ($section === '4-3') {
            $out['KON'] = [];
            foreach (array_keys(app(IsoSystemQuestionnaire::class)->consequences($sources)) as $index => $key) {
                $out['KON'][$key] = $state['KON'][$index] ?? [];
                $out['KON'][$key]['tresc'] ??= app(IsoSystemQuestionnaire::class)->consequences($sources)[$key]['konsekwencja'];
            }
        } elseif ($section === '5-3') {
            foreach ($out['ROLE'] as &$role) {
                foreach (app(IsoPlatformRelease::class)->source($section)['variables']['MAT_ZADANIA'] as $task) {
                    $role['MAT'][$task['kod']] = $state['MAT'][$task['kod'].'|'.$role['id']] ?? '-';
                }
            }
            unset($role);
        } elseif ($section === '6-2') {
            foreach ($out['ZADANIA'] as &$row) {
                $row['WER'] = $state['WER'][$row['nr'] ?? ''] ?? [];
                $row['enpi'] = explode(' ', $row['enpi'] ?? '')[0];
            }
            unset($row);
        } elseif ($section === '6-3') {
            $out['SEU'] = [];
            foreach ($state['SEU'] ?? [] as $index => $row) {
                $row['nazwa'] = str_starts_with((string) $index, 'G:') ? substr($index, 2) : ($state['OBSZARY'][$index]['nazwa'] ?? '');
                $row['nr'] = collect($previous['SEU'] ?? [])->firstWhere('nazwa', $row['nazwa'])['nr'] ?? ($row['nr'] ?? 'SEU-'.Str::uuid());
                $out['SEU'][] = $row;
            }
            // JavaScript enumerates numeric object keys before grouped G: keys.
            // Preserve existing card order so an unchanged browser save is not a revision.
            $order = array_flip(array_column($previous['SEU'] ?? [], 'nr'));
            usort($out['SEU'], fn ($left, $right) => ($order[$left['nr']] ?? PHP_INT_MAX) <=> ($order[$right['nr']] ?? PHP_INT_MAX));
        } elseif ($section === '6-5') {
            $out['REWIZJE'] = [];
            foreach ($state['KARTY'] ?? [] as $card) {
                foreach ($card['historia'] ?? [] as $revision) {
                    $revision['enpi'] = $card['nr'];
                    $revision['opis'] = $revision['uzasadnienie'] ?? '';
                    $out['REWIZJE'][] = $revision;
                }
            }
        } elseif ($section === '7-1') {
            $people = array_flip(array_column($out['OSOBY'] ?? [], 'nazwa'));
            foreach ($out['ZADANIA'] as &$row) {
                $row['osoba'] = (string) ($people[$row['osoba'] ?? ''] ?? '');
            }
            unset($row);
        } elseif ($section === '7-2') {
            $out['MAC'] = [];
            foreach ($state['MAC'] ?? [] as $key => $value) {
                [$competency, $role] = array_pad(explode('|', $key), 2, '');
                [$required, $actual] = array_pad(explode('/', $value), 2, '0');
                $out['MAC'][$competency][$role] = ['W' => $required, 'A' => $actual];
            }
        }
        if (in_array($section, ['9-1-1', '10-2'])) {
            $root = $section === '9-1-1' ? 'WSKAZNIKI' : 'POPRAWA';
            $out[$root] = collect($out[$root] ?? [])->mapWithKeys(fn ($row) => ['card_'.substr(hash('sha256', $row['kod']), 0, 20) => $row])->all();
        }
        if ($section === '9-1-2') {
            $mapping = $this->requirementIds();
            $out['WYM'] = collect($out['WYM'] ?? [])->mapWithKeys(function ($row) use ($mapping) {
                $row['nr'] = $row['wym42'] = $mapping[$row['wym42'] ?? ''] ?? ($row['wym42'] ?? '');

                return [$row['nr'] => $row];
            })->all();
        }
        if (in_array($section, ['4-1', '4-2'])) {
            foreach ($out['custom'] as &$row) {
                $row['id'] ??= (string) Str::uuid();
                if ($section === '4-2') {
                    $row['nazwa'] = $row['strona'] ?? $row['nazwa'] ?? '';
                    $row['wymagania'] = $row['wymaganie'] ?? $row['wymagania'] ?? '';
                    $row['jak'] = $row['sposob'] ?? $row['jak'] ?? '';
                    $row['typ'] = ['wew.' => 'wewnętrzna', 'zew.' => 'zewnętrzna'][$row['typ'] ?? ''] ?? ($row['typ'] ?? '');
                }
            }
            unset($row);
        }
        foreach ($this->numberedRoots($section) as $root => $prefix) {
            $next = $this->maximumNumber([...($previous[$root] ?? []), ...($out[$root] ?? [])], $prefix, (int) ($previous['_html']['_ids'][$root] ?? 0));
            foreach ($out[$root] ?? [] as $i => $row) {
                if (blank($row['nr'] ?? null)) {
                    $out[$root][$i]['nr'] = $prefix.(++$next);
                }
            }
        }
        if ($section === '6-1') {
            $out['META'] = array_replace($previous['META'] ?? [], $out['META'] ?? []);
        }
        if ($section === '6-3') {
            foreach ($out['NOSNIKI'] ?? [] as $i => $row) {
                $out['NOSNIKI'][$i]['zrodlo'] ??= $out['MET']['MET-ZR'] ?? '';
            }
            foreach ($out['SEU'] as &$row) {
                $row['brakZmiennych'] ??= false;
            }
            unset($row);
        }
        if ($section === '6-4') {
            $plan = array_column($sources['plan'] ?? [], 'nr');
            $seu = array_column($sources['upstream']['6-3']['SEU'] ?? [], 'nr');
            foreach ($out['KARTY'] ?? [] as $i => $card) {
                $old = collect($previous['KARTY'] ?? [])->firstWhere('nr', $card['nr'] ?? '') ?? [];
                if (($card['zrodla'] ?? '') !== ($old['zrodla'] ?? '') || array_diff(array_filter(array_map('trim', explode(',', $card['plan'] ?? ''))), $plan)) {
                    $out['KARTY'][$i]['plan'] = '';
                }
                if (($card['obszarNazwa'] ?? '') !== ($old['obszarNazwa'] ?? '') || ! in_array($card['seu'] ?? '', $seu, true)) {
                    $out['KARTY'][$i]['seu'] = '';
                }
            }
        }
        if (in_array($section, ['8-2', '8-3', '10-1'])) {
            $root = ['8-2' => 'PROJEKTY', '8-3' => 'ZAKUPY', '10-1' => 'KARTY'][$section];
            $out['META']['brakZdarzen'] = empty($out[$root]) ? 'tak' : 'nie';
        }
        if ($section === '8-3') {
            foreach ($out['ZAKUPY'] ?? [] as $i => $row) {
                $out['ZAKUPY'][$i]['cenaEnergii'] = filled($row['cenaEnergii'] ?? null) ? $row['cenaEnergii'] : ($out['META']['cenaEnergii'] ?? '');
                if (empty($row['rekomendacja']) && in_array($row['porownanieNd'] ?? false, [true, 'tak'], true)) {
                    $out['ZAKUPY'][$i]['rekomendacja'] = $row['porownanieNdPowod'] ?? '';
                }
            }
        }
        if ($section === '9-3') {
            foreach ($out['DECYZJE'] ?? [] as $i => $row) {
                if (in_array($row['wlasna'] ?? false, [true, 'tak'], true)) {
                    $out['DECYZJE'][$i]['wlasna'] = $row['nazwa'] ?? '';
                } elseif (in_array($row['wlasna'] ?? false, [false, 'nie', null], true)) {
                    unset($out['DECYZJE'][$i]['wlasna']);
                }
            }
        }
        array_walk_recursive($out, function (&$value) {
            if (is_bool($value)) {
                $value = $value ? 'tak' : 'nie';
            } elseif (is_int($value) || is_float($value)) {
                $value = (string) $value;
            }
        });

        return $out;
    }

    public function fromAnswers(string $section, array $answers, array $sources = []): array
    {
        $original = $answers['_html'] ?? [];
        $out = $original;
        unset($answers['_html']);
        foreach ($answers as $root => $rows) {
            if (is_array($rows) && array_is_list($rows) && ! in_array($root, ['MAC', 'KON', 'WER', 'ODP'])) {
                $answers[$root] = array_values(array_filter($rows, fn ($row) => ! is_array($row) || $this->meaningful($row)));
            }
        }
        $out = $this->merge($out, $answers);
        if ($section === 'intro') {
            $out = ['ODP' => $original['ODP'] ?? [], 'STAN' => $out['STAN'] ?? []];
            foreach ($answers as $key => $answer) {
                if (str_starts_with($key, 'FAKT_') && is_array($answer)) {
                    $out['ODP'][$key] = ! empty($answer['unknown']) ? 'nie wiem' : ($answer['value'] ?? '');
                }
            }
            foreach (app(IsoPlantQuestionnaire::class)->questions(app(IsoPlantQuestionnaire::class)->definition()) as $question) {
                if ($question['type'] !== 'rows' || ! is_array($out['ODP'][$question['key']] ?? null)) {
                    continue;
                }
                foreach ($out['ODP'][$question['key']] as &$row) {
                    foreach ($question['fields'] as $key => $field) {
                        if ($field['type'] === 'number' && ($number = IsoPlantCalculations::number($row[$key] ?? null)) !== null) {
                            $row[$key] = json_encode($number, JSON_THROW_ON_ERROR);
                        }
                    }
                }
                unset($row);
            }

            return $out;
        }
        if ($section === '4-1') {
            if (! in_array($original['_schema'] ?? null, [null, '4.1/3'], true)) {
                throw ValidationException::withMessages(['html_state' => 'Nieobsługiwana wersja danych 4.1. Odpowiedzi zachowano; wymagany jest adapter tego układu przed edycją.']);
            }

            return ['DEC' => $answers['factors'] ?? [], 'WLASNE' => $answers['custom'] ?? [], 'KLIMAT' => ['odp' => $answers['FAKT_KLIMAT_ISTOTNY'] ?? '', 'uzas' => $answers['climate_reason'] ?? ''], 'PRAWO' => $answers['PRAWO'] ?? [], 'ODP' => $sources['facts'] ?? []];
        }
        if ($section === '4-2') {
            $mapping = array_flip($this->requirementIds());
            $out = ['W' => [], 'WLASNE' => $answers['custom'] ?? [], 'KLIMAT' => ['odp' => $answers['FAKT_KLIMAT_STRONY'] ?? '', 'lista' => $answers['climate_reason'] ?? '', 'odstep' => $answers['review_interval'] ?? '']];
            foreach ($answers['parties'] ?? [] as $code => $row) {
                $row['decyzja'] = ['potwierdzona' => 'dotyczy', 'odrzucona' => 'nie_dotyczy'][$row['decyzja'] ?? ''] ?? '';
                $row['sposob'] = $row['jak'] ?? '';
                $row['wymaganie'] = $row['wymagania'] ?? '';
                $out['W'][$mapping[$code] ?? $code] = $row;
            }
            foreach ($out['WLASNE'] as &$row) {
                $row['strona'] = $row['nazwa'] ?? '';
                $row['wymaganie'] = $row['wymagania'] ?? '';
                $row['sposob'] = $row['jak'] ?? '';
                $row['typ'] = ['wewnętrzna' => 'wew.', 'zewnętrzna' => 'zew.'][$row['typ'] ?? ''] ?? '';
            }
            unset($row);
        } elseif ($section === '4-3') {
            $out['KON'] = [];
            foreach (array_keys(app(IsoSystemQuestionnaire::class)->consequences($sources)) as $index => $key) {
                $out['KON'][$index] = $answers['KON'][$key] ?? [];
            }
        } elseif ($section === '5-3') {
            $out['MAT'] = [];
            foreach ($answers['ROLE'] ?? [] as $role) {
                foreach ($role['MAT'] ?? [] as $task => $value) {
                    $out['MAT'][$task.'|'.$role['id']] = $value;
                }
            }
        } elseif ($section === '6-2') {
            $out['WER'] = [];
            foreach ($answers['ZADANIA'] ?? [] as $row) {
                if (isset($row['nr'])) {
                    $out['WER'][$row['nr']] = $row['WER'] ?? [];
                }
            }
        } elseif ($section === '6-3') {
            $out['SEU'] = [];
            foreach ($answers['SEU'] ?? [] as $row) {
                $index = array_search($row['nazwa'] ?? '', array_column($answers['OBSZARY'] ?? [], 'nazwa'), true);
                $out['SEU'][$index === false ? 'G:'.($row['nazwa'] ?? '') : $index] = $row;
            }
        } elseif ($section === '6-5') {
            foreach ($out['KARTY'] ?? [] as $i => $card) {
                $out['KARTY'][$i]['historia'] = array_values(array_map(function ($revision) {
                    $revision['uzasadnienie'] = $revision['opis'] ?? '';

                    return $revision;
                }, array_filter($answers['REWIZJE'] ?? [], fn ($revision) => ($revision['enpi'] ?? '') === ($card['nr'] ?? ''))));
            }
        } elseif ($section === '7-1') {
            foreach ($out['ZADANIA'] as &$row) {
                $row['osoba'] = $answers['OSOBY'][$row['osoba'] ?? '']['nazwa'] ?? '';
            }
            unset($row);
        } elseif ($section === '7-2') {
            $out['MAC'] = [];
            foreach ($answers['MAC'] ?? [] as $competency => $roles) {
                foreach ($roles as $role => $value) {
                    $out['MAC'][$competency.'|'.$role] = ($value['W'] ?? '0').'/'.($value['A'] ?? '0');
                }
            }
        }
        if (in_array($section, ['9-1-1', '10-2'])) {
            $root = $section === '9-1-1' ? 'WSKAZNIKI' : 'POPRAWA';
            $out[$root] = array_values($out[$root] ?? []);
        }
        if ($section === '9-1-2') {
            $mapping = array_flip($this->requirementIds());
            $out['WYM'] = array_values(array_map(function ($row) use ($mapping) {
                $row['wym42'] = $mapping[$row['wym42'] ?? ''] ?? ($row['wym42'] ?? '');

                return $row;
            }, $out['WYM'] ?? []));
        }
        if ($section === '9-3') {
            foreach ($out['DECYZJE'] ?? [] as $i => $row) {
                $out['DECYZJE'][$i]['wlasna'] = ! in_array($row['wlasna'] ?? null, [false, 'nie', null, ''], true);
            }
        }

        return $this->aliases($section, $out, true, $original);
    }

    private function aliases(string $section, array $state, bool $reverse, array $original = []): array
    {
        $maps = match ($section) {
            '4-4' => ['CZ' => ['stan' => ['jest' => 'tak']], 'PROC' => ['forma' => ['opis' => 'system', 'nadbudowa' => 'inne']]],
            '5-1' => ['INT' => ['stan' => ['jest' => 'tak', 'czesciowo' => 'zapis']], 'STYK' => ['stan' => ['jest' => 'tak']], 'ZAS' => ['stan' => ['jest' => 'zapewnione']]],
            '6-2' => ['CELE' => ['status' => ['planowane' => 'plan', 'zatwierdzone' => 'plan', 'wykonane' => 'zakonczone']], 'DZIAL' => ['status' => ['planowane' => 'plan', 'zatwierdzone' => 'plan', 'wykonane' => 'zakonczone']]],
            '6-3' => ['OBSZARY' => ['metoda' => ['Sz' => 'S']]],
            '7-5' => [],
            default => [],
        };
        foreach ($maps as $root => $fields) {
            foreach ($state[$root] ?? [] as $index => $row) {
                foreach ($fields as $field => $map) {
                    $old = $original[$root][$index][$field] ?? null;
                    if ($reverse && $old !== null && isset($map[$old]) && $map[$old] === ($row[$field] ?? null)) {
                        $state[$root][$index][$field] = $old;

                        continue;
                    }
                    $map = $reverse ? array_flip($map) : $map;
                    if (isset($map[$row[$field] ?? ''])) {
                        $state[$root][$index][$field] = $map[$row[$field]];
                    }
                }
            }
        }
        if ($section === '7-5') {
            foreach ($state['WYKAZ'] ?? [] as $index => $row) {
                if (str_replace(' ', '', $row['rodzaj'] ?? '') === 'D/Z') {
                    $state['WYKAZ'][$index]['rodzaj'] = 'DZ';
                }
            }
        }
        if ($section === '4-4' && ! $reverse) {
            foreach ($state['PROC'] ?? [] as $key => $row) {
                $process = collect(app(IsoPlatformRelease::class)->source('4-4')['data']['procesy'])->firstWhere('kod', $key);
                $state['PROC'][$key]['trafia'] ??= $process['trafia'] ?? '';
                $state['PROC'][$key]['uwagi'] ??= $process['ksiazka'] ?? '';
                if (($row['forma'] ?? '') === 'inne') {
                    $state['PROC'][$key]['integracja'] ??= 'Rozszerzenie istniejącej procedury ISO 14001 / 9001';
                }
            }
        }
        if ($section === '7-2') {
            foreach ($state['LUKI'] ?? [] as $index => $row) {
                if ($reverse && preg_match('/^(\d+)\|(\d+)$/', $row['dotyczy'] ?? '', $match)) {
                    $state['LUKI'][$index]['dotyczy'] = ($state['ROLE'][$match[2]]['nazwa'] ?? '').' — '.($state['KOMPETENCJE'][$match[1]]['nazwa'] ?? '');
                } elseif (! $reverse) {
                    foreach ($state['KOMPETENCJE'] ?? [] as $i => $competency) {
                        foreach ($state['ROLE'] ?? [] as $r => $role) {
                            if (($row['dotyczy'] ?? '') === $role['nazwa'].' — '.$competency['nazwa']) {
                                $state['LUKI'][$index]['dotyczy'] = $i.'|'.$r;
                            }
                        }
                    }
                }
            }
        }

        return $state;
    }

    private function requirementIds(): array
    {
        return json_decode(file_get_contents(resource_path('iso50001/platform-2026-10-18/requirement-ids.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    private function merge(array $source, array $canonical, bool $byIndex = true): array
    {
        if (array_is_list($canonical) && $canonical !== []) {
            return array_map(function ($row, $index) use ($source, $byIndex) {
                if (! is_array($row)) {
                    return $row;
                }
                $old = $byIndex ? ($source[$index] ?? []) : [];
                foreach (['id', 'nr', 'kod'] as $key) {
                    if (isset($row[$key])) {
                        $old = collect($source)->firstWhere($key, $row[$key]) ?? $old;
                        break;
                    }
                }

                return $this->merge(is_array($old) ? $old : [], $row, $byIndex);
            }, $canonical, array_keys($canonical));
        }
        foreach ($canonical as $key => $value) {
            $source[$key] = is_array($value) && $value !== [] ? $this->merge(is_array($source[$key] ?? null) ? $source[$key] : [], $value, $byIndex) : $value;
        }

        return $source;
    }

    private function meaningful(array $row): bool
    {
        foreach ($row as $key => $value) {
            if ($key !== 'id' && (is_array($value) ? $this->meaningful($value) : filled($value))) {
                return true;
            }
        }

        return false;
    }

    private function numberedRoots(string $section): array
    {
        return match ($section) {
            '6-1' => ['RYZYKA' => 'R', 'SZANSE' => 'S'],
            '6-3' => ['OBSZARY' => 'O-', 'MOZL' => 'M-'],
            '6-6' => ['POZYCJE' => ''],
            default => [],
        };
    }

    private function maximumNumber(array $rows, string $prefix, int $maximum): int
    {
        foreach ($rows as $row) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/D', (string) ($row['nr'] ?? ''), $match)) {
                $maximum = max($maximum, (int) $match[1]);
            }
        }

        return $maximum;
    }
}
