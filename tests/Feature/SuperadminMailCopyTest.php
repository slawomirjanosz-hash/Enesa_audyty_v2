<?php

use App\Mail\UserAccountCreated;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

test('all mailers preserve recipients and add an invisible superadmin envelope recipient', function () {
    config(['mail.mailers.secondary' => ['transport' => 'array']]);
    $manager = new MailManager(app());
    foreach (['array', 'secondary'] as $name) {
        $mailer = $manager->mailer($name);
        $mailer->raw('Test wiadomości', function ($message) {
            $message->to('user@example.test')->cc('team@example.test')->bcc('existing@example.test')->subject('Test');
        });
        $sent = $mailer->getSymfonyTransport()->messages()->last();
        $email = $sent->getOriginalMessage();
        expect(array_map(fn ($a) => $a->getAddress(), $email->getTo()))->toBe(['user@example.test']);
        expect(array_map(fn ($a) => $a->getAddress(), $email->getCc()))->toBe(['team@example.test']);
        expect(array_map(fn ($a) => $a->getAddress(), $email->getBcc()))->toBe(['existing@example.test', 'proximalumine@gmail.com']);
        expect(array_map(fn ($a) => $a->getAddress(), $sent->getEnvelope()->getRecipients()))->toContain('proximalumine@gmail.com');
        expect($sent->toString())->not->toContain('proximalumine@gmail.com');
    }
});

test('superadmin is never duplicated in to cc or bcc including mixed case', function (string $header) {
    $email = (new Email)->from('sender@example.test')->to('user@example.test')->text('Test');
    $email->$header(new Address('ProximaLumine@gmail.com'));
    Event::dispatch(new MessageSending($email));
    Event::dispatch(new MessageSending($email));
    $recipients = array_merge($email->getTo(), $email->getCc(), $email->getBcc());
    expect(array_filter($recipients, fn ($a) => strtolower($a->getAddress()) === 'proximalumine@gmail.com'))->toHaveCount(1);
})->with(['addTo', 'addCc', 'addBcc']);

test('queued credential mail and password reset notifications also receive a copy without extra messages', function () {
    config(['mail.default' => 'array', 'queue.default' => 'sync']);
    $mailer = app('mail.manager')->mailer('array');
    $mailer->to('account@example.test')->queue(new UserAccountCreated('Test', 'account@example.test', 'FakeTest123!', 'Firma testowa', route('login')));
    $user = User::factory()->create();
    $user->notify(new ResetPassword('test-token'));
    $messages = $mailer->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(2);
    foreach ($messages as $sent) {
        expect(array_map(fn ($a) => $a->getAddress(), $sent->getEnvelope()->getRecipients()))->toContain('proximalumine@gmail.com');
        expect($sent->toString())->not->toContain('Bcc:');
    }
});
