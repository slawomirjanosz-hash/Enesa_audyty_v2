<?php

namespace App\Console\Commands;

use App\Mail\TaskOverdue;
use App\Models\User;
use App\Services\TaskReminderMailConfiguration;
use App\Services\TaskReminderSelection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOverdueTaskReminders extends Command
{
    protected $signature = 'tasks:send-overdue-reminders {--dry-run : Sprawdź bez wysyłania i bez zapisu} {--scheduled : Uruchom wyłącznie w godzinie 08:00–08:59 Europe/Warsaw}';

    protected $description = 'Codzienne przypomnienia o zaległych zadaniach CRM, harmonogramu i tablicy';

    public function handle(TaskReminderSelection $selection, TaskReminderMailConfiguration $configuration): int
    {
        if ($this->option('scheduled') && now('Europe/Warsaw')->hour !== 8) {
            return self::SUCCESS;
        }
        $problem = $configuration->problem();
        if ($this->option('dry-run')) {
            $groups = $selection->groups();
            $this->info('Bez wysyłki: '.$groups->sum(fn ($tasks) => $tasks->count()).' zadań, '.$groups->count().' odbiorców.');
            $this->line($problem ?? 'Transport skonfigurowany. Dostarczenie wymaga osobnego testu wysyłki.');

            return self::SUCCESS;
        }
        if ($problem) {
            $this->warn($problem);
            Log::warning('task_reminders.configuration_missing', ['mailer' => config('mail.default')]);

            return self::FAILURE;
        }
        $mailer = config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');
        if ($transport === 'smtp' && ! config('mail.mailers.'.$mailer.'.timeout')) {
            config(['mail.mailers.'.$mailer.'.timeout' => 30]);
        }
        $date = now('Europe/Warsaw')->toDateString();
        $sent = $failed = $skipped = 0;
        foreach ($selection->groups() as $userId => $tasks) {
            // Atomic daily claim shared by replicas/redeploys. Ambiguous provider
            // failures are not retried blindly: the email might be accepted.
            $claimed = DB::table('task_reminder_deliveries')->insertOrIgnore([
                'reminder_date' => $date, 'user_id' => $userId, 'status' => 'sending',
                'mailer' => config('mail.default'), 'task_count' => $tasks->count(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $claimed) {
                $skipped++;

                continue;
            }
            $record = DB::table('task_reminder_deliveries')->where('reminder_date', $date)->where('user_id', $userId);
            try {
                $user = User::findOrFail($userId);
                Mail::to($user->email)->send(new TaskOverdue($tasks));
                $record->update(['status' => 'accepted', 'accepted_at' => now(), 'updated_at' => now()]);
                $sent++;
                Log::info('task_reminders.accepted', ['user_id' => $userId, 'tasks' => $tasks->count(), 'date' => $date]);
            } catch (Throwable $error) {
                $failed++;
                $record->update(['status' => 'failed', 'error_type' => get_class($error), 'updated_at' => now()]);
                Log::error('task_reminders.failed', ['user_id' => $userId, 'date' => $date, 'error_type' => get_class($error)]);
            }
            if ($transport === 'resend') {
                // Avoid sending an unbounded burst to the external API.
                usleep(1000000);
            }
        }
        $this->info("Przekazano do transportu: {$sent}; błędy: {$failed}; już obsłużone dzisiaj: {$skipped}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
