<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\DocumentQuotaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
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

test('admin cannot unlock superadmin and removed quota endpoint cannot change limits', function () {
    $super = securedUser('superadmin');
    $admin = securedUser();
    $this->actingAs($admin)->post(route('settings.users.unlock', $super))->assertForbidden();
    $client = securedUser('client_user');
    $this->actingAs($client)->patch('/settings/users/'.$client->id.'/document-quota', ['limit_mb' => 500])->assertNotFound();
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

test('legacy document limit no longer restricts saves and usage follows retained files', function () {
    Storage::fake('local');
    $user = securedUser();
    $user->forceFill(['document_limit_bytes' => 10])->save();
    $company = Company::create(['name' => 'Quota']);
    $make = fn ($size, $path) => Document::create(['company_id' => $company->id, 'uploaded_by' => $user->id, 'original_filename' => $path, 'stored_path' => $path, 'type' => 'upload', 'size' => $size]);
    $doc = $make(8, 'one.pdf');
    $make(3, 'two.pdf');
    expect(app(DocumentQuotaService::class)->used($user->id))->toBe(11);
    $doc->delete();
    expect(app(DocumentQuotaService::class)->used($user->id))->toBe(3);
    $this->actingAs($user)->get('/documents')->assertOk()->assertSee('Twoje pliki:')->assertDontSee('Twoje miejsce na dokumenty')->assertDontSee('Zapisz limit');
    $this->get(route('settings.users.index'))->assertOk()
        ->assertSee('Dane na serwerze')->assertSee('Ostatnia aktywność')
        ->assertSee('data-sort-value="3"', false)->assertSee('table-sort.js')
        ->assertDontSee('Miejsce / limit')->assertDontSee('Zapisz limit');
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

test('usage and activity are visible in users table but client sidebar only shows own usage', function () {
    $admin = securedUser();
    $client = securedUser('client_admin');
    $client->forceFill(['security_activity_at' => now()->subDay()])->save();
    $company = Company::create(['name' => 'Storage client', 'company_type' => 'client', 'status' => 'active']);
    $client->companies()->attach($company);
    foreach ([[$admin, 4096], [$client, 2048]] as [$owner, $size]) {
        Document::create(['company_id' => $company->id, 'uploaded_by' => $owner->id, 'original_filename' => 'file.pdf', 'stored_path' => 'file-'.$owner->id.'.pdf', 'type' => 'upload', 'size' => $size]);
    }
    $usage = app(DocumentQuotaService::class)->usedMany([$admin->id, $client->id]);
    expect($usage->get($admin->id))->toBe(4096)->and($usage->get($client->id))->toBe(2048);
    $this->actingAs($admin)->get(route('settings.users.index'))->assertOk()
        ->assertSee('data-sort-value="4096"', false)->assertSee('data-sort-value="2048"', false)
        ->assertSee($client->security_activity_at->format('d.m.Y H:i'))
        ->assertDontSee('Limit domyślny')->assertDontSee('Zapisz limit');
    $response = $this->actingAs($client)->get(route('client.dashboard'))->assertOk();
    preg_match('/class="user-storage-usage"[^>]*>(.*?)<\/div>/s', $response->getContent(), $matches);
    expect($matches[1] ?? '')->toContain(Document::formatBytes(2048))->not->toContain(Document::formatBytes(4096));
    $this->get(route('settings.users.index'))->assertForbidden();
});

test('unlock action in users table preserves administrative permission boundary', function () {
    $admin = securedUser();
    $blocked = securedUser('auditor');
    $blocked->forceFill(['is_active' => false])->save();
    $super = securedUser('superadmin');
    $super->forceFill(['is_active' => false])->save();
    $this->actingAs($admin)->get(route('settings.users.index'))->assertOk()
        ->assertSee('aria-label="Odblokuj konto: '.$blocked->name.'"', false)
        ->assertDontSee('aria-label="Odblokuj konto: '.$super->name.'"', false);
    $reader = securedUser('auditor');
    Permission::findOrCreate('settings.users.view');
    $reader->givePermissionTo('settings.users.view');
    $this->actingAs($reader)->get(route('settings.users.index'))->assertOk()->assertDontSee('Odblokuj konto');
    $this->post(route('settings.users.unlock', $blocked))->assertForbidden();
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
