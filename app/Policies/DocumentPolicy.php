<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\HandlesStaffAccess;
use App\Services\AuditorAccessService;

class DocumentPolicy
{
    use HandlesStaffAccess;

    public function view(User $user, Document $document): bool
    {
        return app(AuditorAccessService::class)->canViewDocument($user, $document);
    }

    public function update(User $user, Document $document): bool { return $this->canModify($user, 'documents.upload'); }
    public function delete(User $user, Document $document): bool { return $this->canModify($user, 'documents.delete'); }
}
