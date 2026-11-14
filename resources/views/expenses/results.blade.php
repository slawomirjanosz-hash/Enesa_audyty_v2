<div class="exp-card exp-table-wrap">
<table class="exp-table" data-server-sort="purchase_date,invoice_number,gross_amount,owner_name,cost_center,payment_label,workflow_status,">
<thead><tr>
<th>Data zakupu</th><th>Faktura</th><th>Brutto</th><th>Kupujący</th><th>Projekt / miejsce kosztu</th><th>Jak zapłacono</th><th>Status</th><th data-sortable="false">Działania</th>
</tr></thead>
<tbody>
@forelse($invoices as $invoice)
<tr>
<td data-sort-value="{{ $invoice->purchase_date->toDateString() }}">{{ $invoice->purchase_date->format('d.m.Y') }}</td>
<td><a href="{{ route('expenses.edit', $invoice) }}">{{ $invoice->invoice_number ?: 'Bez numeru' }}</a></td>
<td class="exp-number" data-sort-value="{{ $invoice->gross_amount }}">{{ $invoice->gross_amount !== null ? number_format((float) $invoice->gross_amount, 2, ',', ' ') : '—' }}</td>
<td>{{ $invoice->owner_name ?: 'Nieprzypisana' }}</td>
<td>{{ $invoice->cost_center ?: '—' }}</td>
<td>{{ $invoice->payment_label }}</td>
<td><span class="exp-badge">{{ \App\Models\ExpenseInvoice::WORKFLOW[$invoice->workflow_status] ?? '—' }}</span></td><td>
@if($accounting && $invoice->owner_id !== auth()->id() && $invoice->workflow_status === 'new')
    <form method="POST" action="{{ route('expenses.workflow', $invoice) }}" data-form-draft="false">
        @csrf
        <input type="hidden" name="action" value="read">
        <input type="hidden" name="revision" value="{{ $invoice->revision }}">
        <input type="hidden" name="from_list" value="1">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <button class="exp-btn" type="submit">Oznacz jako odczytaną</button>
    </form>
@elseif(($accounting && $invoice->owner_id && $invoice->owner_id !== auth()->id() && (in_array($invoice->workflow_status, ['read', 'disputed'], true) || ($invoice->workflow_status === null && !$invoice->settled_at))) || ($invoice->owner_id === auth()->id() && in_array($invoice->workflow_status, ['settled', 'returned'], true)))
    <details class="exp-list-workflow">
        <summary>{{ $invoice->workflow_status === 'returned' ? 'Uzupełnij' : 'Rozliczenie' }}</summary>
        @include('expenses.workflow-actions', ['workflow' => $invoice->workflow_status, 'methods' => \App\Models\ExpenseInvoice::SETTLEMENT_METHODS])
    </details>
@endif
<a class="exp-btn" href="{{route('expenses.edit',$invoice)}}">{{$invoice->settled_at || (!$accounting && $invoice->owner_id!==auth()->id()) ? 'Podgląd' : 'Edytuj'}}</a>@if($invoice->is_unassigned && !$invoice->owner_id && !$invoice->settled_at)@include('expenses.claim')@endif @include('expenses.delete')</td>
</tr>@empty<tr><td colspan="8">Brak faktur spełniających wybrane kryteria.</td></tr>@endforelse
</tbody></table></div>{{$invoices->links()}}
