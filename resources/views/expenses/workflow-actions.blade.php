    @if($accounting && $invoice->owner_id && (in_array($workflow,['read','disputed'],true) || ($workflow === null && !$invoice->settled_at)))
        <form method="POST" action="{{route('expenses.workflow',$invoice)}}" data-form-draft="false" class="exp-workflow-fields">
            @csrf
            @isset($tab)<input type="hidden" name="from_list" value="1"><input type="hidden" name="tab" value="{{ $tab }}">@endisset
            <input type="hidden" name="action" value="settled">
            <input type="hidden" name="revision" value="{{$invoice->revision}}">
            <label>Sposób rozliczenia <select name="settlement_method" required data-settlement-method data-gross="{{ $invoice->gross_amount }}"><option value="">Wybierz</option>@foreach($methods as $value=>$label)<option value="{{$value}}">{{$label}}</option>@endforeach</select></label>
            <label>Kwota rozliczenia (zł) <input name="settlement_amount" type="number" min="0" step="0.01" required></label>
            <button class="exp-btn primary">Rozlicz fakturę</button>
        </form>
        <details><summary>Odeślij do uzupełnienia</summary>
            <form method="POST" action="{{route('expenses.workflow',$invoice)}}" data-form-draft="false" class="exp-workflow-fields">
                @csrf
            @isset($tab)<input type="hidden" name="from_list" value="1"><input type="hidden" name="tab" value="{{ $tab }}">@endisset
                <input type="hidden" name="action" value="returned">
                <input type="hidden" name="revision" value="{{$invoice->revision}}">
                <label>Co uzupełnić? <textarea name="note" required maxlength="1000"></textarea></label>
                <button class="exp-btn">Odeślij</button>
            </form>
        </details>
    @elseif($workflow === 'settled' && $invoice->owner_id === auth()->id())
        <form method="POST" action="{{route('expenses.workflow',$invoice)}}" data-form-draft="false">
            @csrf
            @isset($tab)<input type="hidden" name="from_list" value="1"><input type="hidden" name="tab" value="{{ $tab }}">@endisset
            <input type="hidden" name="action" value="closed">
            <input type="hidden" name="revision" value="{{$invoice->revision}}">
            <button class="exp-btn primary">Potwierdzam rozliczenie</button>
        </form>
        <details><summary>Zgłoś rozbieżność</summary>
            <form method="POST" action="{{route('expenses.workflow',$invoice)}}" data-form-draft="false" class="exp-workflow-fields">
                @csrf
            @isset($tab)<input type="hidden" name="from_list" value="1"><input type="hidden" name="tab" value="{{ $tab }}">@endisset
                <input type="hidden" name="action" value="disputed">
                <input type="hidden" name="revision" value="{{$invoice->revision}}">
                <label>Opisz rozbieżność <textarea name="note" required maxlength="1000"></textarea></label>
                <button class="exp-btn">Zgłoś</button>
            </form>
        </details>
    @elseif($workflow === 'returned' && $invoice->owner_id === auth()->id())
        @isset($tab)<a href="{{ route('expenses.edit', $invoice) }}">Uzupełnij dane</a>@else<p>Uzupełnij dane i zapisz fakturę, aby wróciła do księgowości.</p>@endisset
    @endif
