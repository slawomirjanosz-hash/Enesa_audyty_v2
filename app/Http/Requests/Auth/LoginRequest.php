<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\LoginBotProtection;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        app(LoginBotProtection::class)->verify($this);

        $user = User::where('email', $this->string('email')->trim()->value())->first();
        if ($user && app(AccountSecurityService::class)->blocked($user)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages(['email' => 'Nie można się zalogować. Sprawdź dane lub skontaktuj się z administratorem.']);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            if ($user) {
                app(AccountSecurityService::class)->failed($user);
            }
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $this->session()->forget('bot_required');
        $this->user()->forceFill(['failed_login_count' => 0, 'failed_login_at' => null, 'login_locked_until' => null, 'security_activity_at' => now(), 'last_seen_at' => now()])->save();
        $this->session()->put('security_version', (int) $this->user()->session_version);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $version = User::where('email', $this->string('email')->trim()->value())->value('session_version') ?? 0;

        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip().'|'.$version);
    }
}
