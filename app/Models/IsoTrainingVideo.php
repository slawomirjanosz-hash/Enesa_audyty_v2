<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoTrainingVideo extends Model
{
    protected $fillable = ['topic', 'description', 'youtube_url', 'created_by'];
}
