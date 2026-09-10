<?php

namespace App\Console\Commands;

use App\Mail\TaskOverdue;
use App\Models\CompanySettings;
use App\Models\Task;
use App\Services\AuditorAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOverdueTaskReminders extends Command
{
    protected $signature = 'tasks:send-overdue-reminders';

    protected $description = 'Wysyła codzienne przypomnienia mailowe o zaległych zadaniach do przypisanych użytkowników';

    public function handle(): int
    {
        $settings = CompanySettings::first();
        $enabled = collect(['projects', 'audits', 'crm'])->mapWithKeys(fn ($module) => [$module => $settings?->moduleEnabled($module) ?? true]);
        $overdueTasks = Task::with(['company', 'assignedUser', 'project.members', 'audit'])
            ->overdue()
            ->whereNotNull('assigned_to')
            ->where(fn ($q) => $q->whereNull('project_id')->orWhereHas('project', fn ($p) => $p->where('status', '!=', 'completed')))
            ->where(fn ($q) => $q->whereNull('audit_id')->orWhereHas('audit', fn ($a) => $a->whereNotIn('status', ['done', 'cancelled'])))
            ->get()->filter(function (Task $task) use ($enabled) {
                $user = $task->assignedUser;
                if (! $user || ! $user->is_active || ! $user->getRoleNames()->contains(fn ($role) => ! in_array($role, ['client_admin', 'client_user'], true))) {
                    return false;
                }
                $full = app(AuditorAccessService::class)->hasFullAccess($user);
                if ($task->project_id !== null) {
                    return $enabled['projects'] && $task->project && $user->can('view', $task->project)
                        && ($full || $user->canAny(['projects.schedule.view', 'projects.schedule.manage']));
                }
                if ($task->audit_id !== null) {
                    return $enabled['audits'] && $task->audit && $user->can('view', $task->audit);
                }

                return $enabled['crm'] && ($full || $user->can('crm.tasks.own.manage') || $user->can('crm.tasks.team.manage'));
            });

        if ($overdueTasks->isEmpty()) {
            $this->info('Brak zaległych zadań.');

            return self::SUCCESS;
        }

        $grouped = $overdueTasks->groupBy('assigned_to');

        foreach ($grouped as $userId => $tasks) {
            $user = $tasks->first()->assignedUser;
            if ($user && $user->email) {
                Mail::to($user->email)->send(new TaskOverdue($tasks));
                $this->line("Wysłano przypomnienie do {$user->email} ({$tasks->count()} zadań).");
            }
        }

        $this->info('Zakończono. Wysłano przypomnienia do '.$grouped->count().' użytkowników.');

        return self::SUCCESS;
    }
}
