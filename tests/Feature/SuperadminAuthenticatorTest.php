<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\SuperadminTotpService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use OTPHP\TOTP;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['security.totp.enabled' => true, 'security.turnstile.enabled' => false]);
});

function authenticatorUser(string $role = 'superadmin', bool $configured = false): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(Role::findOrCreate($role));
    if ($configured) {
        $user->forceFill(['two_factor_secret' => TOTP::create()->getSecret(), 'two_factor_confirmed_at' => now()])->save();
    }

    return $user->fresh();
}

test('only superadmin is gated including existing and remembered sessions and SMS cannot bypass TOTP', function () {
    Http::fake();
    config(['security.sms.enabled' => true]);
    $user = authenticatorUser();
    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])->assertRedirect(route('auth.totp'));
    expect(ActivityLog::where('user_id', $user->id)->where('action', 'login')->exists())->toBeFalse();
    $this->get('/dashboard')->assertRedirect(route('auth.totp'));
    $this->getJson('/dashboard')->assertForbidden();
    $this->get(route('auth.sms'))->assertRedirect(route('auth.totp'));
    $this->withSession(['sms_verified' => $user->id.':0'])->get('/dashboard')->assertRedirect(route('auth.totp'));
    Http::assertNothingSent();
    $admin = authenticatorUser('admin');
    $this->actingAs($admin)->get('/dashboard')->assertOk();
    $this->get(route('auth.totp'))->assertForbidden();
    $this->post(route('auth.totp.begin'), ['password' => 'password'])->assertForbidden();
    $this->actingAs(authenticatorUser('client_user'))->get(route('auth.totp'))->assertForbidden();
});

test('enrollment needs password and valid code then encrypts secret and reveals recovery codes once', function () {
    $user = authenticatorUser();
    $this->actingAs($user)->get(route('auth.totp'))->assertOk()->assertSee('Aktualne hasło');
    $this->post(route('auth.totp.begin'), ['password' => 'wrong'])->assertSessionHasErrors('password');
    expect(session('totp_pending'))->toBeNull();
    $this->post(route('auth.totp.begin'), ['password' => 'password'])->assertRedirect(route('auth.totp'));
    $pending = session('totp_pending');
    $secret = decrypt($pending['secret']);
    expect($pending['secret'])->not->toBe($secret);
    $this->get(route('auth.totp'))->assertOk()->assertSee('data:image/svg+xml;base64,', false);
    $this->post(route('auth.totp.confirm'), ['code' => 'bad'])->assertSessionHasErrors('code');
    $this->post(route('auth.totp.confirm'), ['code' => TOTP::createFromSecret($secret)->at(now()->timestamp)])
        ->assertRedirect(route('auth.totp.recovery'));
    $codes = decrypt(session('totp_recovery_display'));
    expect($codes)->toHaveCount(8);
    $fresh = $user->fresh();
    expect($fresh->two_factor_secret)->toBe($secret)
        ->and(DB::table('users')->where('id', $user->id)->value('two_factor_secret'))->not->toBe($secret)
        ->and($fresh->two_factor_recovery_codes)->toContain(hash('sha256', $codes[0]))
        ->and($fresh->toArray())->not->toHaveKey('two_factor_secret')
        ->and($fresh->toArray())->not->toHaveKey('two_factor_recovery_codes')
        ->and($fresh->session_version)->toBe(1);
    $this->get(route('auth.totp.recovery'))->assertOk()->assertSee($codes[0]);
    $this->get(route('auth.totp.recovery'))->assertOk()->assertDontSee($codes[0]);
    $this->get('/dashboard')->assertOk();
    expect(ActivityLog::all()->toJson())->not->toContain($secret)->not->toContain($codes[0]);
    $this->post(route('auth.totp.begin'), ['password' => 'password'])->assertForbidden();
});

test('TOTP permits current code once and accepts next time step after a new login', function () {
    $user = authenticatorUser(configured: true);
    $code = TOTP::createFromSecret($user->two_factor_secret)->at(now()->timestamp);
    $this->actingAs($user)->post(route('auth.totp.verify'), ['code' => $code])->assertRedirect();
    $this->get('/dashboard')->assertOk();
    $this->post('/logout');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('auth.totp'));
    $this->post(route('auth.totp.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->get('/dashboard')->assertRedirect(route('auth.totp'));
    $this->travel(31)->seconds();
    $next = TOTP::createFromSecret($user->two_factor_secret)->at(now()->timestamp);
    $this->post(route('auth.totp.verify'), ['code' => $next])->assertRedirect();
    $this->get('/dashboard')->assertOk();
});

test('recovery code is one use and cannot be used for a different user', function () {
    $user = authenticatorUser(configured: true);
    $code = '12345678-abcdef12-12345678-abcdef12';
    $user->forceFill(['two_factor_recovery_codes' => [hash('sha256', $code)]])->saveQuietly();
    $other = authenticatorUser(configured: true);
    $this->actingAs($other)->post(route('auth.totp.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->actingAs($user)->post(route('auth.totp.verify'), ['code' => $code])->assertRedirect();
    expect($user->fresh()->two_factor_recovery_codes)->toBe([]);
    $this->post('/logout');
    $this->actingAs($user->fresh())->post(route('auth.totp.verify'), ['code' => $code])->assertSessionHasErrors('code');
});

test('rate limit for authenticator spans IP addresses and expires', function () {
    $user = authenticatorUser(configured: true);
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '10.2.0.'.($i + 1)])
            ->post(route('auth.totp.verify'), ['code' => 'invalid'])->assertSessionHasErrors('code');
    }
    $code = TOTP::createFromSecret($user->two_factor_secret)->at(now()->timestamp);
    $this->post(route('auth.totp.verify'), ['code' => $code])->assertSessionHasErrors('code');
    expect(RateLimiter::tooManyAttempts('totp-attempt:'.$user->id, 5))->toBeTrue();
    $this->travel(301)->seconds();
    $this->post(route('auth.totp.verify'), ['code' => TOTP::createFromSecret($user->two_factor_secret)->at(now()->timestamp)])->assertRedirect();
    $this->get('/dashboard')->assertOk();
});

test('expired enrollment and enrollment from another account are rejected', function () {
    $user = authenticatorUser();
    $this->actingAs($user)->post(route('auth.totp.begin'), ['password' => 'password']);
    $pending = session('totp_pending');
    $this->travel(601)->seconds();
    $code = TOTP::createFromSecret(decrypt($pending['secret']))->at(now()->timestamp);
    $this->post(route('auth.totp.confirm'), ['code' => $code])->assertSessionHasErrors('code');
    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
    $pending['expires'] = now()->addMinutes(10)->timestamp;
    $other = authenticatorUser();
    $this->actingAs($other)->withSession(['totp_pending' => $pending])->post(route('auth.totp.confirm'), ['code' => $code])->assertSessionHasErrors('code');
    expect($other->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('operator reset revokes sessions and requires enrollment again without changing password', function () {
    $user = authenticatorUser(configured: true);
    $password = $user->password;
    $this->artisan('accounts:reset-authenticator', ['email' => $user->email, '--force' => true])->assertSuccessful();
    $fresh = $user->fresh();
    expect($fresh->two_factor_secret)->toBeNull()->and($fresh->two_factor_confirmed_at)->toBeNull()
        ->and($fresh->password)->toBe($password)->and($fresh->session_version)->toBe(1);
    $this->actingAs($fresh)->withSession(['security_version' => 0, 'totp_verified' => $user->id.':0'])->get('/dashboard')->assertRedirect(route('login'));
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('auth.totp'));
    expect(ActivityLog::where('route_name', 'console.accounts.reset-authenticator')->exists())->toBeTrue();
});

test('TOTP matches RFC 6238 time vector and rejects old or malformed codes', function () {
    $this->travelTo(Carbon::createFromTimestampUTC(59));
    $service = app(SuperadminTotpService::class);
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    expect($service->matchingStep($secret, '287082', null))->toBe(1)
        ->and($service->matchingStep($secret, '287082', 1))->toBeNull()
        ->and($service->matchingStep($secret, '287082x', null))->toBeNull();
});
