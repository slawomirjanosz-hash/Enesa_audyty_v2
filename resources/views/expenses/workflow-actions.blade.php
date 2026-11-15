    @if($accounting && $invoice->owner_id && $invoice->owner_id !== auth()->id() && (in_array($workflow,['read','disputed'],true) || ($workflow === null && !$invoice->settled_at)))
        <form method="POST" action="{{ route('expenses.workflow', $invoice) }}" data-form-draft="false" class="exp-operation">
            @csrf
            @isset($tab)<input type="hidden" name="from_list" value="1"><input type="hidden" name="tab" value="{{ $tab }}">@endisset
            <input type="hidden" name="action" value="settled">
            <input type="hidden" name="revision" value="{{ $invoice->revision }}">
            <select name="settlement_method" required aria-label="Wybierz działanie" data-expense-operation>
                <option value="">Wybierz działanie</option>
                @foreach($methods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                <option value="returned">Zastrzeżenia</option>
            </select>
            <button class="exp-btn primary" type="submit">Wykonaj</button>
            <dialog class="exp-card exp-note-dialog" aria-label="Zastrzeżenia">
                <label class="exp-field">Co wymaga poprawy?<textarea name="note" rows="4" maxlength="1000" disabled></textarea></label>
                <div class="exp-actions">
                    <button class="exp-btn" type="button" data-expense-cancel>Anuluj</button>
                    <button class="exp-btn primary" type="submit">Odeślij do uzupełnienia</button>
                </div>
            </dialog>
        </form>
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
