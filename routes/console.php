<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AccountSecurityService;
use Illuminate\Support\Facades\DB;
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

Artisan::command('accounts:reset-authenticator {email} {--force}', function () {
    $user = User::where('email', $this->argument('email'))->firstOrFail();
    if (! $user->hasRole('superadmin')) {
        $this->error('Ta operacja dotyczy wyłącznie superadministratora.');

        return;
    }
    if (! $this->option('force') && ! $this->confirm('Po potwierdzeniu tożsamości właściciela: usunąć Authenticator i kody ratunkowe oraz unieważnić wszystkie sesje?')) {
        return;
    }
    DB::transaction(function () use ($user) {
        $locked = User::lockForUpdate()->findOrFail($user->id);
        $locked->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->saveQuietly();
        app(AccountSecurityService::class)->revoke($locked);
        ActivityLog::create(['action' => 'updated', 'auditable_type' => User::class, 'auditable_id' => $user->id, 'subject_label' => 'Reset Authenticator przez operatora hostingu', 'route_name' => 'console.accounts.reset-authenticator']);
    });
    $this->info('Sesje unieważnione. Przy następnym logowaniu trzeba skonfigurować Authenticator ponownie.');
});

Artisan::command('accounts:unlock {email} {--force}', function () {
    $user = User::where('email', $this->argument('email'))->firstOrFail();
    if (! $this->option('force') && ! $this->confirm('Odblokować konto i unieważnić jego dotychczasowe sesje?')) {
        return;
    }
    app(AccountSecurityService::class)->unlock($user);
    ActivityLog::create(['action' => 'updated', 'auditable_type' => User::class, 'auditable_id' => $user->id, 'subject_label' => 'Odblokowanie konta z konsoli', 'route_name' => 'console.accounts.unlock', 'changes' => ['blokada' => ['old' => 'Zablokowane', 'new' => 'Odblokowane przez operatora hostingu']]]);
    $this->info('Konto odblokowane.');
});
