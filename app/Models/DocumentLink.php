<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentLink extends Model
{
    protected $fillable = ['audit_id', 'project_id', 'name', 'url', 'created_by'];
}
