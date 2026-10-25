<?php

namespace App\Mail;

use App\Models\HrLeave;
use Illuminate\Mail\Mailable;

class HrLeaveApproved extends Mailable
{
    public function __construct(public HrLeave $leave) {}

    public function build()
    {
        return $this->subject('Twój wniosek urlopowy został zaakceptowany')->view('emails.hr-leave-approved');
    }
}
