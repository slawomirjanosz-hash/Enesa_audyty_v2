<?php

namespace App\Models;

use App\Support\CylinderVideoLink;
use Illuminate\Database\Eloquent\Model;

class CylinderVideo extends Model
{
    protected $fillable = ['title', 'stored_path', 'mime_type', 'size', 'storage_owner_id', 'external_url', 'cylinder_inspection_id'];

    protected $casts = ['external_url' => 'string', 'size' => 'integer'];

    public function externalPlayer(): ?array
    {
        return $this->external_url ? CylinderVideoLink::parse($this->external_url) : null;
    }
}
