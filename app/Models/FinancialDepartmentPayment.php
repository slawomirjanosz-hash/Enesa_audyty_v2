<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialDepartmentPayment extends Model
{
    protected $fillable = ['financial_department_id', 'direction', 'paid_on', 'amount', 'description', 'created_by'];

    protected $casts = ['paid_on' => 'date', 'amount' => 'encrypted'];
}
