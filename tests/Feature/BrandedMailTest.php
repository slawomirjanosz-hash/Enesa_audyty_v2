<?php

use App\Mail\ClientAccepted;
use App\Mail\ClientRegistered;
use App\Mail\ConfigurationTestMail;
use App\Mail\HrVehicleReminder;
use App\Mail\NewClientUser;
use App\Mail\TaskAssigned;
use App\Mail\TaskOverdue;
use App\Mail\UserAccountCreated;
use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\HrVehicle;
use App\Models\Task;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\URL;

test('all application mails share company branding inline logo and canonical links', function () {
    $logo = UploadedFile::fake()->image('logo.png', 120, 40);
    CompanySettings::create(['name' => 'PRINŻ test', 'primary_color' => '#214568', 'logo_data' => base64_encode(file_get_contents($logo->getRealPath())), 'logo_mime' => 'image/png']);
    $company = Company::create(['name' => 'Klient testowy', 'nip' => '1234567890']);
    $user = User::factory()->create();
    $task = new Task(['title' => 'Zadanie testowe', 'due_date' => now()->subDay()]);
    $vehicle = new HrVehicle(['name' => 'Auto testowe', 'registration_number' => 'TEST123']);
    $vehicle->id = 123;
    config(['app.canonical_url' => 'https://app.pri.cieszyn.pl']);
    (new AppServiceProvider(app()))->boot();
    // Generating links from the old installation host must still use the new one.
    app('url')->setRequest(Request::create('http://prinz-cieszyn.up.railway.app/settings/users'));
    $mails = [
        new UserAccountCreated($user->name, $user->email, 'FakeTest123!', 'PRINŻ test', route('login')),
        new UserAccountCreated($user->name, $user->email, 'FakeTest123!', 'PRINŻ test', route('login'), true),
        new HrVehicleReminder(collect([['vehicle' => $vehicle, 'message' => 'Przegląd po terminie']])),
        new TaskAssigned($task), new TaskOverdue(collect([$task])),
        new ConfigurationTestMail('PRINŻ test'), new ClientAccepted($company),
        new ClientRegistered($company, $user), new NewClientUser($user, $company, 'FakeTest123!'),
    ];
    $mailer = (new MailManager(app()))->mailer('array');
    foreach ($mails as $mail) {
        $mailer->send($mail->to($user->email));
        $email = $mailer->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $html = $email->getHtmlBody();
        expect($html)->toContain('PRINŻ test', '#214568', 'cid:', 'Życzymy miłego użytkowania!', 'https://app.pri.cieszyn.pl')->not->toContain('prinz-cieszyn.up.railway.app', 'biuro@enesa.pl', 'ENESA —');
        expect($email->getAttachments())->toHaveCount(1);
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        expect($dom->getElementsByTagName('h1')->length)->toBe(1);
    }
    URL::forceRootUrl(null);
});

test('security notifications share branding and retain working reset and signed verification links', function () {
    CompanySettings::create(['name' => 'Firma testowa', 'primary_color' => '#214568']);
    $user = User::factory()->create();
    $reset = (new ResetPassword('test-token'))->toMail($user);
    $verify = (new VerifyEmail)->toMail($user);
    foreach ([$reset, $verify] as $message) {
        expect($message->view)->toBe('emails.notification');
        $html = view($message->view, $message->viewData)->render();
        expect($html)->toContain('Firma testowa', '#214568', 'Życzymy miłego użytkowania!');
    }
    expect($reset->viewData['actionUrl'])->toContain('test-token', urlencode($user->email));
    expect(URL::hasValidSignature(Request::create($verify->viewData['actionUrl'])))->toBeTrue();
});

test('other installations keep their own domain and company branding', function () {
    CompanySettings::create(['name' => 'ENESA test', 'primary_color' => '#112233']);
    app('url')->setRequest(Request::create('https://app.enesa.pl'));
    URL::forceRootUrl('https://app.enesa.pl');
    $html = (new ConfigurationTestMail('ENESA test'))->render();
    expect($html)->toContain('ENESA test', '#112233', 'https://app.enesa.pl')->not->toContain('app.pri.cieszyn.pl');
    URL::forceRootUrl(null);
});
