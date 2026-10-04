<?php

namespace App\Models;

use App\Services\BoardTaskService;
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
        return DB::transaction(fn () => parent::save($options));
    }

    protected static function booted(): void
    {
        static::updating(function (Task $task): void {
            $task->board_revision = (int) $task->getRawOriginal('board_revision') + 1;
        });
        static::updated(fn (Task $task) => app(BoardTaskService::class)->stageUpdated($task));
        static::deleting(function (Task $task): void {
            BoardTask::where('stage_task_id', $task->id)->update(['stage_task_id' => null, 'stage_offset_days' => null, 'revision' => DB::raw('revision + 1')]);
            $task->dependents()->update(['depends_on_task_id' => null]);
        });
    }

    protected $fillable = [
        'title', 'description', 'assigned_to', 'created_by',
        'deleted_by', 'company_id', 'crm_opportunity_id', 'offer_id', 'project_id', 'audit_id', 'depends_on_task_id', 'status', 'priority', 'start_date', 'due_date', 'progress', 'project_position', 'is_milestone',
    ];

    protected $casts = [
        'due_date' => 'date',
        'start_date' => 'date',
        'progress' => 'integer',
        'project_position' => 'integer',
        'is_milestone' => 'boolean',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())->where('status', '!=', 'done');
    }
}
