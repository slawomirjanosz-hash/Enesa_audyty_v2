<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CylinderImport extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['rows' => 'array', 'create_series' => 'boolean', 'expires_at' => 'datetime', 'completed_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
