<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoardTask extends Model
{
    use SoftDeletes;

    public function isCalendarEntry(): bool
    {
        return ! $this->project_id && ! $this->audit_id && in_array($this->calendar_kind, ['task', 'meeting'], true);
    }

    public function scopeCalendarEntries($query)
    {
        return $query->whereNull('project_id')->whereNull('audit_id')->whereIn('calendar_kind', ['task', 'meeting']);
    }

    protected $fillable = ['project_id', 'audit_id', 'stage_task_id', 'assigned_to', 'created_by', 'title', 'description', 'status', 'due_date', 'stage_offset_days', 'revision'];

    protected $casts = ['completion_confirmations' => 'array', 'due_date' => 'date', 'stage_offset_days' => 'integer', 'revision' => 'integer', 'assigned_to' => 'integer'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'stage_task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_task_participants');
    }

    public function assignedToUser(int $id): bool
    {
        return $this->assigned_to === $id || $this->participants->contains('id', $id);
    }

    public function color(): string
    {
        if ($this->status === 'done') {
            return 'green';
        }
        if ($this->due_date?->lt(today())) {
            return 'red';
        }

        return $this->status === 'in_progress' ? 'orange' : 'gray';
    }
}
