@extends('layouts.app')
@section('title', 'Serie przeglądów')
@section('page-title', 'Serie przeglądów')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><div><h1>Serie przeglądów</h1><p class="cyl-muted">Zapisany postęp pozwala wrócić do pracy w dowolnym momencie.</p></div><a class="cyl-btn" href="{{ route('cylinders.index') }}">Wybierz urządzenia w rejestrze</a></div>
<div class="cyl-card">
<form method="get" class="cyl-actions"><label for="series-q">Nazwa</label><input id="series-q" name="q" value="{{ request('q') }}" maxlength="160"><label for="series-status">Status</label><select id="series-status" name="status"><option value="">Wszystkie</option>@foreach(\App\Models\CylinderInspectionSeries::STATUSES as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select><button class="cyl-btn">Szukaj</button></form>
<div class="cyl-table"><table data-server-sort="name,company,responsible,status,saved,total,created,"><thead><tr><th>Nazwa</th><th>Klient</th><th>Prowadzący</th><th>Status</th><th>Zapisane</th><th>Urządzenia</th><th>Utworzono</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($seriesList as $series)<tr><td><a href="{{ route('cylinders.series.show', $series) }}">{{ $series->name }}</a></td><td>{{ $series->company?->name ?? 'Bez klienta' }}</td><td>{{ $series->responsible->name }}</td><td>{{ \App\Models\CylinderInspectionSeries::STATUSES[$series->status] }}</td><td>{{ $series->saved_count }}</td><td>{{ $series->items_count }}</td><td>{{ $series->created_at->format('d.m.Y H:i') }}</td><td><a class="cyl-btn" href="{{ route('cylinders.series.show', $series) }}">{{ $series->status === 'completed' ? 'Otwórz' : 'Kontynuuj' }}</a></td></tr>
@empty<tr><td colspan="8">Brak serii. Zaznacz urządzenia w rejestrze, aby utworzyć pierwszą.</td></tr>@endforelse
</tbody></table></div>{{ $seriesList->links() }}
</div></div>
@endsection
