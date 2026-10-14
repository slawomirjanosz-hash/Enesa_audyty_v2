<?php

namespace App\Services;

class TaskReminderMailConfiguration
{
    public function problem(): ?string
    {
        $driver = config('mail.default');
        $transport = config('mail.mailers.'.$driver.'.transport');
        if (! in_array($transport, ['smtp', 'resend', 'ses', 'postmark', 'mailgun', 'sendmail'], true)) {
            return 'Skonfiguruj rzeczywisty transport poczty. Tryb log/array lub failover do logów nie wysyła wiarygodnych przypomnień.';
        }
        $from = config('mail.from.address');
        if (! filter_var($from, FILTER_VALIDATE_EMAIL) || str_ends_with($from, '@example.com')) {
            return 'Skonfiguruj adres nadawcy MAIL_FROM_ADDRESS.';
        }
        if ($transport === 'resend' && ! config('services.resend.key')) {
            return 'Brak RESEND_API_KEY.';
        }
        if ($transport === 'smtp' && ! config('mail.mailers.'.$driver.'.host') && ! config('mail.mailers.'.$driver.'.url')) {
            return 'Brak hosta SMTP.';
        }

        return null;
    }
}
