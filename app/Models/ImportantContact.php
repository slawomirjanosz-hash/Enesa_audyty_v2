<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportantContact extends Model
{
    protected $fillable = [
        'first_name', 'last_name', 'company_name', 'position', 'specialization',
        'activity_description', 'help_description', 'email', 'phone', 'linkedin_url',
        'notes', 'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
