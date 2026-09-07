<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDocumentFolder extends Model
{
    protected $fillable = ['project_id', 'name', 'created_by'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderByDesc('created_at');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ProjectDocumentShare::class)->latest();
    }
}
