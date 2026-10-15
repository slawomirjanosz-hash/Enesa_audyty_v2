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
    ) {}

    public function build()
    {
        return $this->subject('Utworzono konto — '.$this->companyName)
            ->view('emails.user-account-created');
    }
}
