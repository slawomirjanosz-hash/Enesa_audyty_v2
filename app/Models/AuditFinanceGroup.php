<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditFinanceGroup extends Model
{
    protected $fillable = ['audit_id', 'name'];

    public function entries(): HasMany
    {
        return $this->hasMany(AuditFinancialEntry::class, 'finance_group_id');
    }
}
