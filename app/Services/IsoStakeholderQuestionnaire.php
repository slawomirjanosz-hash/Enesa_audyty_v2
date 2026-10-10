<?php

namespace App\Services;

use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IsoStakeholderQuestionnaire
{
    public const QUALIFICATIONS = ['prawne' => 'Wymaganie prawne', 'umowne' => 'Wymaganie umowne', 'przyjete' => 'Przyjęte zobowiązanie', 'oczekiwanie_nieprzyjete' => 'Oczekiwanie nieprzyjęte'];

    public const REQUIREMENT_FIELDS = ['kwalifikacja' => 'Kwalifikacja', 'zr_dok' => 'Dokument źródłowy', 'zr_ozn' => 'Paragraf / rozdział / wersja', 'zr_data' => 'Data dokumentu źródłowego', 'data_brzmienia' => 'Data sprawdzenia brzmienia przepisu', 'wlasciciel' => 'Właściciel — stanowisko', 'sprawdzanie' => 'Jak i kiedy sprawdzamy spełnienie wymagania'];

    private ?array $library = null;

    public function schema(): array
    {
        if ($this->library !== null) {
            return $this->library;
        }
        $old = json_decode(file_get_contents(resource_path('iso50001/stakeholders-v1.json')), true, 512, JSON_THROW_ON_ERROR);
        $new = json_decode(file_get_contents(resource_path('iso50001/requirements-v2.json')), true, 512, JSON_THROW_ON_ERROR);
        $old['strony'] = array_map(fn ($row) => [
            'kod' => $row['id'], 'strona_kod' => $row['strona_kod'], 'nazwa' => $row['strona'],
            'typ' => $row['typ'] === 'wew.' ? 'wewnętrzna' : 'zewnętrzna',
            'wymagania' => $row['wymaganie'], 'jak' => $row['sposob_sug'], 'warunek' => $row['pokaz_gdy'],
            'zgodnosc' => 'KANDYDAT', 'suggestions' => $row,
        ], $new['wymagania']);
        $old['grupy'] = array_map(function ($group) use ($old) {
            $group['kody'] = array_column(array_filter($old['strony'], fn ($row) => in_array($row['strona_kod'], $group['strony'], true)), 'kod');

            return $group;
        }, $new['grupy']);

        // Mapping proposes a party, never establishes a legal obligation or replaces its requirement.
        return $this->library = $old;
    }

    public function hash(IsoPlantProfile $profile, ?IsoFactorReview $factors): string
    {
        return hash('sha256', json_encode([app(IsoFactorQuestionnaire::class)->hash($profile), $factors?->id, $factors?->lock_version, $factors?->client_approval, $this->schema()], JSON_THROW_ON_ERROR));
    }

    public function ready(IsoPlantProfile $profile, ?IsoFactorReview $factors): bool
    {
        return $profile->status === 'approved' && $profile->client_approval && $profile->auditor_approval && $factors && $factors->client_approval && in_array($factors->status, ['submitted', 'approved']) && $factors->source_hash === app(IsoFactorQuestionnaire::class)->hash($profile);
    }

    /** Inert allowlisted conditions; missing values never imply a negative answer. */
    private function matches(string $condition, array $facts): bool
    {
        foreach (explode(' OR ', $condition) as $alternative) {
            $matches = true;
            foreach (explode(' AND ', $alternative) as $term) {
                preg_match('/^([A-Z0-9_]+)/', $term, $key);
                $actual = $facts[$key[1] ?? ''] ?? null;
                if (is_array($actual)) {
                    $matched = false;
                    foreach ($actual as $value) {
                        if (is_scalar($value) && app(IsoContextLibrary::class)->matches($term, array_replace($facts, [$key[1] => $value]))) {
                            $matched = true;
                        }
                    }
                    $matches = $matches && $matched;
                } else {
                    $matches = $matches && app(IsoContextLibrary::class)->matches($term, $facts);
                }
            }
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    public function parties(IsoPlantProfile $profile, ?IsoFactorReview $review): array
    {
        $service = app(IsoFactorQuestionnaire::class);
        $facts = $service->facts($profile);
        $confirmed = [];
        if ($this->ready($profile, $review)) {
            foreach ($service->factors($facts, $review->answers) as $factor) {
                if ($factor['visible'] && ($factor['rodzaj'] === 'AUTO' || ($review->answers['factors'][$factor['kod']]['decyzja'] ?? '') === 'potwierdzony')) {
                    $confirmed[$factor['kod']] = $review->answers['factors'][$factor['kod']]['tresc'] ?? $factor['tresc'];
                }
            }
        }

        return array_map(function ($party) use ($facts, $confirmed) {
            $mapping = array_values(array_filter($this->schema()['mapowanie'], fn ($m) => $m['strona'] === $party['strona_kod'] && isset($confirmed[$m['czynnik']])));
            preg_match_all('/\b(?:FAKT_[A-Z0-9_]+|ZUZYCIE_TJ)\b/', $party['warunek'], $matches);
            $dependencies = array_replace(array_fill_keys($matches[0], null), array_intersect_key($facts, array_flip($matches[0])));
            $party['dependencies'] = $dependencies;
            $party['mapping'] = $mapping;
            $party['visible'] = $this->matches($party['warunek'], $facts) || count($mapping) > 0;
            // The party-level fixed classification wins over a mapped candidate (STK-Z-16).
            $party['basis'] = hash('sha256', json_encode([$party['kod'], $party['wymagania'], $dependencies, $mapping, array_intersect_key($confirmed, array_flip(array_column($mapping, 'czynnik')))], JSON_THROW_ON_ERROR));

            return $party;
        }, $this->schema()['strony']);
    }

    public function currentAnswers(array $answers, array $basis, array $parties): array
    {
        foreach ($parties as $party) {
            if (($basis[$party['kod']] ?? '') !== $party['basis']) {
                unset($answers['parties'][$party['kod']]['decyzja']);
            }
        }

        return $answers;
    }

    public function normalize(array $input, array $parties, bool $complete, array $consultant): array
    {
        $data = Validator::make($input, [
            'FAKT_KLIMAT_STRONY' => ['nullable', Rule::in(['tak', 'nie'])], 'climate_reason' => 'nullable|string|max:3000',
            'review_interval' => [$complete ? 'required' : 'nullable', Rule::in(['kwartal', 'polrocze', 'rok', 'rok_umowy'])],
            'parties' => 'nullable|array|max:30', 'parties.*' => 'array',
            'parties.*.kwalifikacja' => ['nullable', Rule::in(array_keys(self::QUALIFICATIONS))],
            'parties.*.zr_dok' => 'nullable|string|max:5000', 'parties.*.zr_ozn' => 'nullable|string|max:5000',
            'parties.*.zr_data' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'parties.*.data_brzmienia' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'parties.*.wlasciciel' => 'nullable|string|max:255', 'parties.*.sprawdzanie' => 'nullable|string|max:5000',
            'parties.*.decyzja' => ['nullable', Rule::in(['potwierdzona', 'odrzucona'])],
            'parties.*.wymagania' => 'nullable|string|max:10000', 'parties.*.powod' => 'nullable|string|max:3000', 'parties.*.jak' => 'nullable|string|max:5000',
            'custom' => 'nullable|array|max:20', 'custom.*' => 'array', 'custom.*.id' => 'required|uuid|distinct',
            'custom.*.nazwa' => 'required|string|max:255', 'custom.*.wymagania' => 'required|string|max:10000',
            'custom.*.typ' => ['required', Rule::in(['wewnętrzna', 'zewnętrzna'])], 'custom.*.jak' => 'nullable|string|max:5000',
            'custom.*.powod' => 'nullable|string|max:3000',
            'custom.*.kwalifikacja' => ['nullable', Rule::in(array_keys(self::QUALIFICATIONS))],
            'custom.*.zr_dok' => 'nullable|string|max:5000', 'custom.*.zr_ozn' => 'nullable|string|max:5000',
            'custom.*.zr_data' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'custom.*.data_brzmienia' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'custom.*.wlasciciel' => 'nullable|string|max:255', 'custom.*.sprawdzanie' => 'nullable|string|max:5000',
        ])->validate();
        $out = ['FAKT_KLIMAT_STRONY' => $data['FAKT_KLIMAT_STRONY'] ?? null, 'climate_reason' => $data['climate_reason'] ?? null, 'parties' => [], 'custom' => []];
        $out['review_interval'] = $data['review_interval'] ?? null;
        foreach ($data['custom'] ?? [] as $row) {
            $out['custom'][] = array_intersect_key($row, array_flip(['id', 'nazwa', 'wymagania', 'typ', 'jak', 'powod', ...array_keys(self::REQUIREMENT_FIELDS)]));
        }
        $errors = [];
        if ($complete) {
            foreach ($out['custom'] as $index => $row) {
                foreach ($this->requiredFields($row) as $key) {
                    if (! filled($row[$key] ?? null)) {
                        $errors["custom.$index.$key"] = 'Uzupełnij kwalifikację i dane własnego wymagania.';
                    }
                }
            }
        }
        if ($complete && (! $out['FAKT_KLIMAT_STRONY'] || ! filled($out['climate_reason']))) {
            $errors['climate_reason'] = 'Oceń wymagania klimatyczne i opisz wymagania lub uzasadnij ich brak.';
        }
        foreach ($parties as $party) {
            $code = $party['kod'];
            $row = $data['parties'][$code] ?? [];
            $out['parties'][$code] = ['decyzja' => $row['decyzja'] ?? null, 'powod' => $row['powod'] ?? null, 'wymagania' => $row['wymagania'] ?? $party['wymagania'], 'jak' => $row['jak'] ?? $party['jak']];
            foreach (array_keys(self::REQUIREMENT_FIELDS) as $key) {
                $out['parties'][$code][$key] = $row[$key] ?? null;
            }
            if ($complete && $party['visible'] && ($row['decyzja'] ?? '') === 'potwierdzona') {
                foreach ($this->requiredFields($row) as $key) {
                    if (! filled($out['parties'][$code][$key] ?? null)) {
                        $errors["parties.$code.$key"] = "$code: uzupełnij ".(self::REQUIREMENT_FIELDS[$key] ?? ['jak' => 'sposób realizacji', 'powod' => 'uzasadnienie decyzji'][$key]).'.';
                    }
                }
            }
            if (! $party['visible']) {
                continue;
            }
            if ($complete && ! filled($row['decyzja'] ?? null)) {
                $errors["parties.$code.decyzja"] = "$code: potwierdź lub odrzuć stronę.";
            }
            if (($row['decyzja'] ?? '') === 'odrzucona' && ! filled($row['powod'] ?? null)) {
                $errors["parties.$code.powod"] = "$code: podaj uzasadnienie odrzucenia.";
            }
            if (($row['decyzja'] ?? '') === 'potwierdzona' && ! filled($out['parties'][$code]['wymagania'])) {
                $errors["parties.$code.wymagania"] = "$code: wpisz rzeczywiste wymagania strony.";
            }
        }
        if ($complete) {
            foreach ($this->register($out, $parties, $consultant) as $row) {
                if ($row['zgodnosc'] === 'pending') {
                    $errors['consultant'] = 'Konsultant musi rozstrzygnąć kandydatów i własne strony przed zatwierdzeniem klienta.';
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    public function decisionHash(array $row): string
    {
        return hash('sha256', json_encode(array_intersect_key($row, array_flip(['kod', 'nazwa', 'typ', 'wymagania', 'jak', 'basis'])), JSON_THROW_ON_ERROR));
    }

    public function requiredFields(array $row): array
    {
        return match ($row['kwalifikacja'] ?? '') {
            'prawne' => ['kwalifikacja', 'zr_dok', 'zr_ozn', 'zr_data', 'data_brzmienia', 'wlasciciel', 'sprawdzanie', 'jak'],
            'umowne', 'przyjete' => ['kwalifikacja', 'zr_dok', 'zr_ozn', 'zr_data', 'wlasciciel', 'sprawdzanie', 'jak'],
            'oczekiwanie_nieprzyjete' => ['kwalifikacja', 'powod'],
            default => ['kwalifikacja'],
        };
    }

    public function register(array $answers, array $parties, array $consultant): array
    {
        $rows = [];
        foreach ($parties as $party) {
            $answer = $answers['parties'][$party['kod']] ?? [];
            if ($party['visible'] && ($answer['decyzja'] ?? '') === 'potwierdzona') {
                $rows[] = array_replace($party, $answer);
            }
        }
        foreach ($answers['custom'] ?? [] as $row) {
            if (filled($row['kwalifikacja'] ?? null)) {
                $row['strona_kod'] = 'CUSTOM-'.$row['id'];
            }
            $rows[] = $row + ['kod' => 'CUSTOM-'.$row['id'], 'zgodnosc' => 'KANDYDAT', 'basis' => '', 'jak' => ''];
        }

        return array_map(function ($row) use ($consultant) {
            $decision = $consultant[$row['kod']] ?? [];
            $current = ($decision['basis'] ?? '') === $this->decisionHash($row);
            $row['classification'] = $row['zgodnosc'];
            $row['zgodnosc'] = $row['zgodnosc'] === 'TAK' ? 'tak' : ($row['zgodnosc'] === 'nie' ? 'nie' : ($current ? ($decision['zgodnosc'] ?? 'pending') : 'pending'));
            $row['consultant'] = $current ? $decision : [];
            if (isset($row['strona_kod'])) {
                $qualification = $row['kwalifikacja'] ?? '';
                $row['zgodnosc'] = $qualification === 'oczekiwanie_nieprzyjete' ? 'nie' : (in_array($qualification, ['prawne', 'umowne', 'przyjete'], true) ? 'tak' : 'pending');
                $row['classification'] = $row['zgodnosc'] === 'tak' ? 'TAK' : ($row['zgodnosc'] === 'nie' ? 'nie' : 'KANDYDAT');
                $row['consultant'] = array_replace($row['consultant'], ['legal_basis' => implode(' · ', array_filter([$row['zr_dok'] ?? null, $row['zr_ozn'] ?? null, $row['zr_data'] ?? null])), 'owner' => $row['wlasciciel'] ?? null, 'reason' => $row['powod'] ?? null]);
            }

            return $row;
        }, $rows);
    }

    public function progress(array $answers, array $parties, array $consultant = []): array
    {
        $total = 2;
        $done = ! empty($answers['FAKT_KLIMAT_STRONY']) && filled($answers['climate_reason'] ?? null) ? 1 : 0;
        $done += filled($answers['review_interval'] ?? null) ? 1 : 0;
        $register = collect($this->register($answers, $parties, $consultant))->keyBy('kod');
        foreach ($parties as $party) {
            if (! $party['visible']) {
                continue;
            }
            $total++;
            $row = $answers['parties'][$party['kod']] ?? [];
            $done += (($row['decyzja'] ?? '') === 'odrzucona' && filled($row['powod'] ?? null)) || (($row['decyzja'] ?? '') === 'potwierdzona' && filled($row['wymagania'] ?? null) && collect($this->requiredFields($row))->every(fn ($key) => filled($row[$key] ?? null)) && ($register[$party['kod']]['zgodnosc'] ?? 'pending') !== 'pending') ? 1 : 0;
        }
        foreach ($answers['custom'] ?? [] as $row) {
            $total++;
            $done += filled($row['nazwa'] ?? null) && filled($row['wymagania'] ?? null) && collect($this->requiredFields($row))->every(fn ($key) => filled($row[$key] ?? null)) && ($register['CUSTOM-'.$row['id']]['zgodnosc'] ?? 'pending') !== 'pending' ? 1 : 0;
        }

        return app(QuestionnaireCompletion::class)->result($done, $total);
    }
}
