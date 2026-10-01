<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\BoardTask;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BoardTaskService
{
    public function save(Project|Audit $owner, array $data, User $actor, ?BoardTask $card = null): BoardTask
    {
        return DB::transaction(function () use ($owner, $data, $actor, $card) {
            // All stage-linked edits lock the stage before the card, matching schedule propagation.
            $stage = ! empty($data['stage_task_id']) ? $owner->tasks()->lockForUpdate()->findOrFail($data['stage_task_id']) : null;
            if ($card) {
                $card = BoardTask::lockForUpdate()->findOrFail($card->id);
                abort_unless($card->revision === (int) $data['revision'], 409, 'Zadanie zmieniło się w międzyczasie. Odśwież tablicę.');
            }
            if (! empty($data['assigned_to']) && ! User::whereKey($data['assigned_to'])->where('is_active', true)->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['client_admin', 'client_user']))->exists()) {
                throw ValidationException::withMessages(['assigned_to' => 'Wybierz aktywnego pracownika.']);
            }
            if (! empty($data['assigned_to']) && (int) $owner->manager_id !== (int) $data['assigned_to'] && ! $owner->members()->whereKey($data['assigned_to'])->exists()) {
                throw ValidationException::withMessages(['assigned_to' => 'Osoba musi należeć do zespołu projektu lub audytu.']);
            }
            $due = ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->startOfDay() : $stage?->due_date?->copy();
            $values = collect($data)->only(['title', 'description', 'status', 'assigned_to'])->all() + [
                'stage_task_id' => $stage?->id, 'due_date' => $due,
                'stage_offset_days' => $stage?->due_date && $due ? (int) $stage->due_date->diffInDays($due, false) : null,
                'revision' => ($card?->revision ?? 0) + 1,
            ];
            if ($card) {
                $card->update($values);

                return $card;
            }

            return BoardTask::create($values + [$owner instanceof Project ? 'project_id' : 'audit_id' => $owner->id, 'created_by' => $actor->id]);
        });
    }

    public function stageUpdated(Task $stage): void
    {
        if (! $stage->wasChanged('due_date')) {
            return;
        }
        BoardTask::where('stage_task_id', $stage->id)->lockForUpdate()->get()->each(function (BoardTask $card) use ($stage) {
            if (! $stage->due_date) {
                return;
            } // Keep the last known date when the stage becomes undated.
            $offset = $card->stage_offset_days;
            if ($offset === null) {
                $old = $stage->getRawOriginal('due_date');
                $offset = $old && $card->due_date ? (int) Carbon::parse($old)->startOfDay()->diffInDays($card->due_date, false) : 0;
                if (! $old && $card->due_date) {
                    $offset = (int) $stage->due_date->diffInDays($card->due_date, false);
                }
            }
            $card->update(['due_date' => $stage->due_date->copy()->addDays($offset), 'stage_offset_days' => $offset, 'revision' => $card->revision + 1]);
        });
    }
}
