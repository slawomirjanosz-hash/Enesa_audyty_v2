<?php

namespace App\Http\Controllers;

use App\Mail\ConfigurationTestMail;
use App\Models\CompanySettings;
use App\Services\TaskReminderMailConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SupportMailTestController extends Controller
{
    public const RECIPIENT = 'slawomir.janosz@pri.cieszyn.pl';

    public function __invoke(Request $request, TaskReminderMailConfiguration $configuration)
    {
        if ($request->isMethod('post')) {
            $key = 'support-mail-test:'.hash('sha256', (string) $request->query('signature'));
            abort_if(RateLimiter::tooManyAttempts($key.':total', 20), 429, 'Wykorzystano limit 20 prób dla tego linku.');
            $result = RateLimiter::attempt($key, 1, function () use ($configuration, $key) {
                RateLimiter::hit($key.':total', 48 * 3600);
                if ($configuration->problem()) {
                    return 'Nieprawidłowa konfiguracja poczty. Skontaktuj się z administratorem aplikacji.';
                }
                try {
                    Mail::to(self::RECIPIENT)->send(new ConfigurationTestMail(CompanySettings::first()?->name ?? config('app.name'), true));
                } catch (Throwable $exception) {
                    Log::warning('Support mail test failed', ['error_type' => get_class($exception)]);

                    return 'Nie udało się przekazać wiadomości do wysyłki. Administrator może sprawdzić usługę pocztową.';
                }

                return 'Przekazano mail testowy do wysyłki na '.self::RECIPIENT.'. Nie jest to potwierdzenie doręczenia. Sprawdź status w Resend i logi serwera odbiorcy.';
            }, 60);
            abort_if($result === false, 429, 'Odczekaj minutę przed kolejną próbą.');

            return redirect()->to($request->fullUrl())->with('mail_test_result', $result);
        }

        return response()->view('support.mail-test', ['recipient' => self::RECIPIENT])
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
