<?php

namespace App\Console\Commands;

use App\Mail\HrLeaveReminder;
use App\Models\User;
use App\Services\HrLeaveReminderSelection;
use App\Services\TaskReminderMailConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendHrLeaveReminders extends Command
{
    protected $signature = 'hr:send-leave-reminders {--scheduled} {--dry-run}';

    protected $description = 'Zbiorcze przypomnienia przełożonym o nierozpatrzonych urlopach';

    public function handle(HrLeaveReminderSelection $selection, TaskReminderMailConfiguration $configuration): int
    {
        $now = now('Europe/Warsaw');
        if ($this->option('scheduled') && $now->hour !== 8) {
            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $groups = $selection->groups();
            $this->info('Bez wysyłki: '.$groups->sum(fn ($items) => $items->count()).' wniosków, '.$groups->count().' przełożonych.');

            return self::SUCCESS;
        }
        if ($problem = $configuration->problem()) {
            $this->warn($problem);

            return self::FAILURE;
        }
        $mailer = config('mail.default');
        if (config('mail.mailers.'.$mailer.'.transport') === 'smtp' && ! config('mail.mailers.'.$mailer.'.timeout')) {
            config(['mail.mailers.'.$mailer.'.timeout' => 30]);
        }
        $failures = 0;
        foreach ($selection->groups() as $id => $leaves) {
            $date = $now->toDateString();
            if (! DB::table('hr_leave_reminder_deliveries')->insertOrIgnore([
                'reminder_date' => $date, 'approver_id' => $id, 'status' => 'sending',
                'request_count' => $leaves->count(), 'created_at' => now(), 'updated_at' => now(),
            ])) {
                continue;
            }
            $record = DB::table('hr_leave_reminder_deliveries')->where('reminder_date', $date)->where('approver_id', $id);
            try {
                Mail::to(User::findOrFail($id)->email)->send(new HrLeaveReminder($leaves));
                $record->update(['status' => 'accepted', 'updated_at' => now()]);
            } catch (Throwable $error) {
                $failures++;
                $record->update(['status' => 'failed', 'error_type' => get_class($error), 'updated_at' => now()]);
                Log::warning('hr.leave.reminder_mail_failed', ['approver_id' => $id, 'error_type' => get_class($error)]);
            }
            if (config('mail.mailers.'.config('mail.default').'.transport') === 'resend') {
                usleep(1000000);
            }
        }

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
