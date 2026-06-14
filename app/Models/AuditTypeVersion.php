<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditTypeVersion extends Model
{
    protected $fillable = [
        'audit_type_id',
        'version_number',
        'html_content',
        'is_current',
        'created_by',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function auditType(): BelongsTo
    {
        return $this->belongsTo(AuditType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
