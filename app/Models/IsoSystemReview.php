<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoSystemReview extends Model
{
    public const TITLES = ['4-3' => '4.3 Zakres i granice systemu D-EnMS-ZAK-01', '4-4' => '4.4 System zarządzania energią D-EnMS-SYS-01'];

    protected $guarded = ['id'];

    protected $casts = ['answers' => 'array', 'source_snapshot' => 'array', 'client_approval' => 'array', 'auditor_approval' => 'array', 'client_changes' => 'array', 'auditor_changes' => 'array', 'issuer' => 'array', 'lock_version' => 'integer'];
}
