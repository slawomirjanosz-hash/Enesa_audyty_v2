<?php

namespace App\Services\Enms;

use Illuminate\Support\Facades\DB;

final class LeadershipProgress
{
    /** Audit ids must already be scoped by the existing dashboard access checks. */
    public function forAudits(array $auditIds): array
    {
        $auditIds = Rollout::auditIds($auditIds);
        if (! $auditIds) {
            return [];
        }
        $requirements = array_keys(array_filter(app(LeadershipLibrary::class)->requirements(), fn ($r) => $r['normative']));
        $rows = DB::table('enms_workspaces as w')->join('enms_records as r', 'r.workspace_id', '=', 'w.id')
            ->join('enms_record_versions as v', function ($join) {
                $join->on('v.record_id', '=', 'r.id')->on('v.version', '=', 'r.current_version');
            })->whereIn('w.audit_id', $auditIds)->where('r.kind', 'declaration')
            ->get(['w.audit_id', 'w.site_id', 'v.payload']);
        $progress = [];
        foreach ($rows as $row) {
            $key = $row->audit_id.':'.$row->site_id;
            $progress[$key] ??= ['answered' => 0, 'total' => count($requirements), 'percent' => 0];
            $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
            if (in_array($payload['requirement_id'], $requirements, true) && filled($payload['stan'] ?? null)) {
                $progress[$key]['answered']++;
                $progress[$key]['percent'] = (int) floor(100 * $progress[$key]['answered'] / $progress[$key]['total']);
            }
        }

        return $progress;
    }
}
