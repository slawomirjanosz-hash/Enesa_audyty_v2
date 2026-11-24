<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProjectWorkOrder extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'text_sections' => 'array', 'price_sections' => 'array', 'stage_ids' => 'array',
        'issuer_snapshot' => 'array', 'supplier_snapshot' => 'array', 'project_snapshot' => 'array',
        'issued_on' => 'date', 'offer_date' => 'date', 'issued_at' => 'datetime', 'confirmed_at' => 'datetime',
        'is_template' => 'boolean', 'revision' => 'integer', 'net_cents' => 'integer', 'vat_cents' => 'integer',
        'history' => 'array', 'document_ids' => 'array', 'attachment_snapshot' => 'array',
        'advance_cents' => 'integer',
    ];

    public const STATUSES = [
        'draft' => 'Robocze', 'issued' => 'Wystawione', 'confirmed' => 'Potwierdzone przez wykonawcę',
        'in_progress' => 'W realizacji', 'received' => 'Odebrane', 'cancelled' => 'Anulowane',
    ];

    public const TRANSITIONS = [
        'draft' => ['issued', 'cancelled'], 'issued' => ['confirmed', 'cancelled'],
        'confirmed' => ['in_progress', 'cancelled'], 'in_progress' => ['received', 'cancelled'],
        'received' => [], 'cancelled' => [],
    ];

    public function filename(string $extension): string
    {
        $clean = fn ($value) => trim(preg_replace('/[^A-Za-z0-9 ._-]+/', '-', Str::ascii($value)), ' .-');

        return $clean($this->number).' - '.mb_substr($clean($this->title), 0, 100).'.'.$extension;
    }

    public static function defaultSections(): array
    {
        return array_map(fn ($name, $index) => ['name' => $name, 'content' => '', 'placement' => $index < 3 ? 'before_price' : 'after_price'], [
            'Podstawa i przedmiot zlecenia', 'Zakres prac', 'Wyłączenia i obowiązki zamawiającego',
            'Warunki płatności i zaliczka', 'Termin realizacji i warunki rozpoczęcia', 'Odbiór prac',
            'Gwarancja', 'Odpowiedzialność i pozostałe uzgodnienia', 'Wykaz załączników',
        ], range(0, 8));
    }
}
