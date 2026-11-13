@if($invoice->exists)
@php($workflow = $invoice->workflow_status)
@php($methods = \App\Models\ExpenseInvoice::SETTLEMENT_METHODS)
<section class="exp-card">
    <h2>Status rozliczenia: {{ \App\Models\ExpenseInvoice::WORKFLOW[$workflow] ?? 'Poza obiegiem' }}</h2>
    @if($invoice->settlement_method)
        <p>{{ $methods[$invoice->settlement_method] }} · {{ number_format((float)$invoice->settlement_amount, 2, ',', ' ') }} zł</p>
    @endif
    @if($accounting && $invoice->owner_id && (in_array($workflow,['read','disputed'],true) || ($workflow === null && !$invoice->settled_at)))
        <form method="POST" action="{{route('expenses.workflow',$invoice)}}" data-form-draft="false" class="exp-filters">
            @csrf
            <input type="hidden" name="action" value="settled">
            <input type="hidden" name="revision" value="{{$invoice->revision}}">
            <label>Sposób rozliczenia <select name="settlement_method" required><option value="">Wybierz</option>@foreach($methods as $value=>$label)<option value="{{$value}}">{{$label}}</option>@endforeach</select></label>
            <label>Kwota rozliczenia (zł) <input name="settlement_amount" type="number" min="0" step="0.01" required></label>
            <button class="exp-btn primary">Rozlicz fakturę</button>
        </form>
        <details><summary>Odeślij do uzupełnienia</summary>
            <form method="POST" action="{{route('expenses.workflow',$invoice)}}" data-form-draft="false" class="exp-filters">
                @csrf
                <input type="hidden" name="action" value="returned">
                <input type="hidden" name="revision" value="{{$invoice->revision}}">
                <label>Co uzupełnić? <textarea name="note" required maxlength="1000"></textarea></label>
                <button class="exp-btn">Odeślij</button>
            </form>
        </details>
    @elseif($workflow === 'settled' && $invoice->owner_id === auth()->id())
        <form method="POST" action="{{route('expenses.workflow',$invoice)}}" data-form-draft="false">
            @csrf
            <input type="hidden" name="action" value="closed">
            <input type="hidden" name="revision" value="{{$invoice->revision}}">
            <button class="exp-btn primary">Potwierdzam rozliczenie</button>
        </form>
        <details><summary>Zgłoś rozbieżność</summary>
            <form method="POST" action="{{route('expenses.workflow',$invoice)}}" data-form-draft="false" class="exp-filters">
                @csrf
                <input type="hidden" name="action" value="disputed">
                <input type="hidden" name="revision" value="{{$invoice->revision}}">
                <label>Opisz rozbieżność <textarea name="note" required maxlength="1000"></textarea></label>
                <button class="exp-btn">Zgłoś</button>
            </form>
        </details>
    @elseif($workflow === 'returned' && $editable)
        <p>Uzupełnij dane i zapisz fakturę, aby wróciła do księgowości.</p>
    @endif
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
