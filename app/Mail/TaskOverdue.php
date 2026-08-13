<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TaskOverdue extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $tasks) {}

    public function build()
    {
        return $this->subject('Masz '.$this->tasks->count().' zaległych zadań w ENESA CRM')
            ->view('emails.task-overdue')
            ->with(['tasks' => $this->tasks]);
    }
}
