<?php

namespace App\Services;

use App\Models\CrmOpportunity;
use App\Models\Offer;

class OfferCrmStageSynchronizer
{
    public function __construct(private readonly CrmActivityLogger $activityLogger) {}

    public function synchronize(Offer $offer, ?CrmOpportunity $opportunity = null): void
    {
        $opportunity ??= $offer->crmOpportunity()->first();

        if (! $opportunity) {
            return;
        }

        $nextStage = match ($offer->status) {
            'wygrana' => 'realization',
            'przegrana' => 'lost',
            'w_toku' => in_array($opportunity->stage, ['new_lead', 'contact'], true) ? 'offer' : null,
            default => null,
        };

        if (! $nextStage || $opportunity->stage === $nextStage) {
            return;
        }

        $previousStage = $opportunity->stage;
        $opportunity->update(['stage' => $nextStage]);
        $this->activityLogger->leadStageChanged($opportunity, $previousStage, $nextStage);
    }
}
