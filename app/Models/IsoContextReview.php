<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoContextReview extends Model
{
    protected $attributes = ['status' => 'draft', 'revision' => 0, 'library_version' => '1.3'];

    protected $guarded = ['id'];

    protected $casts = ['answers' => 'array', 'year' => 'integer', 'revision' => 'integer'];
}
