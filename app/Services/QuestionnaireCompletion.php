<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\IsoContextReview;
use App\Models\IsoPlantProfile;

class QuestionnaireCompletion
{
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
        $profiles = IsoPlantProfile::where('audit_id', $audit->id)->orderByDesc('revision')->orderByDesc('id')->get(['id', 'site_id', 'revision', 'definition', 'answers'])->unique('site_id');
        $plants = $profiles->map(fn ($profile) => ['id' => $profile->id, 'name' => $profile->answers['site.name']['value'] ?? 'Zakład', 'progress' => $this->plant($profile->definition, $profile->answers)]);
        $review = IsoContextReview::where('audit_id', $audit->id)->where('year', now()->year)->first(['answers']);
        $result = ['plants' => $plants, 'context' => $this->fields(array_column(app(IsoContextLibrary::class)->questions(), 'kod'), $review?->answers['facts'] ?? [])];
        request()->attributes->set($cacheKey, $result);

        return $result;
    }
}
