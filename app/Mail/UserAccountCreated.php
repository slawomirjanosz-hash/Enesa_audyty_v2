<?php

namespace App\Mail;

use App\Models\CompanySettings;
use Illuminate\Mail\Mailable;

class UserAccountCreated extends Mailable
{
    public function __construct(
        public string $userName,
        public string $login,
        public string $temporaryPassword,
        public string $companyName,
        public string $loginUrl,
    ) {}

    public function build()
    {
        $company = CompanySettings::first();
        $logoContent = null;
        $logoMime = 'image/png';
        if (preg_match('/^data:([^;]+);base64,(.+)$/s', $company?->logoDataUri() ?? '', $logo)) {
            $logoContent = base64_decode($logo[2], true) ?: null;
            $logoMime = $logo[1];
        }

        return $this->subject('Utworzono konto — '.$this->companyName)
            ->view('emails.user-account-created')->with([
                'brandColor' => $company?->primaryColor() ?? '#1A4D3A',
                'brandTint' => $company?->documentHeaderColor() ?? '#8DA69D',
                'greeting' => now('Europe/Warsaw')->hour >= 19 ? 'Dobry wieczór' : 'Dzień dobry',
                'logoContent' => $logoContent,
                'logoMime' => $logoMime,
            ]);
    }
}
