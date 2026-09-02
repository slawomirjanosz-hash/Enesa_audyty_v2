<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveEntitlement extends Model
{
    protected $fillable = ['user_id', 'year', 'entitled_days'];

    protected $casts = ['year' => 'integer', 'entitled_days' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
