<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CylinderPhoto extends Model
{
    protected $fillable = ['cylinder_id', 'cylinder_inspection_id', 'stored_path', 'thumbnail_path', 'size', 'storage_owner_id'];
}
