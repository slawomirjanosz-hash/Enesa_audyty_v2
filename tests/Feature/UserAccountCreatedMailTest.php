<?php

use App\Mail\UserAccountCreated;
use App\Models\ActivityLog;
use App\Models\CompanySettings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('auditor');
    Mail::fake();
    config(['mail.default' => 'smtp', 'mail.from.address' => 'accounts@test.invalid']);
    CompanySettings::create(['name' => 'PRINŻ — test']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);
});

function newStaffAccount(array $changes = []): array
{
    return $changes + ['name' => 'Nowy Pracownik', 'email' => 'new-staff@example.test', 'role' => 'auditor', 'password' => 'TestoweHaslo123!'];
}

test('new user mail is opt in and its omission or refusal never sends', function ($choice) {
    $data = newStaffAccount();
    if ($choice !== null) {
        $data['send_email'] = $choice;
    }
    $this->post(route('settings.users.store'), $data)->assertSessionHas('success')->assertSessionMissing('new_user_credentials');
    Mail::assertNothingSent();
    expect(Hash::check($data['password'], User::where('email', $data['email'])->firstOrFail()->password))->toBeTrue();
})->with([null, '0']);

test('confirmed mail contains the exact saved password login company and installation login link', function () {
    $data = newStaffAccount(['send_email' => '1', 'password' => 'Test<&>Haslo123!']);
    $this->post(route('settings.users.store'), $data)->assertSessionHas('success')->assertSessionMissing('new_user_credentials');
    $user = User::where('email', $data['email'])->firstOrFail();
    Mail::assertSent(UserAccountCreated::class, function ($mail) use ($user, $data) {
        expect($mail->temporaryPassword)->toBe($data['password']);
        expect(Hash::check($mail->temporaryPassword, $user->password))->toBeTrue();
        expect($mail->render())->toContain(e($data['password']), $data['email'], 'PRINŻ — test', route('login'));

        return $mail->hasTo($user->email);
    });
    expect(json_encode(ActivityLog::get()->toArray()))->not->toContain($data['password']);
    // Repeating the request must not create a second account or resend credentials.
    $this->post(route('settings.users.store'), $data)->assertSessionHasErrors('email');
    Mail::assertSent(UserAccountCreated::class, 1);
});

test('generated password matches the hash and is emailed only with consent', function () {
    $this->post(route('settings.users.store'), newStaffAccount(['password' => '', 'send_email' => '1']))->assertSessionMissing('new_user_credentials');
    $user = User::where('email', 'new-staff@example.test')->firstOrFail();
    Mail::assertSent(UserAccountCreated::class, fn ($mail) => strlen($mail->temporaryPassword) >= 16 && Hash::check($mail->temporaryPassword, $user->password));
});

test('generated password is shown once to creator when no mail is requested', function () {
    $response = $this->post(route('settings.users.store'), newStaffAccount(['password' => '', 'send_email' => '0']))->assertSessionHas('new_user_credentials');
    $credentials = session('new_user_credentials');
    expect(Hash::check($credentials['password'], User::where('email', $credentials['login'])->firstOrFail()->password))->toBeTrue();
    $this->get(route('settings.users.index'))->assertOk()->assertSee($credentials['password']);
    $this->get(route('settings.users.index'))->assertOk()->assertDontSee($credentials['password']);
    Mail::assertNothingSent();
});

test('transport errors keep the created account and do not disclose provider contents', function () {
    Log::spy();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('secret provider contents'));
    $this->post(route('settings.users.store'), newStaffAccount(['password' => '', 'send_email' => '1']))
        ->assertSessionHas('success')->assertSessionHas('error')->assertSessionHas('new_user_credentials');
    expect(session('error'))->not->toContain('secret provider contents');
    expect(User::where('email', 'new-staff@example.test')->exists())->toBeTrue();
    Log::shouldHaveReceived('warning')->once()->with('user_account_created.mail_failed', Mockery::on(fn ($data) => array_keys($data) === ['user_id', 'error_type']));
});

test('log mailer never records account credentials instead of sending them', function () {
    config(['mail.default' => 'log']);
    $this->post(route('settings.users.store'), newStaffAccount(['password' => '', 'send_email' => '1']))->assertSessionHas('error')->assertSessionHas('new_user_credentials');
    Mail::assertNothingSent();
});

test('validation and permission boundaries prevent credential mail', function () {
    $this->post(route('settings.users.store'), newStaffAccount(['send_email' => 'invalid']))->assertSessionHasErrors('send_email');
    $this->post(route('settings.users.store'), newStaffAccount(['password' => 'short', 'send_email' => '1']))->assertSessionHasErrors('password');
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $this->actingAs($auditor)->post(route('settings.users.store'), newStaffAccount(['send_email' => '1']))->assertForbidden();
    Mail::assertNothingSent();
    expect(User::where('email', 'new-staff@example.test')->exists())->toBeFalse();
});

test('user page removes role instructions and includes creation mail choice only on new account form', function () {
    $this->get(route('settings.users.index'))->assertOk()->assertDontSee('Uprawnienia nadajesz przez rolę użytkownika')
        ->assertSee('id="create-user-form"', false)->assertSee('id="create-user-mail-dialog"', false)
        ->assertSee('Utwórz i wyślij dane')->assertSee('Utwórz bez maila')->assertSee('Wróć do formularza');
});

test('existing password change emails only with consent and matches saved hash', function ($choice) {
    $user = User::factory()->create();
    $user->assignRole('auditor');
    $version = $user->session_version;
    $this->put(route('settings.users.update', $user), newStaffAccount(['email' => $user->email, 'send_email' => $choice]))->assertSessionHas('success')->assertSessionHasNoErrors();
    expect(Hash::check('TestoweHaslo123!', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->session_version)->toBe($version + 1);
    if ($choice === '1') {
        Mail::assertSent(UserAccountCreated::class, function ($mail) use ($user) {
            expect($mail->passwordChanged)->toBeTrue();
            expect($mail->render())->toContain('Twoje hasło zostało zmienione', 'TestoweHaslo123!', $user->email)->not->toContain('Twoje konto zostało utworzone');

            return $mail->hasTo($user->email) && Hash::check($mail->temporaryPassword, $user->fresh()->password);
        });
    } else {
        Mail::assertNothingSent();
    }
    expect(json_encode(ActivityLog::get()->toArray()))->not->toContain('TestoweHaslo123!');
})->with(['0', '1']);

test('unchanged password does not send credentials even with mail flag', function () {
    $user = User::factory()->create();
    $user->assignRole('auditor');
    $hash = $user->password;
    $this->put(route('settings.users.update', $user), newStaffAccount(['email' => $user->email, 'password' => '', 'send_email' => '1']))->assertSessionHasNoErrors();
    expect($user->fresh()->password)->toBe($hash);
    Mail::assertNothingSent();
});

test('password update respects hierarchy and validates mail choice', function () {
    $user = User::factory()->create();
    $user->assignRole('auditor');
    $hash = $user->password;
    $this->put(route('settings.users.update', $user), newStaffAccount(['email' => $user->email, 'send_email' => 'invalid']))->assertSessionHasErrors('send_email');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->put(route('settings.users.update', $admin), newStaffAccount(['email' => $admin->email, 'send_email' => '1']))->assertForbidden();
    expect($user->fresh()->password)->toBe($hash);
    Mail::assertNothingSent();
});

test('password mail failure keeps new password without logging credentials', function () {
    $user = User::factory()->create();
    $user->assignRole('auditor');
    Log::spy();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('secret password provider contents'));
    $this->put(route('settings.users.update', $user), newStaffAccount(['email' => $user->email, 'send_email' => '1']))->assertSessionHas('error');
    expect(Hash::check('TestoweHaslo123!', $user->fresh()->password))->toBeTrue();
    expect(session('error'))->not->toContain('secret password provider contents');
    Log::shouldHaveReceived('warning')->once()->with('user_password_changed.mail_failed', Mockery::on(fn ($data) => array_keys($data) === ['user_id', 'error_type']));
});

test('account mail greets according to Warsaw time including the evening boundary', function (string $utc, string $greeting) {
    $this->travelTo(Carbon::parse($utc, 'UTC'));
    $mail = new UserAccountCreated('Test', 'test@example.test', 'FakeTest123!', 'Firma testowa', 'https://example.test/login');
    expect($mail->render())->toContain($greeting.',')->not->toContain('Witaj');
})->with([
    ['2026-10-15 16:59:00', 'Dzień dobry'],
    ['2026-10-15 17:00:00', 'Dobry wieczór'],
    ['2026-10-15 21:59:00', 'Dobry wieczór'],
    ['2026-10-15 22:00:00', 'Dzień dobry'],
    ['2026-12-15 17:59:00', 'Dzień dobry'],
    ['2026-12-15 18:00:00', 'Dobry wieczór'],
]);

test('account mail embeds company logo uses brand colour and aligns credentials in separate cells', function () {
    $logo = UploadedFile::fake()->image('company.png', 180, 60);
    CompanySettings::first()->update(['primary_color' => '#214568', 'logo_data' => base64_encode(file_get_contents($logo->getRealPath())), 'logo_mime' => 'image/png']);
    $mail = new UserAccountCreated('Test', 'test@example.test', 'Fake<&>123!', 'Firma testowa', 'https://example.test/login');
    $html = $mail->render();
    expect($html)->toContain('Logo Firma testowa', '#214568', 'Życzymy miłego użytkowania!', 'skontaktuj się z administratorem systemu.', 'Fake&lt;&amp;&gt;123!');
    // Laravel renders embedded images as data URIs for previews, but real mail
    // must contain a CID attachment for compatibility with desktop mail clients.
    $mail->withSymfonyMessage(function (Email $message) {
        expect($message->getHtmlBody())->toContain('cid:')->not->toContain('src="data:');
        expect($message->getAttachments())->toHaveCount(1);
        expect($message->getAttachments()[0]->getDisposition())->toBe('inline');
    });
    (new MailManager(app()))->mailer('array')->send($mail->to('test@example.test'));
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//td[normalize-space(text())="Login"]/following-sibling::td')->length)->toBe(1);
    expect($xpath->query('//td[normalize-space(text())="Hasło"]/following-sibling::td')->length)->toBe(1);
});
