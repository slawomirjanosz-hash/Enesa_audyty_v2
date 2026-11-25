<?php

namespace App\Services;

use App\Models\BoardTask;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScheduleTaskAssignment
{
    public function sync(Task $stage): void
    {
        if ((! $stage->project_id && ! $stage->audit_id) || ! Schema::hasColumn('board_tasks', 'source_stage_id')) {
            return;
        }

        DB::transaction(function () use ($stage): void {
            $stage = Task::lockForUpdate()->find($stage->id);
            if (! $stage) {
                return;
            }
            $card = BoardTask::withTrashed()->where('source_stage_id', $stage->id)->lockForUpdate()->first();
            if (! $card && $stage->schedule_card_created) {
                return;
            }
            if (! $card && $stage->assigned_to) {
                $card = BoardTask::withTrashed()->whereNull('source_stage_id')->where('stage_task_id', $stage->id)
                    ->where('title', $stage->title)->where('assigned_to', $stage->assigned_to)->orderBy('id')->lockForUpdate()->first();
                if ($card) {
                    $card->forceFill(['source_stage_id' => $stage->id])->save();
                    DB::table('tasks')->where('id', $stage->id)->update(['schedule_card_created' => true]);
                }
            }
            if ($card?->trashed()) {
                return;
            }
            if (! $card && (! $stage->assigned_to || $stage->assignedUser?->hasRole('superadmin'))) {
                return;
            }
            $values = [
                'project_id' => $stage->project_id,
                'audit_id' => $stage->audit_id,
                'stage_task_id' => $stage->id,
                'source_stage_id' => $stage->id,
                'title' => $stage->title,
                'description' => $stage->description,
                'assigned_to' => $stage->assigned_to,
                'due_date' => $stage->due_date,
                'stage_offset_days' => 0,
            ];
            if (! $card) {
                BoardTask::create($values + ['created_by' => $stage->created_by, 'status' => $stage->status, 'revision' => 1]);
                DB::table('tasks')->where('id', $stage->id)->update(['schedule_card_created' => true]);

                return;
            }
            $card->fill($values);
            if ($card->isDirty('assigned_to')) {
                $card->completion_confirmations = [];
                if ($card->status === 'done') {
                    $card->status = 'todo';
                }
            }
            if ($card->isDirty()) {
                $card->revision++;
                $card->save();
            }
        });
    }
}
