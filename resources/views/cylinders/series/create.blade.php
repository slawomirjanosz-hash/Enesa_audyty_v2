@extends('layouts.app')
@section('title', 'Nowa seria przeglądów')
@section('page-title', 'Nowa seria przeglądów')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><h1>Nowa seria przeglądów</h1><a class="cyl-btn" href="{{ route('cylinders.index') }}">Wróć do rejestru</a></div>
<form method="post" action="{{ route('cylinders.series.store') }}" class="cyl-card">@csrf
<div class="cyl-grid">
<div class="cyl-wide"><label for="series-name">Nazwa serii *</label><input id="series-name" name="name" value="{{ old('name', 'Przeglądy '.today()->format('d.m.Y')) }}" required maxlength="160">@error('name')<small class="cyl-field-error">{{ $message }}</small>@enderror</div>
<div><label for="series-company">Klient (opcjonalnie)</label><select id="series-company" name="company_id"><option value="">Bez klienta</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>@endforeach</select><small class="cyl-muted">Wybierz klienta, jeśli wszystkie urządzenia są do niego przypisane. Seria nie zmienia przypisania urządzeń.</small>@error('company_id')<small class="cyl-field-error">{{ $message }}</small>@enderror</div>
<div><label for="series-responsible">Osoba prowadząca *</label><select id="series-responsible" name="responsible_id" required>@foreach($staff as $user)<option value="{{ $user->id }}" @selected(old('responsible_id', auth()->id()) == $user->id)>{{ $user->name }}</option>@endforeach</select>@error('responsible_id')<small class="cyl-field-error">{{ $message }}</small>@enderror</div>
</div>
<h2>Wybrane urządzenia ({{ $cylinders->count() }})</h2>
@error('cylinder_ids')<p class="cyl-field-error">{{ $message }}</p>@enderror
<div class="cyl-table"><table><thead><tr><th>Nazwa</th><th>Znak wytwórczy</th><th>Numer fabryczny</th><th>Klient</th></tr></thead><tbody>
@foreach($cylinders as $cylinder)<tr><td>{{ $cylinder->name }}<input type="hidden" name="cylinder_ids[]" value="{{ $cylinder->id }}"></td><td>{{ $cylinder->manufacturer_mark }}</td><td>{{ $cylinder->serial_number }}</td><td>{{ $cylinder->company?->name ?? 'Bez klienta' }}</td></tr>@endforeach
</tbody></table></div><p><button class="cyl-btn cyl-btn-primary">Zapisz serię roboczą</button></p>
</form></div>
@endsection
