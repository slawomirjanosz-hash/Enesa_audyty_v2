<?php

namespace App\Console\Commands;

use App\Models\IsoPlantProfile;
use App\Services\IsoPlantCalculations;
use Illuminate\Console\Command;

/** Read-only inventory; never rewrites answers, approvals, documents or event history. */
class CheckIsoPlantEnergy extends Command
{
    protected $signature = 'iso:check-energy';

    protected $description = 'Report profiles affected by corrected energy conversion without modifying any data';

    public function handle(IsoPlantCalculations $calculator): int
    {
        $checked = 0;
        $issues = [];
        foreach (IsoPlantProfile::query()->orderBy('id')->cursor() as $profile) {
            if (($profile->definition['version'] ?? '') !== '2.0') {
                continue;
            }
            $checked++;
            $energy = $calculator->energy($profile->answers['FAKT_NOSNIKI']['value'] ?? [], (bool) ($profile->answers['FAKT_NOSNIKI']['unknown'] ?? false));
            $changed = $calculator->energyChanged($profile->definition, $profile->answers);
            if (! $changed && ! $energy['errors']) {
                continue;
            }
            $issues[] = ['profile_id' => $profile->id, 'audit_id' => $profile->audit_id, 'site_id' => $profile->site_id, 'revision' => $profile->revision,
                'status' => $profile->status, 'is_latest' => ! IsoPlantProfile::where('audit_id', $profile->audit_id)->where('site_id', $profile->site_id)->where('revision', '>', $profile->revision)->exists(), 'stored_tj' => $profile->answers['ZUZYCIE_TJ']['value'] ?? null, 'computed_tj' => $energy['value'],
                'changed' => $changed, 'row_errors' => array_values($energy['errors']),
                'has_client_approval' => (bool) $profile->client_approval, 'has_auditor_approval' => (bool) $profile->auditor_approval,
                'document_id' => $profile->document_id, 'elaboration_document_id' => $profile->elaboration_document_id];
        }
        $this->line(json_encode(['checked' => $checked, 'affected' => count($issues), 'profiles' => $issues], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
