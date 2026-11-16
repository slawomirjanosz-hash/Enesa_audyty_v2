<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;
use App\Policies\Concerns\HandlesStaffAccess;
use App\Services\AuditorAccessService;

class OfferPolicy
{
    use HandlesStaffAccess;

    public function view(User $user, Offer $offer): bool
    {
        return app(AuditorAccessService::class)->canViewOfferRecord($user, $offer, 'can_view_offers');
    }

    public function viewPrices(User $user, Offer $offer): bool
    {
        return $this->view($user, $offer) && $offer->company_id !== null
            && app(AuditorAccessService::class)->canViewCompany($user, $offer->company_id, 'can_view_offer_prices');
    }

    public function update(User $user, Offer $offer): bool
    {
        return $this->view($user, $offer) && $this->canModify($user, 'offers.edit');
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $this->view($user, $offer) && $this->canModify($user, 'offers.delete');
    }
}
