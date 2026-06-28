<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfferFormTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'fields', 'is_active', 'created_by',
    ];

    protected $casts = [
        'fields'    => 'array',
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
