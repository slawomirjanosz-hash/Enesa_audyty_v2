<?php

namespace App\Services;

use App\Models\IsoFactorReview;
use App\Models\IsoPlantProfile;
use App\Models\IsoStakeholderReview;
use App\Models\IsoSystemReview;
use App\Services\Enms\LeadershipWorkflow;
use App\Services\Enms\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuestionnairePendingActions
{
    /** Client notifications use the same company membership checks as the questionnaire. */
    public function enmsForClient(Collection $audits): array
    {
        $actions = [];
        if (! auth()->check() || $audits->isEmpty()) {
            return $actions;
        }
        $ids = DB::table('enms_workspaces')->whereIn('audit_id', $audits->pluck('id'))->pluck('audit_id');
        $service = app(LeadershipWorkflow::class);
        foreach (IsoPlantProfile::whereIn('audit_id', $ids)->latestPerSite()->get() as $profile) {
            $audit = $audits->firstWhere('id', $profile->audit_id);
            $w = Workspace::for(auth()->user(), $audit, $profile, true);
            $status = $service->status($w);
            $questions = array_filter($w->records('question'), fn ($q) => ($q['payload']['state'] ?? '') === 'open' && ! $service->hasCurrentAnswer($w, $q));
            if ($status['changed'] || $questions) {
                $actions[$audit->id][] = ['label' => $questions ? '5.1: odpowiedz na pytania audytora' : '5.1: sprawdź zmienione dane i zatwierdź ankietę', 'url' => route('client.audits.enms.show', [$audit, $profile, 'tab' => 'spojnosc'])];
            }
        }

        return $actions;
    }

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
                if (isset(IsoSystemReview::TITLES[$point])) {
                    $params[] = $point;
                }
                $actions[$row->audit_id][] = ['section' => str_replace('-', '.', $point), 'url' => route($route, $params)];
            }
        }

        $enmsAuditIds = DB::table('enms_workspaces')->whereIn('audit_id', $audits->pluck('id'))->pluck('audit_id');
        if (auth()->check() && $enmsAuditIds->isNotEmpty()) {
            $service = app(LeadershipWorkflow::class);
            foreach (IsoPlantProfile::whereIn('audit_id', $enmsAuditIds)->latestPerSite()->get() as $profile) {
                $audit = $audits->firstWhere('id', $profile->audit_id);
                if (! $audit) {
                    continue;
                }
                $workspace = Workspace::for(auth()->user(), $audit, $profile, false);
                $status = $service->status($workspace);
                if ($status['submitted'] && ! $status['assessed']) {
                    $actions[$audit->id][] = ['section' => '5.1 — nowy model', 'url' => route('audits.enms.show', [$audit, $profile, 'tab' => 'ocena'])];
                }
            }
        }

        return $actions;
    }
}
