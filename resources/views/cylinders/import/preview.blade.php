@extends('layouts.app')
@section('title', 'Podgląd importu urządzeń')
@section('page-title', 'Podgląd importu')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
@php($invalid = collect($import->rows)->filter(fn ($row) => count($row['errors']) > 0)->count())
<div class="cyl-head"><h1>Podgląd importu urządzeń</h1><a class="cyl-btn" href="{{ route('cylinders.import.create') }}">Wczytaj inny lub poprawiony plik</a></div>
<div class="cyl-card"><p>Wiersze: <strong>{{ count($import->rows) }}</strong>. Nowe urządzenia: <strong>{{ collect($import->rows)->whereNull('existing_id')->count() }}</strong>. Rozpoznane urządzenia: <strong>{{ collect($import->rows)->whereNotNull('existing_id')->count() }}</strong>. Wiersze z błędami: <strong>{{ $invalid }}</strong>.</p>
<p>Klient nowych urządzeń: {{ $company?->name ?? 'Bez przypisanego klienta' }}.@if($import->create_series) Nowa seria: {{ $import->series_name }}.@endif</p>
<p class="cyl-muted">Dla rozpoznanych urządzeń zachowamy wszystkie dane z rejestru. Parametry z pliku służą w podglądzie do porównania. Import nie zmieni historii przeglądów. Podgląd jest ważny 24 godziny.</p>
@if($import->completed_at)<p>Ten import został już zapisany.</p>
@elseif($invalid)<p class="cyl-field-error">Popraw błędne wiersze w Excelu i wczytaj plik ponownie. Niczego nie zapisano w rejestrze.</p>
@else<form method="post" action="{{ route('cylinders.import.store', $import) }}">@csrf<button class="cyl-btn cyl-btn-primary">Zatwierdź import{{ $import->create_series ? ' i utwórz serię' : '' }}</button></form>@endif
</div>
<div class="cyl-card"><div class="cyl-table"><table><thead><tr><th>Wiersz</th><th>Nazwa z pliku</th><th data-sort-type="text">Znak wytwórczy</th><th>Numer fabryczny</th><th>Operacja</th><th data-sort-type="text">Uwagi / parametry</th></tr></thead><tbody>
@foreach($import->rows as $row)<tr><td data-sort-value="{{ $row['line'] }}">{{ $row['line'] }}</td><td>{{ $row['data']['name'] ?? 'Brak nazwy' }}</td><td>{{ $row['data']['manufacturer_mark'] }}</td><td>{{ $row['data']['serial_number'] }}</td><td>{{ $row['errors'] ? 'Błąd' : ($row['existing_id'] ? 'Wykorzystaj istniejące' : 'Dodaj nowe') }}</td><td data-sort-value="{{ implode(' ', $row['errors']).' '.implode(' ', array_values($row['data'])) }}">
@foreach($row['errors'] as $error)<p class="cyl-field-error">{{ $error }}</p>@endforeach
@if($row['existing_id'])<p>Urządzenie #{{ $row['existing_id'] }}: zachowamy dane z rejestru. @if(!$row['errors'])<a href="{{ route('cylinders.show', $row['existing_id']) }}" target="_blank" rel="noopener">Porównaj kartotekę</a>@endif</p>@endif
<details><summary>Parametry z arkusza</summary><dl>@foreach($row['data'] as $field=>$value)<dt>{{ $columns[$field] }}</dt><dd style="overflow-wrap:anywhere">{{ $value }}</dd>@endforeach</dl></details>
</td></tr>@endforeach
</tbody></table></div></div></div>
@endsection
