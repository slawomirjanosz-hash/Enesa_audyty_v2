<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAttendance extends Model
{
    protected $fillable = ['user_id', 'work_date', 'started_at', 'finished_at', 'status', 'notes'];

    protected $casts = ['work_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
