<?php

namespace App\Console\Commands;

use App\Mail\TaskOverdue;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOverdueTaskReminders extends Command
{
    protected $signature = 'tasks:send-overdue-reminders';

    protected $description = 'Wysyła codzienne przypomnienia mailowe o zaległych zadaniach do przypisanych użytkowników';

    public function handle(): int
    {
        $overdueTasks = Task::with(['company', 'assignedUser'])
            ->overdue()
            ->whereNotNull('assigned_to')
            ->get();

        if ($overdueTasks->isEmpty()) {
            $this->info('Brak zaległych zadań.');

            return self::SUCCESS;
        }

        $grouped = $overdueTasks->groupBy('assigned_to');

        foreach ($grouped as $userId => $tasks) {
            $user = User::find($userId);
            if ($user && $user->email) {
                Mail::to($user->email)->send(new TaskOverdue($tasks));
                $this->line("Wysłano przypomnienie do {$user->email} ({$tasks->count()} zadań).");
            }
        }

        $this->info('Zakończono. Wysłano przypomnienia do '.$grouped->count().' użytkowników.');

        return self::SUCCESS;
    }
}
