<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AccountSecurityService;
use Illuminate\Support\Facades\Schedule;

Schedule::command('tasks:send-overdue-reminders')->dailyAt('08:00')->timezone('Europe/Warsaw');

Artisan::command('accounts:block-inactive', function () {
    User::whereNull('security_block_reason')->where('is_active', true)->chunkById(200, function ($users) {
        foreach ($users as $user) {
            app(AccountSecurityService::class)->checkInactivity($user);
        }
    });
    $this->info('Sprawdzono nieaktywne konta.');
});
Schedule::command('accounts:block-inactive')->dailyAt('03:00')->timezone('Europe/Warsaw')->withoutOverlapping();

Artisan::command('accounts:unlock {email} {--force}', function () {
    $user = User::where('email', $this->argument('email'))->firstOrFail();
    if (! $this->option('force') && ! $this->confirm('Odblokować konto i unieważnić jego dotychczasowe sesje?')) {
        return;
    }
    app(AccountSecurityService::class)->unlock($user);
    ActivityLog::create(['action' => 'updated', 'auditable_type' => User::class, 'auditable_id' => $user->id, 'subject_label' => 'Odblokowanie konta z konsoli', 'route_name' => 'console.accounts.unlock', 'changes' => ['blokada' => ['old' => 'Zablokowane', 'new' => 'Odblokowane przez operatora hostingu']]]);
    $this->info('Konto odblokowane.');
});
