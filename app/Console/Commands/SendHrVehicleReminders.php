<?php

namespace App\Console\Commands;

use App\Mail\HrVehicleReminder;
use App\Services\HrVehicleReminderSelection;
use App\Services\TaskReminderMailConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendHrVehicleReminders extends Command
{
    protected $signature = 'hr:send-vehicle-reminders {--dry-run} {--scheduled}';

    protected $description = 'Powiadomienia o terminach aut i poniedziałkowy raport brakujących danych';

    public function handle(HrVehicleReminderSelection $selection, TaskReminderMailConfiguration $configuration): int
    {
        if ($this->option('scheduled') && now('Europe/Warsaw')->hour !== 8) {
            return self::SUCCESS;
        }
        $groups = $selection->groups();
        if ($this->option('dry-run')) {
            $this->info('Bez wysyłki: '.$groups->count().' odbiorców, '.$groups->sum(fn ($items) => $items->count()).' zdarzeń przed odfiltrowaniem wysłanych.');
            $this->line($configuration->problem() ?? 'Transport skonfigurowany.');

            return self::SUCCESS;
        }
        if ($groups->isEmpty()) {
            return self::SUCCESS;
        }
        if ($problem = $configuration->problem()) {
            $this->error($problem);

            return self::FAILURE;
        }
        $driver = config('mail.default');
        $transport = config('mail.mailers.'.$driver.'.transport');
        if ($transport === 'smtp' && ! config('mail.mailers.'.$driver.'.timeout')) {
            config(['mail.mailers.'.$driver.'.timeout' => 30]);
        }
        $failed = 0;
        foreach ($groups as $id => $items) {
            $claimed = $items->filter(fn ($item) => DB::table('hr_vehicle_reminder_deliveries')->insertOrIgnore([
                'event_key' => $item['key'], 'user_id' => $id, 'status' => 'sending', 'created_at' => now(), 'updated_at' => now(),
            ]));
            if ($claimed->isEmpty()) {
                continue;
            }
            $records = DB::table('hr_vehicle_reminder_deliveries')->whereIn('event_key', $claimed->pluck('key'));
            try {
                Mail::to($claimed->first()['user']->email)->send(new HrVehicleReminder($claimed));
                $records->update(['status' => 'accepted', 'accepted_at' => now(), 'updated_at' => now()]);
                Log::info('hr_vehicle_reminders.accepted', ['user_id' => $id, 'events' => $claimed->count()]);
            } catch (Throwable $error) {
                $failed++;
                $records->update(['status' => 'failed', 'error_type' => get_class($error), 'updated_at' => now()]);
                Log::error('hr_vehicle_reminders.failed', ['user_id' => $id, 'error_type' => get_class($error)]);
            }
            if ($transport === 'resend') {
                usleep(1000000);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
