<?php

namespace App\Services;

use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use App\Models\IsoStakeholderReview;
use App\Models\IsoSystemReview;
use Illuminate\Support\Collection;

class QuestionnairePendingActions
{
    /** Only receive audits already scoped to the viewer's company permissions. */
    public function forAudits(Collection $audits): array
    {
        if ($audits->isEmpty()) {
            return [];
        }
        $profiles = IsoPlantProfile::whereIn('audit_id', $audits->pluck('id'))->latestPerSite()
            ->get(['id', 'audit_id', 'site_id', 'status', 'client_approval']);
        $current = $profiles->keyBy(fn ($profile) => $profile->audit_id.':'.$profile->site_id);
        $actions = [];
        foreach ([IsoPlantProfile::class => '1', IsoFactorReview::class => '4-1', IsoStakeholderReview::class => '4-2', IsoSystemReview::class => null] as $model => $section) {
            $rows = $model === IsoPlantProfile::class ? $profiles : $model::whereIn('audit_id', $audits->pluck('id'))
                ->where('status', 'submitted')->whereNotNull('client_approval')->get(['audit_id', 'site_id', 'status', 'client_approval', ...($section === null ? ['section'] : [])]);
            foreach ($rows as $row) {
                $profile = $current->get($row->audit_id.':'.$row->site_id);
                if (! $profile || $row->status !== 'submitted' || ! $row->client_approval) {
                    continue;
                }
                $point = $section ?? $row->section;
                $route = match ($point) {
                    '1' => 'audits.plant-profile.show',
                    '4-1' => 'audits.factors.show',
                    '4-2' => 'audits.stakeholders.show',
                    default => 'audits.system.show',
                };
                $params = [$row->audit_id, $profile->id];
                if (in_array($point, ['4-3', '4-4'])) {
                    $params[] = $point;
                }
                $actions[$row->audit_id][] = ['section' => str_replace('-', '.', $point), 'url' => route($route, $params)];
            }
        }

        return $actions;
    }
}
