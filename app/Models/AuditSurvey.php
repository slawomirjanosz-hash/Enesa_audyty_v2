<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditSurvey extends Model
{
    protected $fillable = ['audit_id', 'audit_type_id', 'audit_type_version_id', 'title', 'status', 'notes', 'created_by'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function auditType(): BelongsTo
    {
        return $this->belongsTo(AuditType::class);
    }

    public function auditTypeVersion(): BelongsTo
    {
        return $this->belongsTo(AuditTypeVersion::class);
    }
}
