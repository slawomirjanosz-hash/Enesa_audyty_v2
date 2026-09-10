<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IsoSectionDocument extends Model
{
    protected $fillable = [
        'audit_id', 'section_id', 'scope', 'title', 'description', 'document_year',
        'version_number', 'original_filename', 'stored_path', 'mime_type', 'size', 'uploaded_by',
        'content_base64',
    ];

    protected $hidden = ['content_base64'];

    protected $casts = ['document_year' => 'integer'];

    public function scopeMetadata($query)
    {
        return $query->select(['id', 'audit_id', 'section_id', 'scope', 'title', 'description', 'document_year', 'version_number', 'original_filename', 'stored_path', 'mime_type', 'size', 'uploaded_by', 'created_at', 'updated_at'])
            ->selectRaw("CASE WHEN content_base64 IS NOT NULL AND content_base64 <> '' THEN 1 ELSE 0 END AS has_content_backup");
    }

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

    public function isAvailable(): bool
    {
        return (bool) $this->getAttribute('has_content_backup') || filled($this->content_base64) || Storage::disk('local')->exists($this->stored_path);
    }

    public function contents(): ?string
    {
        if (Storage::disk('local')->exists($this->stored_path)) {
            return Storage::disk('local')->get($this->stored_path);
        }

        if (! array_key_exists('content_base64', $this->getAttributes())) {
            $this->setAttribute('content_base64', self::whereKey($this->id)->value('content_base64'));
        }
        if (! filled($this->content_base64)) {
            return null;
        }

        $decoded = base64_decode($this->content_base64, true);

        return $decoded === false ? null : $decoded;
    }
}
