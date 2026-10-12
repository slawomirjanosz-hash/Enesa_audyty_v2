<?php

namespace App\Http\Controllers;

use App\Models\AuditType;
use App\Models\IsoPlantProfile;
use App\Models\IsoSystemReview;
use App\Services\IsoFactorQuestionnaire;
use App\Services\IsoPlantQuestionnaire;
use App\Services\IsoStakeholderQuestionnaire;
use App\Services\IsoSystemQuestionnaire;
use Illuminate\Support\Arr;

class IsoQuestionnairePreviewController extends Controller
{
    public function show(AuditType $auditType, string $section)
    {
        $titles = ['intro' => '1 Wstęp ISO — Profil zakładu', '4-1' => '4.1 Kontekst organizacji', '4-2' => '4.2 Strony zainteresowane'] + IsoSystemReview::TITLES;
        uksort($titles, 'strnatcmp');
        $titles = ['intro' => $titles['intro']] + $titles;
        abort_unless($auditType->slug === 'iso50001' && isset($titles[$section]), 404);

        // Never load a customer's audit or persist preview answers.
        $plant = app(IsoPlantQuestionnaire::class);
        $profile = new IsoPlantProfile(['definition' => $plant->definition(), 'answers' => [], 'status' => 'editing', 'as_of_date' => today(), 'lock_version' => 0]);
        $review = new IsoSystemReview(['status' => 'editing', 'answers' => [], 'lock_version' => 0]);
        $data = compact('auditType', 'section', 'profile', 'review', 'titles') + ['title' => $titles[$section], 'client' => true, 'editable' => true, 'questionnairePreview' => true, 'answers' => [], 'input' => [], 'display' => [], 'changed' => fn () => false, 'value' => fn ($path, $fallback = '') => is_scalar($fallback) ? (string) $fallback : '', 'stale' => false];
        if ($section === 'intro') {
            $data += ['questionnaire' => $plant, 'kind' => 'plant-profile', 'formId' => 'plant-form', 'script' => 'iso-plant-profile.js'];
        } elseif ($section === '4-1') {
            $questionnaire = app(IsoFactorQuestionnaire::class);
            $factors = array_map(fn ($row) => array_replace($row, ['visible' => true]), $questionnaire->factors($plant->facts($profile->definition, []), []));
            $data += compact('questionnaire', 'factors') + ['kind' => 'factors', 'formId' => 'factor-form', 'script' => 'iso-factors.js'];
        } elseif ($section === '4-2') {
            $questionnaire = app(IsoStakeholderQuestionnaire::class);
            $parties = array_map(fn ($row) => array_replace($row, ['visible' => true]), $questionnaire->parties($profile, null));
            $data += compact('questionnaire', 'parties') + ['kind' => 'stakeholders', 'formId' => 'stakeholder-form', 'script' => 'iso-stakeholders.js'];
        } else {
            $service = app(IsoSystemQuestionnaire::class);
            $sources = ['facts' => [], 'approved' => [], 'published' => [], 'factors' => [], 'parties' => [], 'upstream' => [], 'scope' => [], 'cards' => [], 'ready' => false];
            $answers = $service->seed($section, $sources);
            $sections = $service->sections($section, $answers, $sources);
            foreach ($sections as $group) {
                if (isset($group['repeater']) && empty($answers[$group['repeater']])) {
                    $answers[$group['repeater']] = [[]];
                }
                if (isset($group['tree'])) {
                    Arr::set($answers, $group['root'], $this->blankTree($group['tree']));
                }
            }
            $data = array_replace($data, compact('service', 'sources', 'answers', 'sections'), ['kind' => 'system', 'formId' => 'system-form', 'script' => 'iso-system.js']);
        }

        return view('audit-types.questionnaire-preview', $data);
    }

    private function blankTree(array $node): mixed
    {
        if (in_array($node['type'], ['list', 'object'], true)) {
            $row = array_map(fn ($child) => $this->blankTree($child), $node['children']);

            return $node['type'] === 'list' ? [$row] : $row;
        }

        return null;
    }
}
