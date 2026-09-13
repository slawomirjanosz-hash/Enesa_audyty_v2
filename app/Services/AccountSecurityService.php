<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountSecurityService
{
    public function checkInactivity(User $user): void
    {
        $last = $user->security_activity_at ?? $user->created_at;
        if ($user->is_active && ! $user->security_block_reason && $last && $last->lte(now()->subMonthsNoOverflow(2))) {
            $user->forceFill(['security_block_reason' => 'inactivity', 'security_blocked_at' => now(), 'session_version' => $user->session_version + 1, 'remember_token' => Str::random(60)])->save();
        }
    }

    public function blocked(User $user): bool
    {
        $this->checkInactivity($user);

        return ! $user->is_active || $user->security_block_reason !== null || ($user->login_locked_until?->isFuture() ?? false);
    }

    public function failed(User $user): void
    {
        DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $count = $locked->failed_login_at?->gt(now()->subMinutes(15)) ? $locked->failed_login_count + 1 : 1;
            $locked->forceFill(['failed_login_count' => $count, 'failed_login_at' => now()]);
            if ($count >= 10) {
                $locked->login_locked_until = now()->addMinutes(15);
            }
            $locked->save();
        });
    }

    public function revoke(User $user): void
    {
        $user->forceFill(['session_version' => $user->session_version + 1, 'remember_token' => Str::random(60)])->save();
    }

    public function unlock(User $user): void
    {
        $user->forceFill(['is_active' => true, 'security_block_reason' => null, 'security_blocked_at' => null, 'login_locked_until' => null, 'failed_login_count' => 0, 'failed_login_at' => null, 'security_activity_at' => now()])->save();
        $this->revoke($user);
    }
}
