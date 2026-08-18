<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'company_id', 'offer_id', 'audit_id', 'project_id', 'type',
        'original_filename', 'stored_path', 'mime_type', 'size', 'uploaded_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function auditorAccesses(): HasMany
    {
        return $this->hasMany(AuditorDocumentAccess::class);
    }

    public function formattedSize(): string
    {
        $bytes = $this->size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function displayFilename(): string
    {
        if ($this->type === 'offer_pdf' && $this->offer) {
            return $this->offer->documentFilename('pdf');
        }

        return $this->original_filename;
    }
}
