@extends($layout)
@section('title', 'Inspektor UDT')
@section('page-title', 'Inspektor UDT')
@section('content')
<div class="cyl-module cyl-register">
    @include('cylinders.style')
    <div class="cyl-head">
        <div><h1>{{ $clientView ? 'Moje butle i przeglądy' : 'Rejestr butli' }}</h1><p class="cyl-muted">Urządzenia, status przeglądu i nadchodzące terminy.</p></div>
        @if($canManage)<a class="cyl-btn cyl-btn-primary" href="{{ route('cylinders.create') }}">+ Dodaj butlę</a>@endif
    </div>
    <div class="cyl-card cyl-register-card">
        <div class="cyl-register-toolbar">
            <nav class="cyl-register-tabs" aria-label="Widok rejestru">
                <a href="{{ route($routePrefix.'index', ['q'=>request('q')]) }}" @if(!request('archived')) aria-current="page" @endif>Aktywne butle</a>
                <a href="{{ route($routePrefix.'index', ['archived'=>1, 'q'=>request('q')]) }}" @if(request('archived')) aria-current="page" @endif>Archiwum</a>
            </nav>
            <form method="get" class="cyl-register-search" role="search">
                <label for="cyl-search" class="cyl-sr-only">Szukaj numeru seryjnego, typu lub firmy</label>
                <input id="cyl-search" type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Numer seryjny, typ lub firma…">
                @if(request('archived'))<input type="hidden" name="archived" value="1">@endif
                <button class="cyl-btn" type="submit">Szukaj</button>
            </form>
        </div>
        <div class="cyl-table" tabindex="0" role="region" aria-label="Lista butli — przewijana poziomo na małym ekranie">
            <table class="cyl-register-table">
                <thead><tr><th scope="col">Numer seryjny</th><th scope="col">Firma</th><th scope="col">Typ / producent</th><th scope="col">Status</th><th scope="col">Ostatni przegląd</th><th scope="col">Następny termin</th><th scope="col"><span class="cyl-sr-only">Akcja</span></th></tr></thead>
                <tbody>
                @forelse($cylinders as $cylinder)
                    <tr>
                        <td><div class="cyl-identity">@include('cylinders.photo-thumb')<a class="cyl-serial" href="{{ route($routePrefix.'show', $cylinder) }}">{{ $cylinder->serial_number }}</a></div></td>
                        <td class="cyl-company-cell">{{ $cylinder->company?->name }}</td>
                        <td class="cyl-type-inline">{{ $cylinder->type }}@if($cylinder->manufacturer)<span class="cyl-muted"> / {{ $cylinder->manufacturer }}</span>@endif</td>
                        <td><span class="cyl-status-chip cyl-status-{{ $cylinder->conditionStatus() }}"><span class="cyl-status-dot" aria-hidden="true"></span>{{ $cylinder->conditionLabel() }}</span></td>
                        <td class="cyl-date">{{ $cylinder->latestInspection?->inspected_at?->format('d.m.Y') ?? 'Brak wpisu' }}</td>
                        <td class="cyl-date"><span class="cyl-due cyl-due-{{ $cylinder->dueStatus() }}">{{ $cylinder->latestInspection?->next_due_at?->format('d.m.Y') ?? 'Nie ustalono' }}</span></td>
                        <td class="cyl-row-action"><a href="{{ route($routePrefix.'show', $cylinder) }}" class="cyl-open" aria-label="Otwórz butlę {{ $cylinder->serial_number }}">Otwórz <span aria-hidden="true">→</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="cyl-empty"><strong>{{ request('q') ? 'Nie znaleziono butli' : 'Brak butli w tym widoku' }}</strong><span class="cyl-secondary">{{ request('q') ? 'Zmień wpisaną frazę i spróbuj ponownie.' : 'Zarejestrowane urządzenia pojawią się tutaj.' }}</span></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="cyl-register-footer"><span>{{ $cylinders->total() ? $cylinders->firstItem().'–'.$cylinders->lastItem().' z '.$cylinders->total() : '0' }} pozycji</span>{{ $cylinders->links() }}</div>
    </div>
    <p class="cyl-register-help">Status wynika z ostatniego przeglądu. Zgłoszone problemy mają pierwszeństwo przed terminem.</p>
    @include('cylinders.photo-viewer')
</div>
@endsection
