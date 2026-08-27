<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnergyPassport extends Model
{
    protected $fillable = [
        'template_id', 'company_id', 'name', 'asset_identifier', 'location', 'status', 'notes', 'responses', 'created_by',
    ];

    protected $casts = [
        'responses' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EnergyPassportTemplate::class, 'template_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
