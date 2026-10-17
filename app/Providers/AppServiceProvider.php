<?php

namespace App\Providers;

use App\Models\CompanySettings;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\SuperadminSmsService;
use App\Services\SuperadminTotpService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('pagination.compact');
        if (config('app.canonical_url')) {
            URL::forceRootUrl(config('app.canonical_url'));
            URL::forceScheme(parse_url(config('app.canonical_url'), PHP_URL_SCHEME));
        }
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            return (new MailMessage)->subject('Resetowanie hasła')->view('emails.notification', [
                'notificationTitle' => 'Resetowanie hasła',
                'notificationText' => 'Otrzymaliśmy prośbę o zmianę hasła do Twojego konta. Jeśli jej nie wysyłano, zignoruj tę wiadomość.',
                'actionText' => 'Ustaw nowe hasło',
                'actionUrl' => route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]),
                'notificationNote' => 'Link wygaśnie po '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutach.',
            ]);
        });
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            return (new MailMessage)->subject('Potwierdź adres e-mail')->view('emails.notification', [
                'notificationTitle' => 'Potwierdź adres e-mail',
                'notificationText' => 'Potwierdź swój adres e-mail, aby korzystać z konta. Jeśli nie zakładano tego konta, zignoruj tę wiadomość.',
                'actionText' => 'Potwierdź adres e-mail',
                'actionUrl' => $url,
                'notificationNote' => '',
            ]);
        });
        View::composer('emails.*', function ($view): void {
            $company = CompanySettings::first();
            $logoContent = null;
            $logoMime = 'image/png';
            if (preg_match('/^data:([^;]+);base64,(.+)$/s', $company?->logoDataUri() ?? '', $logo)) {
                $logoContent = base64_decode($logo[2], true) ?: null;
                $logoMime = $logo[1];
            }
            $view->with([
                'mailCompanyName' => $view->getData()['companyName'] ?? $company?->name ?? config('app.name'),
                'brandColor' => $company?->primaryColor() ?? '#1A4D3A',
                'brandTint' => $company?->documentHeaderColor() ?? '#8DA69D',
                'greeting' => now('Europe/Warsaw')->hour >= 19 ? 'Dobry wieczór' : 'Dzień dobry',
                'logoContent' => $logoContent,
                'logoMime' => $logoMime,
            ]);
        });
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $copyAddress = 'proximalumine@gmail.com';
            $message = $event->message;
            foreach (array_merge($message->getTo(), $message->getCc(), $message->getBcc()) as $recipient) {
                if (strcasecmp($recipient->getAddress(), $copyAddress) === 0) {
                    return;
                }
            }
            $message->addBcc($copyAddress);
        });
        foreach (['created', 'updated', 'deleted', 'restored'] as $action) {
            Event::listen("eloquent.{$action}: *", function (string $eventName, array $models) use ($action): void {
                if (isset($models[0])) {
                    app(ActivityLogService::class)->recordModel($models[0], $action);
                }
            });
        }
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $needsSecondFactor = app(SuperadminTotpService::class)->required($event->user) || app(SuperadminSmsService::class)->required($event->user);
                app(ActivityLogService::class)->recordAuthentication($event->user, $needsSecondFactor ? 'login_password' : 'login');
            }
        });
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(ActivityLogService::class)->recordAuthentication($event->user, 'logout');
            }
        });

        View::composer('*', function ($view): void {
            $request = request();
            if (! $request->attributes->has('app_brand_loaded')) {
                $request->attributes->set('app_brand', CompanySettings::query()->first());
                $request->attributes->set('app_brand_loaded', true);
            }
            $view->with('appBrand', $request->attributes->get('app_brand'));
        });
    }
}
