@extends($layout)
@section('title', 'Edycja przeglądu')
@section('page-title', 'Inspektor UDT — edycja wpisu')
@section('content')
<div class="cyl-module">@include('cylinders.style')
<div class="cyl-head"><h1>Edytuj wpis #{{ $inspection->id }} · {{ $cylinder->serial_number }}</h1><a class="cyl-btn" href="{{ route('cylinders.show', $cylinder) }}">Anuluj</a></div>
<form method="post" action="{{ route('cylinders.inspections.update', [$cylinder, $inspection]) }}" class="cyl-card">@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ old('revision', $inspection->revision) }}">
<p class="cyl-muted">Autor wpisu: {{ $inspection->inspector_name }}. Zmienione wartości oraz osoba edytująca zostaną zapisane w historii zmian.</p>
<div class="cyl-grid">
<div><label for="edit-date">Data przeglądu</label><input id="edit-date" type="date" name="inspected_at" value="{{ old('inspected_at', $inspection->inspected_at->format('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required></div>
<div><label for="edit-due">Następny termin</label><input id="edit-due" type="date" name="next_due_at" value="{{ old('next_due_at', $inspection->next_due_at?->format('Y-m-d')) }}"></div>
<div class="cyl-wide"><label for="edit-result">Wynik</label><select id="edit-result" name="result" required>@foreach($results as $value=>$label)<option value="{{ $value }}" @selected(old('result', $inspection->result) === $value)>{{ $label }}</option>@endforeach</select></div>
<div class="cyl-wide"><label for="edit-observations">Zakres, pomiary i uwagi</label><textarea id="edit-observations" name="observations" rows="6" maxlength="20000" required>{{ old('observations', $inspection->observations) }}</textarea></div>
</div><p><button class="cyl-btn cyl-btn-primary">Zapisz zmiany</button></p>
</form></div>
@endsection
