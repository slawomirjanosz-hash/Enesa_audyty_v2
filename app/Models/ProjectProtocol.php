<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProjectProtocol extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = ['direction' => 'supplier'];

    protected $casts = ['acceptance_date' => 'date', 'remedy_deadline' => 'date', 'items' => 'array', 'issuer_snapshot' => 'array', 'supplier_snapshot' => 'array', 'client_snapshot' => 'array', 'revision' => 'integer'];

    public const DIRECTIONS = ['supplier' => 'Odbiór od dostawcy', 'client' => 'Odbiór u klienta'];

    public function isClientAcceptance(): bool
    {
        return $this->direction === 'client';
    }

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
        $title = $clean(preg_replace('/\s+/u', ' ', (string) $this->title));

        return substr($number, 0, 65).($title !== '' ? ' - '.rtrim(substr($title, 0, 120), ' .-') : '').'.pdf';
    }
}
