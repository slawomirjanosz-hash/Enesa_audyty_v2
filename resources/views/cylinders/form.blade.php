@extends($layout)
@section('title', 'Dane butli')
@section('page-title', 'Dane butli')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><h1>{{ $cylinder->exists ? 'Edytuj butlę' : 'Dodaj butlę' }}</h1><a class="cyl-btn" href="{{ $cylinder->exists ? route('cylinders.show', $cylinder) : route('cylinders.index') }}">Wróć</a></div>
<form class="cyl-card" action="{{ $cylinder->exists ? route('cylinders.update', $cylinder) : route('cylinders.store') }}" method="post">@csrf @if($cylinder->exists)@method('PUT')@endif
<div class="cyl-grid">
<div class="cyl-wide">@if($cylinder->exists)<label>Firma właściciela</label><p>{{ $cylinder->company->name }}</p><small class="cyl-muted">Właściciel jest stały, aby historia nie przechodziła pomiędzy klientami.</small>@else<label for="company_id">Firma właściciela *</label><select id="company_id" name="company_id" required><option value="">Wybierz firmę</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>@endforeach</select><small>Firmy dodajesz w CRM.</small>@endif</div>
@foreach(['serial_number'=>'Numer seryjny', 'type'=>'Typ butli / zastosowanie', 'manufacturer'=>'Producent', 'manufactured_year'=>'Rok produkcji', 'capacity_litres'=>'Pojemność [l]', 'working_pressure_bar'=>'Ciśnienie robocze [bar]'] as $field=>$label)
<div><label for="{{ $field }}">{{ $label }} {{ in_array($field, ['serial_number','type']) ? '*' : '' }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $cylinder->$field) }}" @required(in_array($field,['serial_number','type'])) @if(in_array($field,['manufactured_year','capacity_litres','working_pressure_bar'])) type="number" step="{{ $field === 'manufactured_year' ? '1' : '0.001' }}" min="{{ $field === 'manufactured_year' ? '1900' : '0.001' }}" max="{{ $field === 'manufactured_year' ? now()->year : '9999999' }}" @else maxlength="{{ $field === 'serial_number' ? '100' : '160' }}" @endif></div>
@endforeach
<div class="cyl-wide"><label for="notes">Uwagi o urządzeniu</label><textarea id="notes" name="notes" rows="4" maxlength="10000">{{ old('notes', $cylinder->notes) }}</textarea></div>
</div><p><button class="cyl-btn cyl-btn-primary" type="submit">Zapisz butlę</button></p>
</form></div>
@endsection
