@extends($layout)
@section('title', 'Edycja przeglądu')
@section('page-title', 'Przeglądy urządzeń — edycja wpisu')
@section('content')
<div class="cyl-module">@include('cylinders.style')
<div class="cyl-head"><h1>Edytuj wpis #{{ $inspection->id }} · {{ $cylinder->serial_number }}</h1><a class="cyl-btn" href="{{ route('cylinders.show', $cylinder) }}">Anuluj</a></div>
<form method="post" action="{{ route('cylinders.inspections.update', [$cylinder, $inspection]) }}" class="cyl-card" enctype="multipart/form-data">@csrf @method('PUT')
<input type="hidden" name="revision" value="{{ old('revision', $inspection->revision) }}">
<p class="cyl-muted">Autor wpisu: {{ $inspection->inspector_name }}. Zmienione wartości oraz osoba edytująca zostaną zapisane w historii zmian.</p>
@include('cylinders.inspection-fields', ['inspectionForm'=>$inspection])
<p><button class="cyl-btn cyl-btn-primary">Zapisz zmiany</button></p>
</form></div>
@endsection
