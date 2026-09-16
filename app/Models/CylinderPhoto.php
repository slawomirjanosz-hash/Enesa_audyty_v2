<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CylinderPhoto extends Model
{
    protected $fillable = ['stored_path', 'thumbnail_path', 'size', 'storage_owner_id'];
}
