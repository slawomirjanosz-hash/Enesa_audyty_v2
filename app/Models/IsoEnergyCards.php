<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoEnergyCards extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['cards' => 'array'];
}
