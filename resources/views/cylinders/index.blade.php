@extends($layout)
@section('title', 'Inspektor UDT')
@section('page-title', 'Inspektor UDT')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><div><h1>{{ $clientView ? 'Moje butle i przeglądy' : 'Rejestr butli' }}</h1><p class="cyl-muted">Dane urządzeń, terminy i historia przeglądów.</p></div>@if($canManage)<a class="cyl-btn cyl-btn-primary" href="{{ route('cylinders.create') }}">+ Dodaj butlę</a>@endif</div>
<div class="cyl-card">
<form method="get" class="cyl-actions" style="margin-bottom:18px"><div class="cyl-search"><label for="cyl-search">Szukaj numeru, typu lub firmy</label><input id="cyl-search" type="search" name="q" value="{{ request('q') }}" maxlength="100"></div>@if(request('archived'))<input type="hidden" name="archived" value="1">@endif<button class="cyl-btn">Szukaj</button><a class="cyl-btn" href="{{ route($routePrefix.'index', request('archived') ? [] : ['archived'=>1]) }}">{{ request('archived') ? 'Aktywne butle' : 'Archiwum' }}</a></form>
<div class="cyl-table"><table><thead><tr><th>Numer seryjny</th><th>Firma</th><th>Typ / producent</th><th>Ostatni przegląd</th><th>Następny termin</th><th>Akcja</th></tr></thead><tbody>
@forelse($cylinders as $cylinder)
<tr class="cyl-state-{{ $cylinder->conditionStatus() }}"><td><strong>{{ $cylinder->serial_number }}</strong><br><small>{{ $cylinder->conditionLabel() }}</small></td><td>{{ $cylinder->company?->name }}</td><td>{{ $cylinder->type }}<br><span class="cyl-muted">{{ $cylinder->manufacturer }}</span></td><td>{{ $cylinder->latestInspection?->inspected_at?->format('d.m.Y') ?? 'Brak wpisu' }}</td><td>{{ $cylinder->latestInspection?->next_due_at?->format('d.m.Y') ?? 'Nie ustalono' }}</td><td><a class="cyl-btn" href="{{ route($routePrefix.'show', $cylinder) }}">Otwórz</a></td></tr>
@empty<tr><td colspan="6">{{ request('q') ? 'Brak wyników wyszukiwania.' : 'Brak butli w tym widoku.' }}</td></tr>@endforelse
</tbody></table></div><p class="cyl-muted">Liczba wyników: {{ $cylinders->total() }}</p>{{ $cylinders->links() }}
</div>
@include('cylinders.legend')
@unless($clientView)<div class="cyl-notice">Analiza zdjęć AI, import pomiarów i protokoły będą dodawane po ustaleniu procedury badania.</div>@endunless
</div>
@endsection
