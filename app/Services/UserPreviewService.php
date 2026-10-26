<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class UserPreviewService
{
    public function eligible(User $user): bool
    {
        return $user->is_active && ! $user->trashed() && ! $user->hasRole('superadmin')
            && ! (($user->security_activity_at ?? $user->created_at)?->lte(now()->subMonthsNoOverflow(2)) ?? false)
            && ! $user->security_block_reason && ! ($user->login_locked_until?->isFuture() ?? false);
    }

    public function finish(Request $request, string $reason): void
    {
        $state = $request->session()->pull('support_user_preview');
        if (! is_array($state)) {
            return;
        }
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'updated', 'auditable_type' => User::class, 'auditable_id' => $state['target_id'],
            'subject_label' => 'Zakończono podgląd serwisowy użytkownika',
            'changes' => ['support_preview' => ['actor_id' => $state['actor_id'], 'target_id' => $state['target_id'], 'reason' => $state['reason'], 'ended_by' => $reason]],
            'ip_address' => $request->ip(), 'route_name' => $request->route()?->getName(),
        ]);
        $request->session()->regenerate();
    }
}
