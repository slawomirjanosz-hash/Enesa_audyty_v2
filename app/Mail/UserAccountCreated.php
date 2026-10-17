<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class UserAccountCreated extends Mailable
{
    public function __construct(
        public string $userName,
        public string $login,
        public string $temporaryPassword,
        public string $companyName,
        public string $loginUrl,
        public bool $passwordChanged = false,
    ) {}

    public function build()
    {
        return $this->subject(($this->passwordChanged ? 'Zmieniono hasło — ' : 'Utworzono konto — ').$this->companyName)
            ->view('emails.user-account-created');
    }
}
