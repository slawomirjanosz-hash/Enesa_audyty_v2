<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocumentShare extends Model
{
    protected $fillable = ['project_document_folder_id', 'access_level', 'expires_at', 'active', 'created_by'];

    protected $casts = ['expires_at' => 'datetime', 'active' => 'boolean'];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ProjectDocumentFolder::class, 'project_document_folder_id');
    }

    public function isAvailable(): bool
    {
        return $this->active && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function allowsUpload(): bool
    {
        return $this->access_level === 'upload';
    }
}
