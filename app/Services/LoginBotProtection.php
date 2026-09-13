<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginBotProtection
{
    public function required(Request $request): bool
    {
        return config('security.turnstile.enabled') && (RateLimiter::attempts('login-ip:'.$request->ip()) >= 3 || $request->session()->get('bot_required', false));
    }

    public function verify(Request $request): void
    {
        $key = 'login-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            throw ValidationException::withMessages(['email' => 'Zbyt wiele prób. Spróbuj ponownie za kilka minut.']);
        }
        $required = $this->required($request);
        RateLimiter::hit($key, 300);
        if ($required) {
            $request->session()->put('bot_required', true);
            $valid = false;
            if (config('security.turnstile.secret') && $request->filled('cf-turnstile-response')) {
                try {
                    $response = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                        'secret' => config('security.turnstile.secret'), 'response' => $request->input('cf-turnstile-response'), 'remoteip' => $request->ip(),
                    ]);
                    $valid = $response->successful() && $response->json('success') === true && $response->json('hostname') === config('security.turnstile.hostname') && $response->json('action') === 'login';
                } catch (\Throwable) {
                    $valid = false;
                }
            }
            if (! $valid) {
                throw ValidationException::withMessages(['email' => 'Potwierdź weryfikację bezpieczeństwa i spróbuj ponownie.']);
            }
        }
    }
}
