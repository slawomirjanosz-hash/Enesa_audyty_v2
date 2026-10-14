<?php

use App\Mail\ConfigurationTestMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['mail.default' => 'smtp', 'mail.from.address' => 'app@test.invalid']);
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole(Role::findOrCreate('superadmin'));
});

test('company settings offer a test sent only to the authenticated administrator', function () {
    Mail::fake();
    $this->actingAs($this->administrator)->get(route('settings.company'))
        ->assertOk()->assertSee('Wyślij wiadomość testową');
    $this->postJson(route('settings.company.test-mail'), ['email' => 'other@test.invalid'])
        ->assertOk()->assertJsonPath('message', fn ($message) => str_contains($message, $this->administrator->email));
    Mail::assertSent(ConfigurationTestMail::class, fn ($mail) => $mail->hasTo($this->administrator->email) && ! $mail->hasTo('other@test.invalid'));
    $this->postJson(route('settings.company.test-mail'))->assertStatus(429);
    Mail::assertSentCount(1);
});

test('test mail rejects non-delivering configuration', function () {
    Mail::fake();
    config(['mail.default' => 'log']);
    $this->actingAs($this->administrator)->postJson(route('settings.company.test-mail'))->assertStatus(422);
    Mail::assertNothingSent();
});

test('test mail reports provider failure without exposing secrets', function () {
    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('secret-api-key'));
    $this->actingAs($this->administrator)->postJson(route('settings.company.test-mail'))
        ->assertStatus(502)->assertDontSee('secret-api-key');
});

test('staff and customers cannot send configuration tests', function () {
    Mail::fake();
    foreach (['auditor', 'client_user', 'admin'] as $role) {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role));
        $this->actingAs($user)->postJson(route('settings.company.test-mail'))->assertForbidden();
    }
    Mail::assertNothingSent();
});

test('test mail is readable and identifies the company', function () {
    expect((new ConfigurationTestMail('PRINŻ CIESZYN'))->render())
        ->toContain('PRINŻ CIESZYN', 'wiadomość testowa');
});
