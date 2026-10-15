@extends('layouts.app')
@section('title', 'Podgląd protokołu')
@section('page-title', 'Podgląd protokołu')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><div><h1>Protokół serii: {{ $series->name }}</h1><p class="cyl-muted">Wzór 1.0 · {{ count($snapshot['items']) }} urządzeń</p></div><a class="cyl-btn" href="{{ route('cylinders.series.show', $series) }}">Wróć do serii</a></div>
<div class="cyl-card">
<p>Sprawdź dane i wyniki w podglądzie. Wydanie zapisuje numer, dane i finalny PDF. Zmiany w kartotekach nie zmienią wydanego dokumentu.</p>
<p class="cyl-muted">Dokument zawiera zapisane wyniki przeglądów. Podpis elektroniczny i formalna korekta dokumentu nie są jeszcze obsługiwane.</p>
@if($series->status === 'completed')
<form method="post" action="{{ route('cylinders.series.protocol.issue', $series) }}">@csrf<input type="hidden" name="fingerprint" value="{{ $fingerprint }}"><button class="cyl-btn cyl-btn-primary">Wydaj i zapisz protokół PDF</button></form>
@else<p>Zakończ serię po zapisaniu wszystkich przeglądów, aby wydać protokół.</p>@endif
<p><a class="cyl-btn" target="_blank" rel="noopener" href="{{ route('cylinders.series.protocol.draft', [$series, 'fingerprint' => $fingerprint]) }}">Otwórz podgląd PDF w osobnej karcie</a></p>
</div>
<iframe title="Podgląd roboczego protokołu PDF" src="{{ route('cylinders.series.protocol.draft', [$series, 'fingerprint' => $fingerprint]) }}" style="width:100%;height:75vh;border:1px solid #ddd;border-radius:8px;background:white"></iframe>
</div>
@endsection
