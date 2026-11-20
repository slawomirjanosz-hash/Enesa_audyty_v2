<?php

namespace App\Mail;

use App\Models\CompanySettings;
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
        return $this->subject('Minął termin: zadania i etapy prac ('.$this->tasks->count().') — '.(CompanySettings::first()?->name ?? config('app.name')))
            ->view('emails.task-overdue')
            ->with(['tasks' => $this->tasks]);
    }
}
