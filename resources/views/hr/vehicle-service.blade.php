@extends('layouts.app')
@section('page-title', 'Książka serwisowa auta')
@section('content')
<style>
.service-book{max-width:1100px;margin:auto}.service-card{background:white;border:1px solid #e5e1d8;border-radius:12px;padding:24px;margin-bottom:20px}.service-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.service-book h1{font-size:22px}.service-muted{color:#64746d;font-size:13px;margin-top:6px}.service-fields{display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;margin-top:20px}.service-fields label{display:block;font-size:13px;margin-bottom:6px}.service-fields input,.service-fields select,.service-fields textarea{width:100%;border:1px solid #cad6d0;border-radius:7px;padding:10px;font:inherit}.service-description{grid-column:1/-1}.service-button{display:inline-flex;align-items:center;gap:6px;border:1px solid #cad6d0;border-radius:7px;padding:10px 16px;text-decoration:none;cursor:pointer}.service-primary{background:var(--green);color:white}.service-table-wrap{overflow-x:auto}.service-table{width:100%;border-collapse:collapse;font-size:13px}.service-table th,.service-table td{text-align:left;padding:12px;border-bottom:1px solid #e5e1d8;vertical-align:top}.service-table th{background:#fafaf6}.service-note{white-space:pre-wrap;overflow-wrap:anywhere;min-width:180px;max-width:400px}.service-delete{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border:1px solid #fecaca;border-radius:6px;background:#fff;color:#b91c1c;cursor:pointer}.service-errors{color:#b91c1c;margin-top:12px}@media(max-width:650px){.service-fields{grid-template-columns:1fr}.service-card{padding:16px}}
</style>
<div class="service-book">
<div class="service-card service-head"><div><h1>Książka serwisowa auta</h1><p class="service-muted">{{ $vehicle->name }} · {{ $vehicle->registration_number }}</p></div><a class="service-button" href="{{ route('hr.index', ['tab'=>'vehicles']) }}">Wróć do aut</a></div>
@if(session('success'))<p role="status" class="service-card">{{ session('success') }}</p>@endif
@if($canEdit)
<section class="service-card"><h2>Dodaj wykonany serwis</h2>
@if($errors->any())<ul class="service-errors" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<form method="POST" action="{{ route('hr.vehicles.service.store', $vehicle) }}">@csrf
<div class="service-fields">
<div><label for="service-type">Rodzaj czynności</label><select id="service-type" name="type" required>@foreach(\App\Models\HrVehicleServiceEntry::TYPES as $value=>$label)<option value="{{ $value }}" @selected(old('type')===$value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="service-date">Data wykonania</label><input id="service-date" name="performed_on" type="date" required max="{{ today()->format('Y-m-d') }}" value="{{ old('performed_on', today()->format('Y-m-d')) }}"></div>
<div><label for="service-mileage">Przebieg (km)</label><input id="service-mileage" name="mileage" type="number" min="0" max="9999999" step="1" required value="{{ old('mileage') }}"></div>
<div class="service-description"><label for="service-description">Opis / uwagi (wymagane dla innej czynności)</label><textarea id="service-description" name="description" rows="3" maxlength="5000" placeholder="Np. wymiana filtrów, naprawa hamulców, zastosowany olej lub nazwa warsztatu">{{ old('description') }}</textarea></div>
</div><button class="service-button service-primary" style="margin-top:16px" type="submit">Dodaj wpis</button>
</form></section>
@endif
<section class="service-card"><h2>Historia serwisowa</h2><p class="service-muted">Data i przebieg dotyczą wykonanej czynności.</p>
<div class="service-table-wrap"><table class="service-table"><thead><tr><th data-sort-type="date">Data wykonania</th><th>Rodzaj czynności</th><th data-sort-type="number">Przebieg (km)</th><th>Opis / uwagi</th><th>Wpis dodał</th>@if($canDelete)<th data-sortable="false">Akcje</th>@endif</tr></thead><tbody>
@forelse($entries as $entry)<tr><td data-sort-value="{{ $entry->performed_on->format('Y-m-d') }}">{{ $entry->performed_on->format('d.m.Y') }}</td><td>{{ \App\Models\HrVehicleServiceEntry::TYPES[$entry->type] ?? $entry->type }}</td><td data-sort-value="{{ $entry->mileage }}">{{ number_format($entry->mileage, 0, ',', ' ') }}</td><td class="service-note">{{ $entry->description ?: '—' }}</td><td>{{ $entry->author?->name ?? 'Usunięty użytkownik' }}</td>
@if($canDelete)<td><form method="POST" action="{{ route('hr.vehicles.service.destroy', [$vehicle, $entry]) }}" onsubmit="return confirm('Usunąć ten wpis z książki serwisowej?')">@csrf @method('DELETE')<button type="submit" class="service-delete" title="Usuń wpis serwisowy" aria-label="Usuń wpis serwisowy"><i class="ti ti-trash" aria-hidden="true"></i></button></form></td>@endif</tr>
@empty<tr data-sort-fixed><td colspan="{{ $canDelete ? 6 : 5 }}">Brak wpisów w książce serwisowej.</td></tr>@endforelse
</tbody></table></div></section>
</div>
@endsection
