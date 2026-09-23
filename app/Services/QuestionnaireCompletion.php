<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\IsoContextReview;
use App\Models\IsoImplementationResponse;
use App\Models\IsoPlantProfile;
use Illuminate\Support\Collection;

class QuestionnaireCompletion
{
    /** Batch calculation for already access-scoped audit lists; no queries per card. */
    public function auditCards(Collection $audits): array
    {
        if ($audits->isEmpty()) {
            return [];
        }
        $audits->loadMissing(['manager', 'surveys.auditType']);
        $ids = $audits->modelKeys();
        $profiles = IsoPlantProfile::whereIn('audit_id', $ids)->latestPerSite()->get(['id', 'audit_id', 'site_id', 'definition', 'answers'])->groupBy('audit_id');
        $reviews = IsoContextReview::whereIn('audit_id', $ids)->where('year', now()->year)->get(['audit_id', 'answers'])->keyBy('audit_id');
        $responses = IsoImplementationResponse::whereIn('audit_id', $ids)->get()->groupBy('audit_id');
        $results = [];
        foreach ($audits as $audit) {
            $parts = [];
            if ($audit->surveys->contains(fn ($survey) => $survey->auditType?->slug === 'iso50001')) {
                $plants = ($profiles->get($audit->id) ?? collect())->unique('site_id');
                foreach ($plants as $profile) {
                    $parts[] = $this->plant($profile->definition, $profile->answers);
                }
                if ($plants->isEmpty()) {
                    $parts[] = $this->plant(app(IsoPlantQuestionnaire::class)->definition(), []);
                }
                $parts[] = $this->fields(array_column(app(IsoContextLibrary::class)->questions(), 'kod'), $reviews->get($audit->id)?->answers['facts'] ?? []);
                foreach (config('iso50001-workflows', []) as $section => $actions) {
                    // 4.1 is now covered by the context questionnaire, not the legacy form.
                    if ($section === '4-1') {
                        continue;
                    }
                    foreach ($actions as $key => $workflow) {
                        $response = ($responses->get($audit->id) ?? collect())->first(fn ($row) => $row->section_id === $section && $row->action_key === $key);
                        $parts[] = $this->fields(array_keys($workflow['fields']), $response?->answers ?? []);
                    }
                }
            }
            foreach ($audit->surveys as $survey) {
                if ($survey->auditType?->slug !== 'iso50001') {
                    $parts[] = $this->result($survey->status === 'completed' ? 1 : 0, 1);
                }
            }
            $results[$audit->id] = $this->result(array_sum(array_column($parts, 'answered')), array_sum(array_column($parts, 'total')));
        }

        return $results;
    }

    public function result(int $answered, int $total): array
    {
        return ['answered' => $answered, 'total' => $total, 'percent' => $total ? (int) floor(100 * $answered / $total) : 0];
    }

    public function fields(array $keys, array $answers): array
    {
        return $this->result(count(array_filter($keys, fn ($key) => isset($answers[$key]) && is_scalar($answers[$key]) && trim((string) $answers[$key]) !== '')), count($keys));
    }

    public function plant(array $definition, array $answers): array
    {
        $service = app(IsoPlantQuestionnaire::class);
        $answered = $total = 0;
        foreach ($service->questions($definition) as $question) {
            if (! $service->visible($question, $answers)) {
                continue;
            }
            $total++;
            $answer = $answers[$question['key']] ?? [];
            $value = $answer['value'] ?? null;
            $complete = ($answer['unknown'] ?? false) && ! $question['required'];
            if ($question['type'] === 'rows') {
                $complete = $complete || collect(is_array($value) ? $value : [])->contains(fn ($row) => is_array($row) && collect($row)->except('id')->contains(fn ($v) => is_scalar($v) && trim((string) $v) !== ''));
            } elseif ($question['type'] === 'multi') {
                $complete = is_array($value) && count(array_intersect($value, array_keys($question['options']))) > 0;
            } elseif ($question['type'] === 'select') {
                $complete = is_scalar($value) && array_key_exists($value, $question['options']);
            } else {
                $complete = $complete || (is_scalar($value) && trim((string) $value) !== '');
            }
            $answered += $complete ? 1 : 0;
        }

        return $this->result($answered, $total);
    }

    public function audit(Audit $audit): array
    {
        $cacheKey = 'questionnaire_completion_'.$audit->id;
        if (request()->attributes->has($cacheKey)) {
            return request()->attributes->get($cacheKey);
        }
        $profiles = IsoPlantProfile::where('audit_id', $audit->id)->latestPerSite()->get(['id', 'site_id', 'revision', 'definition', 'answers', 'status', 'client_approval', 'auditor_approval', 'document_id', 'client_changes']);
        $plants = $profiles->map(fn ($profile) => ['id' => $profile->id, 'name' => $profile->answers['site.name']['value'] ?? 'Zakład', 'progress' => $this->plant($profile->definition, $profile->answers), 'profile' => $profile]);
        $review = IsoContextReview::where('audit_id', $audit->id)->where('year', now()->year)->first(['answers']);
        $result = ['plants' => $plants, 'context' => $this->fields(array_column(app(IsoContextLibrary::class)->questions(), 'kod'), $review?->answers['facts'] ?? [])];
        request()->attributes->set($cacheKey, $result);

        return $result;
    }
}
