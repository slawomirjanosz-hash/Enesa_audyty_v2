<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IsoSectionDocument extends Model
{
    protected $fillable = [
        'audit_id', 'section_id', 'scope', 'title', 'description', 'document_year',
        'version_number', 'original_filename', 'stored_path', 'mime_type', 'size', 'uploaded_by',
    ];

    protected $casts = ['document_year' => 'integer'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function formattedSize(): string
    {
        return Document::formatBytes((int) $this->size);
    }
}
