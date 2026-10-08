@extends($layout)
@section('title', 'Dane urządzenia')
@section('page-title', 'Dane urządzenia')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><div><h1>{{ $cylinder->exists ? 'Edytuj urządzenie' : 'Dodaj urządzenie' }}</h1><p class="cyl-muted">Butla — dane identyfikacyjne i parametry stałe</p></div><a class="cyl-btn" href="{{ $cylinder->exists ? route('cylinders.show', $cylinder) : route('cylinders.index') }}">Wróć</a></div>
<form class="cyl-card" action="{{ $cylinder->exists ? route('cylinders.update', $cylinder) : route('cylinders.store') }}" method="post">
@csrf
@if($cylinder->exists)@method('PUT')@endif
@if($cylinder->exists && !$cylinder->manufacturer_mark)<p class="cyl-notice">Uzupełnij znak wytwórczy z oznaczenia butli. Dotychczasowa nazwa producenta została zachowana osobno.</p>@endif
<div class="cyl-grid">
<div class="cyl-wide">
@if($cylinder->exists)
<label>Klient</label><p>{{ $cylinder->company?->name ?? 'Bez przypisanego klienta' }}</p>@if($cylinder->company_id)<small class="cyl-muted">Historia urządzenia pozostaje przypisana do tego klienta.</small>@endif
@else
<label for="company_id">Klient (opcjonalnie)</label><select id="company_id" name="company_id" @if($errors->has('company_id')) aria-invalid="true" @endif><option value="">Bez przypisanego klienta</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>@endforeach</select>
@error('company_id')<small class="cyl-field-error">{{ $message }}</small>@enderror
@endif
</div>
</div>
@foreach([
    'Identyfikacja' => ['name','manufacturer_mark','serial_number','manufactured_year','inventory_number','manufacturer'],
    'Parametry techniczne' => ['working_medium','temperature_min_c','temperature_max_c','capacity_litres','working_pressure_bar','test_pressure_bar'],
    'Masy znamionowe i osprzęt' => ['tare_or_gross_mass_kg','net_mass_kg','stamped_empty_mass_kg','filling_mass_symbol','equipment_type','equipment_mark'],
] as $group => $fields)
<fieldset class="cyl-parameter-group"><legend>{{ $group }}</legend><div class="cyl-grid">
@foreach($fields as $field)
@php
    $required = in_array($field, ['name','manufacturer_mark','serial_number']);
    $numeric = in_array($field, \App\Models\Cylinder::DECIMAL_PARAMETERS) || $field === 'manufactured_year';
    $temperature = in_array($field, ['temperature_min_c','temperature_max_c']);
@endphp
<div>
<label for="{{ $field }}">{{ \App\Models\Cylinder::PARAMETER_LABELS[$field] }}{{ $required ? ' *' : '' }}</label>
<input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $cylinder->$field) }}" @required($required)
@if($numeric) type="number" step="{{ $field === 'manufactured_year' ? '1' : ($temperature ? '0.01' : '0.001') }}" min="{{ $field === 'manufactured_year' ? '1900' : ($temperature ? '-273.15' : (in_array($field, ['capacity_litres','working_pressure_bar','test_pressure_bar']) ? '0.001' : '0')) }}" max="{{ $field === 'manufactured_year' ? now()->year : ($temperature ? '99999.99' : '9999999') }}"
@else type="text" maxlength="{{ $field === 'serial_number' ? '100' : '160' }}" @endif
@if($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif>
@error($field)<small class="cyl-field-error" id="{{ $field }}-error">{{ $message }}</small>@enderror
</div>
@endforeach
</div></fieldset>
@endforeach
<label for="notes">Uwagi o urządzeniu</label><textarea id="notes" name="notes" rows="3" maxlength="10000" @if($errors->has('notes')) aria-invalid="true" @endif>{{ old('notes', $cylinder->notes) }}</textarea>
@error('notes')<small class="cyl-field-error">{{ $message }}</small>@enderror
<p><button class="cyl-btn cyl-btn-primary" type="submit">Zapisz urządzenie</button></p>
</form></div>
@endsection
