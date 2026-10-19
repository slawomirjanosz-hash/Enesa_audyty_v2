<?php

namespace App\Services;

use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use Illuminate\Support\Str;

/** The supplied HTML is the presentation contract, rather than a source of labels for a generic form. */
class IsoHtmlQuestionnaire
{
    private static ?array $manifest = null;

    private static array $documents = [];

    public function manifest(): array
    {
        return self::$manifest ??= json_decode(file_get_contents(resource_path('iso50001/html-2026-10-18/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function document(string $section): string
    {
        abort_unless(in_array($section, array_column($this->manifest(), 'section'), true), 404);

        return self::$documents[$section] ??= file_get_contents(resource_path('iso50001/html-2026-10-18/'.$section.'.html'));
    }

    public function point(string $section, string $cards, string $channel, string $identity, bool $client): string
    {
        $document = new \DOMDocument;
        $errors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?>'.$this->document($section), LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($errors);
        }
        $page = $document->getElementById('w_strona');
        abort_unless($page, 404);
        $xpath = new \DOMXPath($document);
        $box = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ankbox ")]', $page)->item(0);
        $title = $box?->getElementsByTagName('h3')->item(0)?->textContent ?? $document->getElementById('t_ankieta')?->textContent ?? 'Ankieta';
        $cards = str_replace('ENESA_SOURCE_QUESTIONNAIRE_TITLE', e($title), $cards);
        $placeholder = $document->createElement('div', 'ENESA_PROFILE_CARDS');
        if ($box) {
            $box->parentNode->replaceChild($placeholder, $box);
        } else {
            ($page->getElementsByTagName('main')->item(0) ?? $page)->appendChild($placeholder);
        }
        $header = $document->getElementsByTagName('header')->item(0);
        $subtitle = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " sub ")]', $header)->item(0);
        if ($subtitle) {
            $subtitle->textContent = $identity;
        }
        if ($client && ($tab = $document->getElementById('t_spec'))) {
            $tab->parentNode->removeChild($tab);
        }
        $spec = $document->getElementById('w_spec');
        $style = $document->getElementsByTagName('style')->item(0)->textContent;
        $body = $document->saveHTML($header).str_replace('<div>ENESA_PROFILE_CARDS</div>', $cards, $document->saveHTML($page));
        if (! $client && $spec) {
            $body .= $document->saveHTML($spec);
        }
        $configuration = json_encode(['channel' => $channel], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $script = '<script>var ENESA_POINT='.$configuration.';'.file_get_contents(public_path('js/iso-html-clipboard-frame.js')).file_get_contents(public_path('js/iso-html-point.js')).'</script>';

        return '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; script-src \'unsafe-inline\'; style-src \'unsafe-inline\'; form-action \'none\'"><style>'.$style.'</style></head><body>'.$body.$script.'</body></html>';
    }

    public function frame(string $section, array $configuration): string
    {
        $html = $this->document($section);
        $configuration['energyFactors'] = IsoPlantCalculations::factors();
        $configuration['roots'] = $section === 'intro' ? ['ODP', 'STAN'] : collect($this->manifest())->firstWhere('section', $section)['state'];
        $json = json_encode($configuration, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        // The prototype's browser storage is replaced by a frame-local memory store.
        // The sandbox has no origin or network permission; only the parent can save.
        $bootstrap = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; script-src \'unsafe-inline\'; style-src \'unsafe-inline\'; img-src data:; form-action \'none\'"><script>var ENESA_HOST='.$json.';var localStorage={getItem:function(){return null},setItem:function(){},removeItem:function(){}};</script>';
        $html = str_replace('<head>', '<head>'.$bootstrap, $html);
        $actions = file_get_contents(public_path('js/table-actions.js'));
        preg_match('/export function isActionHeading\(.*?(?=export function enhanceAction)/s', $actions, $heading);
        $sort = preg_replace('/^import .*?;\s*/', '', file_get_contents(public_path('js/table-sort.js')));
        $bridge = '<script>'.file_get_contents(public_path('js/iso-plant-energy.js')).file_get_contents(public_path('js/iso-html-clipboard-frame.js')).file_get_contents(public_path('js/iso-html-frame.js')).'</script><script type="module">'.$heading[0].$sort.'</script>';

        return str_replace('</body>', $bridge.'</body>', $html);
    }

    public function configuration(string $section, array $data): array
    {
        $profile = $data['profile'];
        $client = $data['client'];
        $review = $data['review'] ?? $profile;
        $sources = $data['sources'] ?? ['facts' => app(IsoFactorQuestionnaire::class)->facts($profile)];
        $answers = old('answers', $data['answers'] ?? $profile->answers ?? []);
        $answers = is_array($answers) ? $answers : [];
        $state = app(IsoHtmlState::class)->fromAnswers($section, $answers, $sources);
        $context = ['PROFIL' => $sources['facts'] ?? [], 'TERAZ' => now()->format('d.m.Y H:i'), 'DZIS' => today()->toDateString()];
        if ($section === '4-1') {
            $context['ODP'] = $sources['facts'] ?? [];
            $context['PROFIL'] = ['nazwa' => $profile->answers['site.name']['value'] ?? '', 'odpowiedzi' => count($sources['facts'] ?? [])];
            if (! $client) {
                $swot = old('swot', $data['exampleSwot'] ?? $review->swot ?? []);
                foreach (['S' => 'strengths', 'W' => 'weaknesses', 'O' => 'opportunities', 'T' => 'threats'] as $key => $field) {
                    $state['SWOT'][$key] = $swot[$field] ?? '';
                }
                $state['WNIOSKI'] = $swot['wnioski'] ?? [];
            }
        } elseif ($section === '4-2') {
            $factor = ! empty($data['questionnairePreview']) ? null : IsoFactorReview::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->first();
            $confirmed = [];
            foreach (app(IsoFactorQuestionnaire::class)->factors($sources['facts'] ?? [], $factor?->answers ?? []) as $item) {
                if ($item['visible'] && ($item['rodzaj'] === 'AUTO' || data_get($factor?->answers, 'factors.'.$item['kod'].'.decyzja') === 'potwierdzony')) {
                    $confirmed[] = $item['kod'];
                }
            }
            $context += ['ODP' => $sources['facts'] ?? [], 'POTWIERDZONE' => $confirmed, 'CZYN41' => $confirmed, 'WYL' => ['ZUZYCIE_TJ' => $sources['facts']['ZUZYCIE_TJ'] ?? null]];
        } elseif ($section === '4-3') {
            $context['KONSEKWENCJE'] = array_values(app(IsoSystemQuestionnaire::class)->consequences($sources));
        } elseif ($section === '6-6') {
            $context['KARTY'] = array_values($sources['cards'] ?? []);
        } elseif ($section === '7-3') {
            $context['PLAN'] = array_values(app(IsoSupportQuestionnaire::class)->plan($sources));
        } elseif ($section === '4-4') {
            $context['STAN_KLAUZUL'] = [];
            foreach (array_keys(app(IsoPlatformRelease::class)->source('4-4')['variables']['STAN_KLAUZUL']) as $clause) {
                $key = str_replace('.', '-', $clause);
                $context['STAN_KLAUZUL'][$clause] = ['stan' => ! empty($sources['published'][$key]) ? 'gotowy' : (! empty($sources['approved'][$key]) ? 'opracowanie' : 'nierozpoczety')];
            }
        }
        if (! $client && preg_match('/^(?:[5-9]|10)-/', $section)) {
            $state['WNIOSKI'] = old('consultant.WNIOSKI', $data['exampleConsultant']['WNIOSKI'] ?? $review->consultant['WNIOSKI'] ?? []);
        }
        $roots = $section === 'intro' ? ['ODP', 'STAN'] : collect($this->manifest())->firstWhere('section', $section)['state'];
        $state = array_intersect_key($state, array_flip([...$roots, ...($client ? [] : ['SWOT', 'WNIOSKI'])]));
        $editable = ($data['canWrite'] ?? false) && (! $client || ($data['stale'] ?? false) || in_array($review->status, ['editing', 'returned', 'auditor_corrected']));
        if ($section === 'intro') {
            $editable = $editable && ($data['isLatest'] ?? true);
        }
        if (in_array($section, ['4-1', '4-2'])) {
            $editable = $editable && ($data['ready'] ?? false);
        }
        $sourceReady = $section === 'intro' || (in_array($section, ['4-1', '4-2']) ? ($data['ready'] ?? false) : ($sources['ready'] ?? false));

        return ['section' => $section, 'channel' => (string) Str::uuid(), 'state' => $state, 'context' => $context, 'client' => $client, 'editable' => $editable,
            'initialDirty' => request()->boolean('source_example'),
            'unknown' => $section === 'intro' ? array_keys(array_filter($answers, fn ($answer) => is_array($answer) && ! empty($answer['unknown']))) : [],
            'identity' => isset($data['audit']) ? $data['audit']->company->name.' · '.($profile->answers['site.name']['value'] ?? '').' · '.(($data['stale'] ?? false) ? 'Wymaga ponownego zatwierdzenia' : IsoPlantProfile::STATUSES[$review->status]) : 'Podgląd bez danych klienta',
            'submit' => $editable && $sourceReady && ($data['canClientApprove'] ?? false),
            'submitHint' => $sourceReady ? 'Zatwierdza uprawniony administrator klienta po uzupełnieniu ankiety.' : 'Najpierw zatwierdź aktualne punkty źródłowe.',
            'consultant' => ! $client && ($data['canWrite'] ?? false) && ! ($data['stale'] ?? false) && ($section === '4-1'
                ? (($data['ready'] ?? false) && $review->client_approval && in_array($review->status, ['submitted', 'approved']))
                : ($review->status === 'submitted' && preg_match('/^(?:[5-9]|10)-/', $section))),
            'history' => collect($data['events'] ?? [])->map(fn ($event) => ['data' => (string) $event->created_at, 'osoba' => $event->user_name, 'akcja' => $event->action])->all()];
    }
}
