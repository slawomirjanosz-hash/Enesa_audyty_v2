<?php

namespace App\Services;

use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IsoStakeholderQuestionnaire
{
    private ?array $library = null;

    public function schema(): array
    {
        return $this->library ??= json_decode(file_get_contents(resource_path('iso50001/stakeholders-v1.json')), true, 512, JSON_THROW_ON_ERROR);
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
            $mapping = array_values(array_filter($this->schema()['mapowanie'], fn ($m) => $m['strona'] === $party['kod'] && isset($confirmed[$m['czynnik']])));
            preg_match_all('/\b(?:FAKT_[A-Z0-9_]+|ZUZYCIE_TJ)\b/', $party['warunek'], $matches);
            $dependencies = array_replace(array_fill_keys($matches[0], null), array_intersect_key($facts, array_flip($matches[0])));
            $party['dependencies'] = $dependencies;
            $party['mapping'] = $mapping;
            $party['visible'] = $this->matches($party['warunek'], $facts) || count($mapping) > 0;
            $party['wymagania'] = implode("\n", array_unique([$party['wymagania'], ...array_column($mapping, 'wymaganie')]));
            // The party-level fixed classification wins over a mapped candidate (STK-Z-16).
            $party['basis'] = hash('sha256', json_encode([$dependencies, $mapping, array_intersect_key($confirmed, array_flip(array_column($mapping, 'czynnik')))], JSON_THROW_ON_ERROR));

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
            'parties' => 'nullable|array|max:28', 'parties.*' => 'array',
            'parties.*.decyzja' => ['nullable', Rule::in(['potwierdzona', 'odrzucona'])],
            'parties.*.wymagania' => 'nullable|string|max:10000', 'parties.*.powod' => 'nullable|string|max:3000', 'parties.*.jak' => 'nullable|string|max:5000',
            'custom' => 'nullable|array|max:20', 'custom.*' => 'array', 'custom.*.id' => 'required|uuid|distinct',
            'custom.*.nazwa' => 'required|string|max:255', 'custom.*.wymagania' => 'required|string|max:10000',
            'custom.*.typ' => ['required', Rule::in(['wewnętrzna', 'zewnętrzna'])], 'custom.*.jak' => 'nullable|string|max:5000',
        ])->validate();
        $out = ['FAKT_KLIMAT_STRONY' => $data['FAKT_KLIMAT_STRONY'] ?? null, 'climate_reason' => $data['climate_reason'] ?? null, 'parties' => [], 'custom' => []];
        foreach ($data['custom'] ?? [] as $row) {
            $out['custom'][] = array_intersect_key($row, array_flip(['id', 'nazwa', 'wymagania', 'typ', 'jak']));
        }
        $errors = [];
        if ($complete && (! $out['FAKT_KLIMAT_STRONY'] || ! filled($out['climate_reason']))) {
            $errors['climate_reason'] = 'Oceń wymagania klimatyczne i opisz wymagania lub uzasadnij ich brak.';
        }
        foreach ($parties as $party) {
            $code = $party['kod'];
            $row = $data['parties'][$code] ?? [];
            $out['parties'][$code] = ['decyzja' => $row['decyzja'] ?? null, 'powod' => $row['powod'] ?? null, 'wymagania' => $row['wymagania'] ?? $party['wymagania'], 'jak' => $row['jak'] ?? $party['jak']];
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
            $rows[] = $row + ['kod' => 'CUSTOM-'.$row['id'], 'zgodnosc' => 'KANDYDAT', 'basis' => '', 'jak' => ''];
        }

        return array_map(function ($row) use ($consultant) {
            $decision = $consultant[$row['kod']] ?? [];
            $current = ($decision['basis'] ?? '') === $this->decisionHash($row);
            $row['classification'] = $row['zgodnosc'];
            $row['zgodnosc'] = $row['zgodnosc'] === 'TAK' ? 'tak' : ($row['zgodnosc'] === 'nie' ? 'nie' : ($current ? ($decision['zgodnosc'] ?? 'pending') : 'pending'));
            $row['consultant'] = $current ? $decision : [];

            return $row;
        }, $rows);
    }

    public function progress(array $answers, array $parties, array $consultant = []): array
    {
        $total = 1;
        $done = ! empty($answers['FAKT_KLIMAT_STRONY']) && filled($answers['climate_reason'] ?? null) ? 1 : 0;
        $register = collect($this->register($answers, $parties, $consultant))->keyBy('kod');
        foreach ($parties as $party) {
            if (! $party['visible']) {
                continue;
            }
            $total++;
            $row = $answers['parties'][$party['kod']] ?? [];
            $done += (($row['decyzja'] ?? '') === 'odrzucona' && filled($row['powod'] ?? null)) || (($row['decyzja'] ?? '') === 'potwierdzona' && filled($row['wymagania'] ?? null) && ($register[$party['kod']]['zgodnosc'] ?? 'pending') !== 'pending') ? 1 : 0;
        }
        foreach ($answers['custom'] ?? [] as $row) {
            $total++;
            $done += filled($row['nazwa'] ?? null) && filled($row['wymagania'] ?? null) && ($register['CUSTOM-'.$row['id']]['zgodnosc'] ?? 'pending') !== 'pending' ? 1 : 0;
        }

        return app(QuestionnaireCompletion::class)->result($done, $total);
    }
}
