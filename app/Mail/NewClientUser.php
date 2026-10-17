<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\CompanySettings;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewClientUser extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Company $company,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Dostęp do systemu — '.(CompanySettings::first()?->name ?? config('app.name')));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.new-client-user');
    }
}
