@extends('layouts.app')
@section('title', 'Import urządzeń z Excela')
@section('page-title', 'Import urządzeń')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><div><h1>Import urządzeń z Excela</h1><p class="cyl-muted">Wczytaj plik, sprawdź podgląd, a następnie zatwierdź zapis.</p></div><a class="cyl-btn" href="{{ route('cylinders.index') }}">Wróć do rejestru</a></div>
<div class="cyl-card"><a class="cyl-btn" href="{{ route('cylinders.import.template') }}">Pobierz szablon XLSX</a><p>Wymagane kolumny: nazwa urządzenia, znak wytwórczy i numer fabryczny. Pozostałe parametry są opcjonalne. Wczytujemy arkusz „Urządzenia”, a gdy go nie ma - pierwszy arkusz. Limit: 300 urządzeń, 5 MB.</p><p class="cyl-muted">Zachowaj numery fabryczne i znaki wytwórcze jako tekst, aby nie utracić zer na początku. Wklej wartości zamiast formuł. Istniejące urządzenia zostaną rozpoznane po znaku wytwórczym i numerze fabrycznym.</p></div>
<form class="cyl-card" method="post" enctype="multipart/form-data" action="{{ route('cylinders.import.preview') }}">@csrf
<div class="cyl-grid"><div class="cyl-wide"><label for="import-file">Plik Excel *</label><input id="import-file" name="file" type="file" accept=".xlsx,.xls" required></div>
<div class="cyl-wide"><label for="import-company">Klient nowych urządzeń (opcjonalnie)</label><select id="import-company" name="company_id"><option value="">Bez przypisanego klienta</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>@endforeach</select><small class="cyl-muted">Nie wymagamy NIP. Import nie zmienia klienta istniejącego urządzenia.</small></div>
<div class="cyl-wide"><label><input type="checkbox" name="create_series" value="1" @checked(old('create_series'))> Utwórz serię przeglądów z listy</label></div>
<div><label for="import-series">Nazwa nowej serii</label><input id="import-series" name="series_name" maxlength="160" value="{{ old('series_name', 'Przeglądy '.today()->format('d.m.Y')) }}"></div>
<div><label for="import-responsible">Osoba prowadząca serię</label><select id="import-responsible" name="responsible_id">@foreach($staff as $user)<option value="{{ $user->id }}" @selected(old('responsible_id', auth()->id()) == $user->id)>{{ $user->name }}</option>@endforeach</select></div></div>
<p><button class="cyl-btn cyl-btn-primary">Sprawdź plik i pokaż podgląd</button></p>
</form></div>
@endsection
