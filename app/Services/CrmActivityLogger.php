<?php

namespace App\Services;

use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Models\Offer;

class CrmActivityLogger
{
    private const STAGE_LABELS = [
        'new_lead' => 'Nowy lead',
        'contact' => 'Kontakt',
        'offer' => 'Oferta',
        'negotiation' => 'Negocjacje',
        'realization' => 'Realizacja',
        'won' => 'Wygrana',
        'lost' => 'Przegrana',
        'rejected' => 'Odrzucona',
    ];

    private const OFFER_STATUS_LABELS = [
        'w_toku' => 'W toku',
        'wygrana' => 'Wygrana',
        'przegrana' => 'Przegrana',
        'zarchiwizowana' => 'Zarchiwizowana',
    ];

    public function leadCreated(CrmOpportunity $opportunity): void
    {
        $this->record($opportunity->company_id, 'lead_created', 'Utworzono lead: '.$opportunity->title, $opportunity);
    }

    public function leadStageChanged(CrmOpportunity $opportunity, string $from, string $to): void
    {
        $this->record(
            $opportunity->company_id,
            'lead_stage_changed',
            'Lead „'.$opportunity->title.'”: '.$this->stageLabel($from).' → '.$this->stageLabel($to),
            $opportunity,
            null,
            ['from' => $from, 'to' => $to]
        );
    }

    public function offerLinked(Offer $offer, CrmOpportunity $opportunity): void
    {
        $this->record(
            $offer->company_id,
            'offer_linked',
            'Przypięto ofertę '.$offer->fullNumber().' do leada „'.$opportunity->title.'”.',
            $opportunity,
            $offer
        );
    }

    public function offerStatusChanged(Offer $offer, string $from, string $to): void
    {
        if (! $offer->crm_opportunity_id) {
            return;
        }

        $this->record(
            $offer->company_id,
            'offer_status_changed',
            'Oferta '.$offer->fullNumber().': '.$this->offerStatusLabel($from).' → '.$this->offerStatusLabel($to),
            $offer->crmOpportunity,
            $offer,
            ['from' => $from, 'to' => $to]
        );
    }

    private function record(?int $companyId, string $type, string $description, ?CrmOpportunity $opportunity = null, ?Offer $offer = null, array $metadata = []): void
    {
        if (! $companyId) {
            return;
        }

        CrmActivity::create([
            'company_id' => $companyId,
            'crm_opportunity_id' => $opportunity?->id,
            'offer_id' => $offer?->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function stageLabel(string $stage): string
    {
        return self::STAGE_LABELS[$stage] ?? $stage;
    }

    private function offerStatusLabel(string $status): string
    {
        return self::OFFER_STATUS_LABELS[$status] ?? $status;
    }
}
