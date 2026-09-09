<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IsoImplementationResponse extends Model
{
    protected $fillable = ['audit_id', 'section_id', 'action_key', 'answers', 'completed_by', 'generated_at'];

    protected $casts = ['answers' => 'array', 'generated_at' => 'datetime'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
