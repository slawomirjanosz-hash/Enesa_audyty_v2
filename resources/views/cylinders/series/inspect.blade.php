@extends('layouts.app')
@section('title', 'Przegląd · '.$cylinder->serial_number)
@section('page-title', 'Przegląd w serii')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><div><h1>{{ $cylinder->name }} · {{ $cylinder->serial_number }}</h1><p>Znak wytwórczy: {{ $cylinder->manufacturer_mark }} · Seria: {{ $series->name }}</p></div><a class="cyl-btn" href="{{ route('cylinders.series.show', $series) }}">Wróć do serii</a></div>
<form class="cyl-card" method="post" enctype="multipart/form-data" action="{{ route('cylinders.series.inspections.store', [$series, $item]) }}">@csrf
<p class="cyl-muted">Wykonujący: {{ auth()->user()->name }}. Zapis tworzy nowy wpis w historii urządzenia.</p>
@include('cylinders.inspection-fields')
<p class="cyl-actions"><button class="cyl-btn cyl-btn-primary">Zapisz i przejdź dalej</button><a class="cyl-btn" href="{{ route('cylinders.series.show', $series) }}">Wróć bez zapisywania tego formularza</a></p>
</form></div>
@endsection
