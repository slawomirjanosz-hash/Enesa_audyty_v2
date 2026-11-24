<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    // Laravel normally bypasses CSRF verification in tests; exercise the real failure path.
    $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
});

test('expired login form opens a fresh login page without authenticating or keeping submitted credentials', function () {
    $user = User::factory()->create();
    $response = $this->withSession(['_token' => 'current-session-token'])->post('/login', [
        '_token' => 'expired-form-token',
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(303)->assertRedirect(route('login'))
        ->assertSessionMissing('_old_input')->assertDontSee('Formularz czekał zbyt długo');
    $this->assertGuest();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $this->get('/login')->assertOk()->assertSee('name="_token"', false);
    $this->post('/login', ['_token' => session()->token(), 'email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('expired logout form also opens login and never replays a stale submission', function () {
    $this->withSession(['_token' => 'fresh-token'])->post('/logout', ['_token' => 'old-token'])
        ->assertStatus(303)->assertRedirect(route('login'));
    $this->get('/login')->assertOk();
});

test('JSON requests keep the expiry status so autosave can preserve unsaved work', function () {
    $this->withSession(['_token' => 'fresh-token'])->postJson('/login', [
        '_token' => 'old-token', 'email' => 'test@example.com', 'password' => 'secret',
    ])->assertStatus(419)->assertHeaderMissing('Location');
    $this->assertGuest();
});

test('invalid credentials with a valid token retain normal login validation', function () {
    $user = User::factory()->create();
    $this->withSession(['_token' => 'fresh-token'])->from('/login')->post('/login', [
        '_token' => 'fresh-token', 'email' => $user->email, 'password' => 'incorrect-password',
    ])->assertRedirect('/login')->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('login pages cannot be cached with stale session tokens', function () {
    $response = $this->get('/login')->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});
