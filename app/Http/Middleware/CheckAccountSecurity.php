<?php

namespace App\Http\Middleware;

use App\Services\AccountSecurityService;
use App\Services\SuperadminSmsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAccountSecurity
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            $user->refresh();
            $security = app(AccountSecurityService::class);
            if ($security->blocked($user) || (int) $request->session()->get('security_version', 0) !== (int) $user->session_version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson() ? response()->json(['message' => 'Sesja wygasła lub konto jest zablokowane.'], 401)
                    : redirect()->route('login')->withErrors(['email' => 'Sesja wygasła lub konto jest zablokowane. Skontaktuj się z administratorem, jeśli nie możesz się zalogować.']);
            }
            $sms = app(SuperadminSmsService::class);
            if ($sms->required($user) && ! $sms->verified($request) && ! $request->routeIs('auth.sms', 'auth.sms.*', 'logout')) {
                return $request->expectsJson() ? response()->json(['message' => 'Wymagane potwierdzenie SMS.'], 403) : redirect()->route('auth.sms');
            }
            if (! $user->security_activity_at || $user->security_activity_at->lt(now()->subMinutes(5))) {
                $user->forceFill(['security_activity_at' => now(), 'last_seen_at' => now()])->saveQuietly();
            }
        }

        return $next($request);
    }
}
