@extends('layouts.app')
@section('title', 'Plan finansowy działu')
@section('content')
@include('financial-plans.styles')
<div class="fp">
    <div class="fp-head"><div><h1>Plan finansowy działu</h1><div class="fp-muted">Plan roczny, aktualna prognoza i wykonanie. Wszystkie kwoty w PLN.</div></div><span class="fp-badge">Dane poufne · wynagrodzenia</span></div>
    @if(session('success'))<div class="fp-note" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="fp-note fp-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="fp-note">Wynik działu = przychody − bezpośrednie koszty realizacji − wynagrodzenia − samochody − pozostałe koszty działu. Przypisanie do zespołu nie daje dostępu do wynagrodzeń.</div>
    @forelse($departments as $department)
        <section class="fp-card">
            <div class="fp-head"><div><h2>{{ $department->name }}</h2><div class="fp-muted">{{ $department->members->pluck('name')->join(', ') ?: 'Uzupełnij skład działu.' }}</div></div>
            @if($canManage)<form method="POST" action="{{ route('financial-plans.store', $department) }}" class="fp-actions">@csrf<label>Rok<input type="number" name="year" min="2020" max="2100" value="{{ now('Europe/Warsaw')->year }}" required></label><button class="fp-btn primary">Otwórz / utwórz plan</button></form>@endif</div>
            <div class="fp-tabs">@foreach($department->plans as $plan)<a href="{{ route('financial-plans.show', $plan) }}">{{ $plan->year }} · {{ $plan->approved_at ? 'plan zatwierdzony' : 'wersja robocza' }}</a>@endforeach</div>
            @if($canManage && (auth()->user()->hasRole('superadmin') || $department->owner_id === auth()->id()))
                <details><summary>Skład działu i dostęp do planowania</summary>
                    <form method="POST" action="{{ route('financial-plans.departments.update', $department) }}">@csrf @method('PUT')
                        @include('financial-plans.department-form', ['department' => $department])
                        <button class="fp-btn">Zapisz dział i dostęp</button>
                    </form>
                </details>
            @endif
        </section>
    @empty
        <div class="fp-card"><h2>Nie masz jeszcze przypisanego działu</h2><p class="fp-muted">Utwórz dział lub poproś właściciela o przypisanie do planowania. Dostęp wymaga również osobnego uprawnienia do planów finansowych.</p></div>
    @endforelse
    @if($canManage)
        <details class="fp-card" @if($departments->isEmpty()) open @endif><summary>Nowy dział</summary>
            <form method="POST" action="{{ route('financial-plans.departments.store') }}">@csrf
                @include('financial-plans.department-form', ['department' => null])
                <button class="fp-btn primary">Utwórz dział</button>
            </form>
        </details>
    @endif
</div>
@endsection
