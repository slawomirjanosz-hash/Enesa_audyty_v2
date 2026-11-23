<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

class HrLeaveReminder extends Mailable
{
    public function __construct(public Collection $leaves) {}

    public function build()
    {
        return $this->subject('Wnioski urlopowe do rozpatrzenia ('.$this->leaves->count().')')
            ->view('emails.hr-leave-reminder');
    }
}
