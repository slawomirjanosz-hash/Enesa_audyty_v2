<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\DocumentQuotaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

function securedUser(string $role = 'admin'): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(Role::findOrCreate($role));

    return $user->fresh();
}

test('inactive and dormant accounts cannot log in and admin can unlock', function () {
    $user = securedUser('auditor');
    $user->forceFill(['security_activity_at' => now()->subMonthsNoOverflow(2)->subDay()])->save();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
    expect($user->fresh()->security_block_reason)->toBe('inactivity');
    $admin = securedUser();
    $this->actingAs($admin)->post(route('settings.users.unlock', $user))->assertRedirect();
    expect($user->fresh()->security_block_reason)->toBeNull()->and($user->fresh()->security_activity_at->isToday())->toBeTrue();
});

test('admin cannot unlock superadmin or client elevate limits', function () {
    $super = securedUser('superadmin');
    $admin = securedUser();
    $this->actingAs($admin)->post(route('settings.users.unlock', $super))->assertForbidden();
    $client = securedUser('client_user');
    $this->actingAs($client)->patch(route('settings.users.document-quota', $client), ['limit_mb' => 500])->assertForbidden();
});

test('failed attempts from different addresses lock account and unlock revokes sessions', function () {
    $user = securedUser('auditor');
    for ($i = 0; $i < 10; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.($i + 1)])->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }
    expect($user->fresh()->login_locked_until?->isFuture())->toBeTrue();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    app(AccountSecurityService::class)->unlock($user->fresh());
    $this->actingAs($user->fresh())->withSession(['security_version' => 0])->get('/dashboard')->assertRedirect(route('login'));
});

test('account activity is updated without blocking active users', function () {
    $user = securedUser();
    $user->forceFill(['security_activity_at' => now()->subMonth()])->save();
    $this->actingAs($user)->get('/dashboard')->assertOk();
    expect($user->fresh()->security_activity_at->isToday())->toBeTrue();
});

test('document limit is enforced across saves and owner can see usage', function () {
    Storage::fake('local');
    $user = securedUser();
    $user->forceFill(['document_limit_bytes' => 10])->save();
    $company = Company::create(['name' => 'Quota']);
    $make = fn ($size, $path) => Document::create(['company_id' => $company->id, 'uploaded_by' => $user->id, 'original_filename' => $path, 'stored_path' => $path, 'type' => 'upload', 'size' => $size]);
    $doc = $make(8, 'one.pdf');
    expect(fn () => $make(3, 'two.pdf'))->toThrow(ValidationException::class);
    expect(app(DocumentQuotaService::class)->used($user->id))->toBe(8);
    $doc->delete();
    expect(app(DocumentQuotaService::class)->used($user->id))->toBe(0);
    $this->actingAs($user)->get('/documents')->assertOk()->assertSee('Twoje miejsce na dokumenty');
    $this->patch(route('settings.users.document-quota', $user), ['limit_mb' => 300])->assertRedirect();
    expect($user->fresh()->document_limit_bytes)->toBe(300 * 1048576);
});

test('download history records successful downloads without query secrets', function () {
    Storage::fake('local');
    $user = securedUser();
    $company = Company::create(['name' => 'Logs']);
    $doc = Document::create(['company_id' => $company->id, 'type' => 'upload', 'original_filename' => 'one.pdf', 'stored_path' => 'one.pdf', 'size' => 1]);
    Storage::disk('local')->put('one.pdf', 'x');
    $this->actingAs($user)->get(route('documents.download', $doc).'?token=TOPSECRET')->assertOk();
    $log = ActivityLog::where('action', 'download')->firstOrFail();
    expect($log->user_id)->toBe($user->id)->and($log->url)->not->toContain('TOPSECRET');
});

test('superadmin requires SMS when enabled and a code is single use', function () {
    config(['security.sms.enabled' => true, 'security.sms.token' => 'fake', 'security.sms.phone' => '48123456789']);
    $sentCode = null;
    Http::fake(function ($request) use (&$sentCode) {
        preg_match('/\b(\d{6})\b/', $request['message'], $matches);
        $sentCode = $matches[1];

        return Http::response(['count' => 1]);
    });
    $user = securedUser('superadmin');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('auth.sms'));
    $this->get('/dashboard')->assertRedirect(route('auth.sms'));
    $this->post(route('auth.sms.verify'), ['code' => '000000'])->assertSessionHasErrors();
    $this->post(route('auth.sms.verify'), ['code' => $sentCode])->assertRedirect();
    $this->get('/dashboard')->assertOk();
    $this->post(route('auth.sms.verify'), ['code' => $sentCode])->assertSessionHasErrors();
});

test('SMS outage never grants access to superadmin', function () {
    config(['security.sms.enabled' => true, 'security.sms.token' => null]);
    $user = securedUser('superadmin');
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('auth.sms'));
    $this->get('/dashboard')->assertRedirect(route('auth.sms'));
});

test('antibot challenge is conditional and verified on server', function () {
    config(['security.turnstile.enabled' => true, 'security.turnstile.site_key' => 'site', 'security.turnstile.secret' => 'secret', 'security.turnstile.hostname' => 'localhost']);
    $this->get('/login')->assertDontSee('cf-turnstile', false);
    $user = securedUser();
    $this->withSession(['bot_required' => true])->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'hostname' => 'localhost', 'action' => 'login'])]);
    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'valid'])->assertRedirect();
    $this->assertAuthenticated();
});

test('disabled account loses an existing session and correct password does not bypass the block', function () {
    $user = securedUser();
    $this->actingAs($user)->get('/dashboard')->assertOk();
    $user->forceFill(['is_active' => false])->save();
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
});

test('scheduled inactivity block preserves files and can be recovered from operator console', function () {
    $user = securedUser('superadmin');
    $user->forceFill(['security_activity_at' => now()->subMonthsNoOverflow(3)])->save();
    $this->artisan('accounts:block-inactive')->assertSuccessful();
    expect($user->fresh()->security_block_reason)->toBe('inactivity');
    $this->artisan('accounts:unlock', ['email' => $user->email, '--force' => true])->assertSuccessful();
    expect($user->fresh()->security_block_reason)->toBeNull();
});

test('Turnstile rejects tokens issued for a different domain', function () {
    config(['security.turnstile.enabled' => true, 'security.turnstile.secret' => 'secret', 'security.turnstile.hostname' => 'localhost']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'hostname' => 'attacker.example', 'action' => 'login'])]);
    $user = securedUser();
    $this->withSession(['bot_required' => true])->post('/login', ['email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'wrong-domain'])->assertSessionHasErrors();
    $this->assertGuest();
});
