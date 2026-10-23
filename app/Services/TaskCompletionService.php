<?php

namespace App\Services;

use App\Models\BoardTask;
use App\Models\Task;
use App\Models\User;

class TaskCompletionService
{
    public function people(BoardTask|Task $task): array
    {
        return $task->participants->pluck('id')->push($task->assigned_to)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function complete(BoardTask|Task $task): bool
    {
        $people = $this->people($task);

        return $task->status === 'done' && (count($people) < 2 || ! array_diff($people, array_keys($task->completion_confirmations ?? [])));
    }

    // Call under the task's row lock, including edits submitted outside the board.
    public function apply(BoardTask|Task $task, array $data, User $actor): void
    {
        $before = $task->status;
        $task->fill($data);
        $people = $this->people($task);
        $confirmed = array_intersect_key($task->completion_confirmations ?? [], array_flip($people));
        if ($task->isDirty('assigned_to')) {
            $confirmed = [];
        }
        if (($data['status'] ?? null) === 'done' && ($before !== 'done' || $task->isDirty('assigned_to'))) {
            if (count($people) > 1) {
                abort_unless(in_array($actor->id, $people, true), 403, 'Każdy wykonawca musi potwierdzić własne wykonanie zadania.');
                $confirmed[$actor->id] = now()->toIso8601String();
            }
        } elseif (isset($data['status']) && $data['status'] !== $before) {
            $confirmed = [];
        }
        $task->completion_confirmations = $confirmed;
        if ($task->status === 'done' && count($people) > 1 && array_diff($people, array_keys($confirmed))) {
            $task->status = 'in_progress';
        }
    }

    public function participantsChanged(BoardTask|Task $task): void
    {
        $task->unsetRelation('participants');
        $people = $this->people($task);
        $task->completion_confirmations = array_intersect_key($task->completion_confirmations ?? [], array_flip($people));
        if ($task->status === 'done' && ! $this->complete($task)) {
            $task->status = 'in_progress';
        } elseif ($task->status === 'in_progress' && $task->completion_confirmations && ! array_diff($people, array_keys($task->completion_confirmations))) {
            $task->status = 'done';
        }
    }
}
