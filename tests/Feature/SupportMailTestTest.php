<?php

use App\Http\Controllers\SupportMailTestController;
use App\Mail\ConfigurationTestMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

test('support mail link requires an unexpired valid signature', function () {
    Mail::fake();
    $this->get('/support/mail-test')->assertForbidden();
    $this->post('/support/mail-test')->assertForbidden();
    $this->get(URL::temporarySignedRoute('support.mail-test', now()->subMinute()))->assertForbidden();
    $this->get(URL::temporarySignedRoute('support.mail-test', now()->addHour()).'&email=other@example.com')->assertForbidden();
    Mail::assertNothingSent();
});

test('support link is read only until explicitly submitted and locks recipient and limits sends', function () {
    Mail::fake();
    config(['mail.default' => 'smtp', 'mail.from.address' => 'test@pri.cieszyn.pl', 'mail.mailers.smtp.host' => 'localhost']);
    $url = URL::temporarySignedRoute('support.mail-test', now()->addHours(48));
    $this->get($url)->assertOk()->assertSee(SupportMailTestController::RECIPIENT)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    Mail::assertNothingSent();
    $this->post($url, ['email' => 'unwanted@example.com'])->assertRedirect($url);
    Mail::assertSent(ConfigurationTestMail::class, fn ($mail) => $mail->hasTo(SupportMailTestController::RECIPIENT) && ! $mail->hasTo('unwanted@example.com'));
    $this->post($url)->assertStatus(429);
    Mail::assertSentCount(1);
});

test('support mail link has a total limit independent of visitor IP', function () {
    Mail::fake();
    $url = URL::temporarySignedRoute('support.mail-test', now()->addHours(48));
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $key = 'support-mail-test:'.hash('sha256', $query['signature']).':total';
    for ($i = 0; $i < 20; $i++) {
        RateLimiter::hit($key, 48 * 3600);
    }
    $this->post($url)->assertStatus(429);
    Mail::assertNothingSent();
});
