<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CylinderVideo extends Model
{
    protected $fillable = ['title', 'stored_path', 'mime_type', 'size', 'storage_owner_id'];
}
