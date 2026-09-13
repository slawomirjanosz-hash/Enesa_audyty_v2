<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SuperadminSmsService
{
    public function required(User $user): bool
    {
        return config('security.sms.enabled') && $user->hasRole('superadmin');
    }

    public function verified(Request $request): bool
    {
        return $request->session()->get('sms_verified') === $request->user()->id.':'.$request->user()->session_version;
    }

    public function send(Request $request): void
    {
        $user = $request->user();
        $phone = preg_replace('/[^0-9]/', '', (string) config('security.sms.phone'));
        if (! config('security.sms.token') || ! preg_match('/^\d{10,15}$/', $phone)) {
            throw ValidationException::withMessages(['code' => 'Wysyłka SMS nie została skonfigurowana. Skontaktuj się z administratorem hostingu.']);
        }
        foreach (['sms-minute:'.$user->id => [1, 60], 'sms-hour:'.$user->id => [5, 3600]] as $key => [$limit, $seconds]) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                throw ValidationException::withMessages(['code' => 'Poczekaj przed kolejnym SMS-em. Kod jest ważny przez 5 minut.']);
            }
        }
        RateLimiter::hit('sms-minute:'.$user->id, 60);
        RateLimiter::hit('sms-hour:'.$user->id, 3600);
        $code = (string) random_int(100000, 999999);
        $challenge = Str::random(48);
        try {
            $response = Http::withToken(config('security.sms.token'))->asForm()->timeout(10)->post('https://api.smsapi.pl/sms.do', array_filter([
                'to' => $phone, 'from' => config('security.sms.sender'), 'message' => 'Kod logowania do aplikacji: '.$code.'. Wazny 5 minut. Nie udostepniaj go nikomu.', 'format' => 'json',
            ]));
            if (! $response->successful() || $response->json('error') || (int) $response->json('count') !== 1) {
                throw new \RuntimeException('SMS failed');
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages(['code' => 'Nie udało się wysłać SMS-a. Spróbuj później.']);
        }
        Cache::put('sms-challenge:'.$challenge, ['user_id' => $user->id, 'version' => (int) $user->session_version, 'hash' => Hash::make($code)], now()->addMinutes(5));
        if ($old = $request->session()->get('sms_challenge')) {
            Cache::forget('sms-challenge:'.$old);
        }
        $request->session()->put('sms_challenge', $challenge);
    }

    public function verify(Request $request, string $code): bool
    {
        $challenge = (string) $request->session()->get('sms_challenge');
        $attemptKey = 'sms-verify:'.$request->user()->id;
        if ($challenge === '' || RateLimiter::tooManyAttempts($attemptKey, 5)) {
            return false;
        }
        RateLimiter::hit($attemptKey, 300);

        return Cache::lock('sms-check:'.$challenge, 10)->block(3, function () use ($request, $challenge, $code, $attemptKey) {
            $data = Cache::get('sms-challenge:'.$challenge);
            if (! $data || $data['user_id'] !== $request->user()->id || $data['version'] !== (int) $request->user()->session_version || ! Hash::check($code, $data['hash'])) {
                return false;
            }
            Cache::forget('sms-challenge:'.$challenge);
            RateLimiter::clear($attemptKey);
            $request->session()->forget('sms_challenge');
            $request->session()->regenerate();
            $request->session()->put('sms_verified', $request->user()->id.':'.$request->user()->session_version);

            return true;
        });
    }
}
