<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use OTPHP\TOTP;

class SuperadminTotpService
{
    public function required(User $user): bool
    {
        return (bool) config('security.totp.enabled') && $user->hasRole('superadmin');
    }

    public function verified(Request $request): bool
    {
        return $request->user()->two_factor_confirmed_at !== null
            && $request->session()->get('totp_verified') === $request->user()->id.':'.$request->user()->session_version;
    }

    public function markVerified(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->put('security_version', (int) $user->session_version);
        $request->session()->put('totp_verified', $user->id.':'.$user->session_version);
    }

    public function matchingStep(string $secret, string $code, ?int $lastStep): ?int
    {
        if (! preg_match('/^\d{6}$/D', $code)) {
            return null;
        }
        $otp = TOTP::createFromSecret($secret);
        $current = intdiv(now()->timestamp, 30);
        foreach ([$current, $current - 1, $current + 1] as $step) {
            if (($lastStep === null || $step > $lastStep) && hash_equals($otp->at($step * 30), $code)) {
                return $step;
            }
        }

        return null;
    }

    public function attempt(User $user): bool
    {
        $key = 'totp-attempt:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return false;
        }
        RateLimiter::hit($key, 300);

        return true;
    }

    public function verify(Request $request, string $code): bool
    {
        if (! $this->attempt($request->user())) {
            return false;
        }

        return DB::transaction(function () use ($request, $code) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            if (! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
                return false;
            }
            $step = $this->matchingStep($user->two_factor_secret, $code, $user->two_factor_last_step);
            if ($step !== null) {
                $user->forceFill(['two_factor_last_step' => $step])->saveQuietly();
            } else {
                $hash = hash('sha256', strtolower(trim($code)));
                $codes = $user->two_factor_recovery_codes ?? [];
                $index = array_search($hash, $codes, true);
                if ($index === false) {
                    return false;
                }
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();
            }
            RateLimiter::clear('totp-attempt:'.$user->id);
            $this->markVerified($request, $user);
            app(ActivityLogService::class)->recordAuthentication($user, 'login');

            return true;
        });
    }
}
