<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormDraft extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['payload' => 'encrypted:array', 'revision' => 'integer'];
}
