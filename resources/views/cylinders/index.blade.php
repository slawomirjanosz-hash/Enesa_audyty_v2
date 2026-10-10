@extends($layout)
@section('title', 'Przeglądy urządzeń')
@section('page-title', 'Przeglądy urządzeń')
@section('content')
<div class="cyl-module cyl-register">
@include('cylinders.style')
<div class="cyl-head">
<div><h1>{{ $clientView ? 'Moje urządzenia i przeglądy' : 'Rejestr urządzeń' }}</h1><p class="cyl-muted">Butle — identyfikacja i ostatni przegląd.</p></div>
@if($canManage)<a class="cyl-btn cyl-btn-primary" href="{{ route('cylinders.create') }}">+ Dodaj urządzenie</a>@endif
</div>
<div class="cyl-card cyl-register-card">
<div class="cyl-register-toolbar">
<nav class="cyl-register-tabs" aria-label="Widok rejestru">
<a href="{{ route($routePrefix.'index', request()->only('q','company_id')) }}" @if(!request('archived')) aria-current="page" @endif>Aktywne urządzenia</a>
<a href="{{ route($routePrefix.'index', request()->only('q','company_id') + ['archived'=>1]) }}" @if(request('archived')) aria-current="page" @endif>Archiwum</a>
</nav>
<form method="get" class="cyl-register-search cyl-register-filters" role="search">
<label for="cyl-company" class="cyl-sr-only">Klient</label>
<select id="cyl-company" name="company_id"><option value="">Wszyscy dostępni klienci</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->name }}</option>@endforeach</select>
<label for="cyl-search" class="cyl-sr-only">Szukaj nazwy lub numeru fabrycznego</label>
<input id="cyl-search" type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Nazwa lub numer fabryczny…">
@if(request('archived'))<input type="hidden" name="archived" value="1">@endif
<button class="cyl-btn" type="submit">Szukaj</button>
</form>
</div>
@if($canManage)<div class="cyl-selection-summary" data-cylinder-selection hidden role="status" aria-live="polite">Wybrano butle: <strong data-cylinder-count>0</strong><button type="button" class="cyl-btn" data-cylinder-clear>Wyczyść zaznaczenie</button></div>@endif
<div class="cyl-table" tabindex="0" role="region" aria-label="Lista urządzeń — przewijana poziomo na małym ekranie">
<table class="cyl-register-table" data-cylinder-register data-server-sort=",name,manufacturer,year,serial,last,,,">
<thead><tr>
<th data-sortable="false">@if($canManage)<input type="checkbox" data-cylinder-select-all aria-label="Zaznacz wszystkie widoczne butle">@endif</th>
<th>Nazwa</th><th>Znak wytwórczy</th><th>Rok produkcji</th><th>Nr fabryczny</th><th>Data ostatniego przeglądu</th>
<th data-sortable="false"><span class="cyl-sr-only">Film</span><i class="ti ti-video" aria-hidden="true"></i></th>
<th data-sortable="false"><span class="cyl-sr-only">Zdjęcia</span><i class="ti ti-photo" aria-hidden="true"></i></th>
<th data-sortable="false"><span class="cyl-sr-only">Protokół</span><i class="ti ti-file-type-pdf" aria-hidden="true"></i></th>
</tr></thead><tbody>
@forelse($cylinders as $cylinder)
<tr data-cylinder-url="{{ route($routePrefix.'show', $cylinder) }}" tabindex="0" aria-label="Otwórz kartę: {{ $cylinder->name }}, {{ $cylinder->serial_number }}">
<td>@if($canManage)<input type="checkbox" data-cylinder-select value="{{ $cylinder->id }}" data-company-id="{{ $cylinder->company_id }}" aria-label="Zaznacz butlę {{ $cylinder->serial_number }}">@endif</td>
<td><a class="cyl-serial" href="{{ route($routePrefix.'show', $cylinder) }}">{{ $cylinder->name }}</a><span class="cyl-secondary">{{ $cylinder->company?->name }}</span></td>
<td>{{ $cylinder->manufacturer_mark ?? 'Do uzupełnienia' }}</td>
<td>{{ $cylinder->manufactured_year ?? '—' }}</td>
<td>{{ $cylinder->serial_number }}</td>
<td class="cyl-date">{{ $cylinder->latestInspection?->inspected_at?->format('d.m.Y') ?? 'Brak przeglądu' }}
@if($cylinder->latestInspection?->next_due_at)<span class="cyl-secondary">Termin: <span class="cyl-due cyl-due-{{ $cylinder->dueStatus() }}">{{ $cylinder->latestInspection->next_due_at->format('d.m.Y') }}</span></span>@endif
<span class="cyl-secondary"><span class="cyl-status-chip cyl-status-{{ $cylinder->conditionStatus() }}">{{ $cylinder->conditionLabel() }}</span></span>
</td>
@foreach(['videos'=>'video', 'photos'=>'photo'] as $relation=>$icon)
<td>@if($cylinder->latestInspection && $cylinder->latestInspection->{$relation.'_count'})
<a class="cyl-media-icon" href="{{ route($routePrefix.'inspections.media', [$cylinder, $cylinder->latestInspection]) }}" data-inspection-media aria-label="{{ $relation === 'videos' ? 'Film ostatniego przeglądu' : 'Zdjęcia ostatniego przeglądu' }}"><i class="ti ti-{{ $icon }}" aria-hidden="true"></i></a>
@else<span class="cyl-media-icon is-empty" aria-disabled="true" title="Brak załącznika"><i class="ti ti-{{ $icon }}" aria-hidden="true"></i></span>@endif</td>
@endforeach
<td><span class="cyl-media-icon is-empty" aria-disabled="true" title="Protokół PDF — dostępny w kolejnym etapie"><i class="ti ti-file-type-pdf" aria-hidden="true"></i></span></td>
</tr>
@empty<tr><td colspan="9" class="cyl-empty">Brak urządzeń spełniających wybrane kryteria.</td></tr>@endforelse
</tbody></table></div>
<div class="cyl-register-footer"><span>{{ $cylinders->total() ? $cylinders->firstItem().'–'.$cylinders->lastItem().' z '.$cylinders->total() : '0' }} pozycji</span>{{ $cylinders->links() }}</div>
</div></div>
<script src="{{ asset('js/cylinder-register.js') }}" defer></script>
@include('cylinders.media-dialog')
@endsection
