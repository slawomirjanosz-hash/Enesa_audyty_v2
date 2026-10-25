<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveApprovalTask extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['archived_at' => 'datetime'];

    public function leave(): BelongsTo
    {
        return $this->belongsTo(HrLeave::class, 'hr_leave_id');
    }
}
