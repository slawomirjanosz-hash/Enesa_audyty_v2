<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRequirement extends Model
{
    protected $fillable = [
        'project_id', 'type', 'name', 'description', 'quantity', 'unit',
        'estimated_cost', 'supplier', 'supplier_company_id', 'status', 'needed_by', 'responsible_id', 'created_by',
    ];

    protected $casts = ['quantity' => 'decimal:2', 'estimated_cost' => 'decimal:2', 'needed_by' => 'date'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function supplierCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    public function formattedQuantity(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantity, 2, ',', ' '), '0'), ',');
    }

    public function displayUnit(): string
    {
        $unit = trim((string) $this->unit);

        return $unit === '' || is_numeric(str_replace(',', '.', $unit))
            ? ($this->type === 'material' ? 'szt.' : 'usł.')
            : $unit;
    }
}
