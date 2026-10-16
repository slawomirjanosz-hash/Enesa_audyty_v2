@extends('layouts.app')
@section('title', $series->name)
@section('page-title', 'Seria przeglądów')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><div><h1>{{ $series->name }}</h1><p>{{ \App\Models\CylinderInspectionSeries::STATUSES[$series->status] }} · {{ $series->company?->name ?? 'Bez klienta' }} · Prowadzący: {{ $series->responsible->name }}</p></div><a class="cyl-btn" href="{{ route('cylinders.series.index') }}">Wszystkie serie</a></div>
<div class="cyl-card"><h2>Postęp: {{ $series->saved_count }} / {{ $series->items_count }}</h2><progress value="{{ $series->saved_count }}" max="{{ $series->items_count }}" aria-label="Zapisane przeglądy">{{ $series->saved_count }} / {{ $series->items_count }}</progress>
<p class="cyl-muted">Każdy zapisany wynik pozostaje w historii urządzenia. Możesz opuścić serię i wrócić do niej później.</p>
@error('series')<p class="cyl-field-error">{{ $message }}</p>@enderror
@if($series->status !== 'completed')
@if($nextItem)<a class="cyl-btn cyl-btn-primary" href="{{ route('cylinders.series.inspect', [$series, $nextItem]) }}">{{ $series->saved_count ? 'Kontynuuj przeglądy' : 'Rozpocznij przeglądy' }}</a>
@elseif($series->saved_count === $series->items_count)<form method="post" action="{{ route('cylinders.series.complete', $series) }}">@csrf @method('PATCH')<button class="cyl-btn cyl-btn-primary">Zakończ serię</button></form>
@else<p>Przywróć pozostałe urządzenia z archiwum, aby dokończyć serię.</p>@endif
@else<p>Zakończono: {{ $series->completed_at?->format('d.m.Y H:i') }}</p>@endif
</div>
<div class="cyl-card"><h2>Protokół PDF</h2>
@if($series->protocol)<p>{{ $series->protocol->number }} · Wydano {{ $series->protocol->issued_at->format('d.m.Y H:i') }}. Dokument zachowuje stan z chwili wydania.</p><a class="cyl-btn cyl-btn-primary" target="_blank" rel="noopener" href="{{ route('cylinders.series.protocol.download', $series) }}">Otwórz zapisany PDF</a>
@else<p>Sprawdź podgląd dokumentu. Wydanie protokołu będzie możliwe po zakończeniu serii.</p><a class="cyl-btn" href="{{ route('cylinders.series.protocol.preview', $series) }}">Podgląd protokołu</a>@endif
</div>
<div class="cyl-card"><div class="cyl-table"><table data-server-sort="name,mark,serial,status,date,"><thead><tr><th>Urządzenie</th><th>Nazwa lub znak wytwórcy</th><th>Numer fabryczny</th><th>Postęp</th><th>Data przeglądu</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@foreach($items as $item)<tr><td><a href="{{ route('cylinders.show', $item->cylinder) }}">{{ $item->cylinder->name }}</a>@if($item->cylinder->archived_at)<span class="cyl-secondary">Urządzenie zarchiwizowane</span>@endif</td><td>{{ $item->cylinder->manufacturer_mark }}</td><td>{{ $item->cylinder->serial_number }}</td><td>{{ $item->inspection_id ? 'Przegląd zapisany' : 'Do wykonania' }}</td><td>{{ $item->inspection?->inspected_at->format('d.m.Y') ?? '—' }}</td><td>
@if($item->inspection_id)<a class="cyl-btn" href="{{ route('cylinders.show', $item->cylinder) }}">Historia urządzenia</a>
@elseif($series->status !== 'completed' && !$item->cylinder->archived_at)<a class="cyl-btn" href="{{ route('cylinders.series.inspect', [$series, $item]) }}">Wykonaj przegląd</a>@endif
</td></tr>@endforeach
</tbody></table></div>{{ $items->links() }}</div></div>
@endsection
