<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditDocumentFolder extends Model
{
    protected $fillable = ['audit_id', 'name', 'created_by'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'audit_document_folder_id');
    }
}
