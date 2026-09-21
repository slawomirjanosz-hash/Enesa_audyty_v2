<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    public function pdfFilename(): string
    {
        $clean = fn ($value) => trim(preg_replace('/[^A-Za-z0-9 ._-]+/', '-', Str::ascii((string) $value)), ' .-');
        $number = $clean($this->number) ?: 'Protokol';
        $description = $clean(preg_replace('/\s+/u', ' ', (string) $this->description)) ?: 'Odbior';

        return substr($number, 0, 65).' - '.rtrim(substr($description, 0, 120), ' .-').'.pdf';
    }
}
