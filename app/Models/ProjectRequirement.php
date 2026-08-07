<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRequirement extends Model
{
    protected $fillable = [
        'project_id', 'type', 'name', 'description', 'quantity', 'unit',
        'estimated_cost', 'supplier', 'status', 'needed_by', 'responsible_id', 'created_by',
    ];

    protected $casts = ['quantity' => 'decimal:2', 'estimated_cost' => 'decimal:2', 'needed_by' => 'date'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}
