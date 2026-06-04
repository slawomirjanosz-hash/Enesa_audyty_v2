<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientRegistered extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public User $user,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ENESA - Nowa rejestracja klienta');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.client-registered');
    }
}
