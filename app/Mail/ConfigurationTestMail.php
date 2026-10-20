<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ConfigurationTestMail extends Mailable
{
    public function __construct(public string $companyName, public bool $supportTest = false) {}

    public function build()
    {
        return $this->subject('Test poczty — '.$this->companyName)
            ->view('emails.configuration-test');
    }
}
