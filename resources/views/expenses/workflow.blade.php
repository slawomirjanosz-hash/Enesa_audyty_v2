@if($invoice->exists)
@php($workflow = $invoice->workflow_status)
@php($methods = \App\Models\ExpenseInvoice::SETTLEMENT_METHODS)
<section class="exp-card">
    <h2>Status rozliczenia: {{ \App\Models\ExpenseInvoice::WORKFLOW[$workflow] ?? 'Poza obiegiem' }}</h2>
    @if($invoice->settlement_method)
        <p>{{ $methods[$invoice->settlement_method] }} · {{ number_format((float)$invoice->settlement_amount, 2, ',', ' ') }} zł</p>
    @endif
    @include('expenses.workflow-actions')
    @if($invoice->workflow_history && ($accounting || $invoice->owner_id === auth()->id()))
        <h3>Historia rozliczenia</h3>
        <ul>@foreach(array_reverse($invoice->workflow_history) as $event)
            <li>{{$event['at']}} · {{$event['actor']}} · {{\App\Models\ExpenseInvoice::WORKFLOW[$event['status']]}}
                @if($event['method'] ?? null) · {{$methods[$event['method']]}} ({{$event['amount']}} zł) @endif
                @if($event['note']) — {{$event['note']}} @endif
            </li>
        @endforeach</ul>
    @endif
</section>
@endif
