<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\CompanySettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientAccepted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Company $company) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Twoje konto zostało zaakceptowane — '.(CompanySettings::first()?->name ?? config('app.name')));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.client-accepted');
    }
}
