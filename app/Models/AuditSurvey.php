<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditSurvey extends Model
{
    protected $fillable = ['audit_id', 'title', 'status', 'notes', 'created_by'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
