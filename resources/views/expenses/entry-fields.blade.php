<input type="hidden" name="simple_entry" value="1">
@php
    $choice = old('payment_choice', match (true) {
        !$invoice->exists => '',
        !$invoice->simple_entry => 'unchanged',
        $invoice->payment_status === 'unpaid' => 'unpaid',
        $invoice->payment_status === 'paid' && in_array($invoice->payment_source, ['company', 'personal'], true) => $invoice->payment_source,
        default => 'unchanged',
    });
    $projectId = old('project_id', $invoice->project_id);
@endphp
<label class="exp-field" data-expense-step>Data zakupu *<input name="purchase_date" type="date" max="{{ today()->toDateString() }}" value="{{old('purchase_date',$invoice->purchase_date?->toDateString())}}" required></label>
<label class="exp-field" data-expense-step>Numer faktury (opcjonalnie)<input name="invoice_number" value="{{old('invoice_number',$invoice->invoice_number)}}" maxlength="255"></label>
<label class="exp-field" data-expense-step>Kwota brutto (zł) *<input name="gross_amount" type="number" inputmode="decimal" min="0" max="999999999999.99" step="0.01" value="{{old('gross_amount',$invoice->gross_amount)}}" required></label>
<label class="exp-field" data-expense-step>Kto zapłacił<input name="paid_by" value="{{old('paid_by',$invoice->exists ? $invoice->paid_by : auth()->user()->name)}}" maxlength="255"></label>
<div class="exp-field" data-expense-step>
    <label>Projekt<select name="project_id">
        <option value="">Bez projektu – wpisz miejsce kosztu</option>
        @if($invoice->project_id && !$projects->contains('id',$invoice->project_id))
            <option value="{{$invoice->project_id}}" @selected((string)$projectId===(string)$invoice->project_id)>Dotychczasowy projekt</option>
        @endif
        @foreach($projects as $project)<option value="{{$project->id}}" @selected((string)$projectId===(string)$project->id)>{{$project->number}} · {{$project->name}}</option>@endforeach
    </select></label>
    <label data-expense-manual-cost>Miejsce kosztu<input name="cost_center" value="{{old('cost_center',$invoice->cost_center)}}" maxlength="255"></label>
</div>
<label class="exp-field" data-expense-step>Jak zapłacono? *<select name="payment_choice" required>
    <option value="">Wybierz</option>
    @if($invoice->exists && !$invoice->simple_entry)<option value="unchanged" @selected($choice==='unchanged')>Zachowaj dotychczasowe dane płatności</option>@endif
    @foreach(['personal'=>'Prywatnie','company'=>'Służbowo','unpaid'=>'Nieopłacono'] as $value=>$label)<option value="{{$value}}" @selected($choice===$value)>{{$label}}</option>@endforeach
</select></label>
<label class="exp-field wide" data-expense-step>Uwagi<textarea name="description" rows="3" maxlength="5000">{{old('description',$invoice->description)}}</textarea></label>
