<?php

namespace App\Mail;

use App\Models\CompanySettings;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

class HrVehicleReminder extends Mailable
{
    public function __construct(public Collection $items) {}

    public function build()
    {
        return $this->subject('Samochody — terminy i brakujące dane — '.(CompanySettings::first()?->name ?? config('app.name')))
            ->view('emails.hr-vehicle-reminder');
    }
}
