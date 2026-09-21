<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectProtocol extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['acceptance_date' => 'date', 'remedy_deadline' => 'date', 'items' => 'array', 'issuer_snapshot' => 'array', 'supplier_snapshot' => 'array', 'revision' => 'integer'];

    public const KINDS = ['partial' => 'Odbiór częściowy', 'final' => 'Odbiór końcowy'];

    public const OUTCOMES = ['accepted' => 'Odebrano bez uwag', 'reserved' => 'Odebrano z uwagami', 'rejected' => 'Odmowa odbioru'];

    public const INVOICES = ['no' => 'Nie można wystawić faktury', 'yes' => 'Można wystawić fakturę', 'conditional' => 'Po spełnieniu warunków'];

    public function totals(): array
    {
        $net = array_sum(array_column($this->items ?? [], 'net_cents'));
        $vat = array_sum(array_column($this->items ?? [], 'vat_cents'));

        return ['net' => $net, 'vat' => $vat, 'gross' => $net + $vat];
    }
}
