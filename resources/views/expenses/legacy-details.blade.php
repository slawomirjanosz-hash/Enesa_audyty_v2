@php
    $legacy = array_filter([
        'Numer WZ' => $invoice->wz_number,
        'Numer KSeF' => $invoice->ksef_number,
        'Dostawca' => $invoice->supplier_name === '—' ? null : $invoice->supplier_name,
        'NIP' => $invoice->supplier_nip,
        'Netto (zł)' => $invoice->net_amount,
        'VAT (%)' => $invoice->vat_rate,
        'Metoda płatności' => $invoice->payment_method === 'unknown' ? null : (\App\Models\ExpenseInvoice::METHODS[$invoice->payment_method] ?? null),
    ], fn ($value) => filled($value));
@endphp
@if($legacy)
    <details class="exp-card">
        <summary>Dane ze starszego formularza</summary>
        <dl>
            @foreach($legacy as $label => $value)
                <dt>{{ $label }}</dt>
                <dd>{{ $value }}</dd>
            @endforeach
        </dl>
    </details>
@endif
