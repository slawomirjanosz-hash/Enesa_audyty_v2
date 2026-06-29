<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'body',
        'is_read',
        'conversation_id',
        'conversation_ended_at',
        'ended_by',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'conversation_ended_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActiveConversation(Builder $query, int $companyId): Builder
    {
        return $query
            ->where('company_id', $companyId)
            ->whereNull('conversation_ended_at');
    }

    public function scopeArchivedConversations(Builder $query, int $companyId): Builder
    {
        return $query
            ->where('company_id', $companyId)
            ->whereNotNull('conversation_ended_at')
            ->groupBy('conversation_id');
    }
}
