<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoPresentation extends Model
{
    protected $fillable = ['section_id', 'title', 'description', 'original_filename', 'source_size', 'slide_count', 'created_by'];
}
