<?php

namespace App\Models;

use App\Services\BoardTaskService;
use App\Services\ProjectScheduleDelayService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    use SoftDeletes;

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            if ($this->project_id) {
                DB::table('projects')->where('id', $this->project_id)->lockForUpdate()->first();
            }

            return parent::save($options);
        }, 3);
    }

    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            if (! $task->project_id) {
                return;
            }
            if (! $task->exists || $task->isDirty('depends_on_task_id')) {
                $task->schedule_dependency_anchor = $task->depends_on_task_id
                    ? self::where('project_id', $task->project_id)->find($task->depends_on_task_id)?->due_date
                    : null;
                $task->schedule_dependency_shift = 0;
            }
            if (! $task->exists || $task->isDirty(['progress', 'status'])) {
                $task->schedule_completed_on = $task->scheduleIsComplete()
                    ? ($task->schedule_completed_on ?? now('Europe/Warsaw')->startOfDay()) : null;
            }
        });
        static::updating(function (Task $task): void {
            $task->board_revision = (int) $task->getRawOriginal('board_revision') + 1;
        });
        static::updated(fn (Task $task) => app(BoardTaskService::class)->stageUpdated($task));
        static::saved(function (Task $task): void {
            if ($task->project_id && ($task->wasRecentlyCreated || $task->wasChanged(['start_date', 'due_date', 'progress', 'status', 'depends_on_task_id']))) {
                app(ProjectScheduleDelayService::class)->syncProject($task->project_id);
                $task->refresh();
            }
        });
        static::deleting(function (Task $task): void {
            BoardTask::where('stage_task_id', $task->id)->update(['stage_task_id' => null, 'stage_offset_days' => null, 'revision' => DB::raw('revision + 1')]);
            $task->dependents()->update(['depends_on_task_id' => null]);
        });
    }

    protected $fillable = [
        'title', 'description', 'assigned_to', 'created_by', 'scheduled_time',
        'deleted_by', 'company_id', 'crm_opportunity_id', 'offer_id', 'project_id', 'audit_id', 'depends_on_task_id', 'status', 'priority', 'start_date', 'due_date', 'progress', 'project_position', 'is_milestone', 'schedule_cost',
    ];

    protected $casts = [
        'completion_confirmations' => 'array',
        'due_date' => 'date',
        'start_date' => 'date',
        'progress' => 'integer',
        'schedule_cost' => 'decimal:2',
        'project_position' => 'integer',
        'is_milestone' => 'boolean',
        'schedule_dependency_anchor' => 'date',
        'schedule_dependency_shift' => 'integer',
        'schedule_completed_on' => 'date',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function crmOpportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(self::class, 'depends_on_task_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(self::class, 'depends_on_task_id');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where(fn ($q) => $q->where('assigned_to', $userId)->orWhereHas('participants', fn ($p) => $p->whereKey($userId)));
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_participants');
    }

    public function scopeCrm($query)
    {
        return $query->whereNull('project_id')->whereNull('audit_id');
    }

    public function scopeCrmDefaultOrder($query)
    {
        return $query->orderByRaw("CASE status WHEN 'todo' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'done' THEN 2 ELSE 3 END")
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())->where('status', '!=', 'done');
    }

    public function scheduleIsComplete(): bool
    {
        return $this->progress >= 100 || $this->status === 'done';
    }

    public function scheduleEffectiveEnd(): ?Carbon
    {
        if (! $this->due_date) {
            return null;
        }
        $today = Carbon::parse(now('Europe/Warsaw')->toDateString(), $this->due_date->timezone);
        $end = $this->scheduleIsComplete() ? ($this->schedule_completed_on ?? $this->due_date) : $today;

        return $end->greaterThan($this->due_date) ? $end->copy() : $this->due_date->copy();
    }

    public function scheduleDelayPayload(): array
    {
        $end = $this->scheduleEffectiveEnd();

        return ['overdue_end' => $end?->toDateString(), 'overdue_days' => $end && $this->due_date ? (int) $this->due_date->diffInDays($end) : 0];
    }
}
