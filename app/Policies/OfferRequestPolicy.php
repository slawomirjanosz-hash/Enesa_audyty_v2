<?php

namespace App\Policies;

use App\Models\OfferRequest;
use App\Models\User;
use App\Policies\Concerns\HandlesStaffAccess;
use App\Services\AuditorAccessService;

class OfferRequestPolicy
{
    use HandlesStaffAccess;

    public function view(User $user, OfferRequest $offerRequest): bool
    {
        return app(AuditorAccessService::class)->canViewCompany($user, $offerRequest->company_id, 'can_view_offer_requests');
    }

    public function update(User $user, OfferRequest $offerRequest): bool { return $this->canModify($user); }
    public function delete(User $user, OfferRequest $offerRequest): bool { return $this->canModify($user); }
}