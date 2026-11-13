<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class HrLeaveSubmitted extends Mailable
{
    public function __construct(public int $leaveId) {}

    public function build()
    {
        return $this->subject('Nowy wniosek urlopowy do rozpatrzenia')->view('emails.hr-leave-submitted');
    }
}
