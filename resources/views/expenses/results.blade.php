<div class="exp-card exp-table-wrap">
<table class="exp-table" data-action-icons="false" data-server-sort="purchase_date,invoice_number,gross_amount,owner_name,cost_center,payment_label,workflow_status,">
<thead><tr>
@foreach(['Data zakupu', 'Faktura', 'Brutto', 'Kupujący', 'Projekt / miejsce kosztu', 'Jak zapłacono', 'Status'] as $heading)
    <th><span class="app-table-sort">{{ $heading }} <span class="app-table-sort-marker" aria-hidden="true">↕</span></span></th>
@endforeach
<th data-sortable="false">Działania</th>
</tr></thead>
<tbody>
@forelse($invoices as $invoice)
<tr>
<td data-sort-value="{{ $invoice->purchase_date->toDateString() }}">{{ $invoice->purchase_date->format('d.m.Y') }}</td>
<td><a href="{{ route('expenses.edit', ['invoice' => $invoice, 'tab' => $tab]) }}">{{ $invoice->invoice_number ?: 'Bez numeru' }}</a></td>
<td class="exp-number" data-sort-value="{{ $invoice->gross_amount }}">{{ $invoice->gross_amount !== null ? number_format((float) $invoice->gross_amount, 2, ',', ' ') : '—' }}</td>
<td>{{ $invoice->owner_name ?: 'Nieprzypisana' }}</td>
<td>{{ $invoice->cost_center ?: '—' }}</td>
<td>{{ $invoice->payment_label }}</td>
<td><span @class(['exp-badge', 'exp-badge-success' => $accounting && $invoice->workflow_status === 'closed'])>{{ $workflowLabels[$invoice->workflow_status] ?? '—' }}</span></td><td><div class="exp-row-actions">
@if($accounting && ($tab === 'all' || $invoice->owner_id !== auth()->id()) && $invoice->workflow_status === 'new')
    <form method="POST" action="{{ route('expenses.workflow', $invoice) }}" data-form-draft="false">
        @csrf
        <input type="hidden" name="action" value="read">
        <input type="hidden" name="revision" value="{{ $invoice->revision }}">
        <input type="hidden" name="from_list" value="1">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <button class="exp-btn" type="submit"><i class="ti ti-check" aria-hidden="true"></i> Oznacz jako odczytaną</button>
    </form>
@elseif(($accounting && $invoice->owner_id && ($tab === 'all' || $invoice->owner_id !== auth()->id()) && (in_array($invoice->workflow_status, ['read', 'disputed'], true) || ($invoice->workflow_status === null && !$invoice->settled_at))) || ($invoice->owner_id === auth()->id() && in_array($invoice->workflow_status, ['settled', 'returned'], true)))
    @include('expenses.workflow-actions', ['workflow' => $invoice->workflow_status, 'methods' => \App\Models\ExpenseInvoice::SETTLEMENT_METHODS])
@endif
@php($preview = $invoice->settled_at || (!$accounting && $invoice->owner_id !== auth()->id()))
<a class="exp-btn exp-icon-btn" href="{{ route('expenses.edit', ['invoice' => $invoice, 'tab' => $tab]) }}" aria-label="{{ $preview ? 'Podgląd' : 'Edytuj' }}" title="{{ $preview ? 'Podgląd' : 'Edytuj' }}">
    <i @class(['ti', 'ti-eye' => $preview, 'ti-pencil' => !$preview]) aria-hidden="true"></i>
</a>
@if($invoice->is_unassigned && !$invoice->owner_id && !$invoice->settled_at)
    @include('expenses.claim')
@endif
@include('expenses.delete', ['iconOnly' => true])
</div></td>
</tr>@empty<tr><td colspan="8">Brak faktur spełniających wybrane kryteria.</td></tr>@endforelse
</tbody></table></div>{{$invoices->links()}}
