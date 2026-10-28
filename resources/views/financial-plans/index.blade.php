@extends('layouts.app')
@section('title', 'Plan finansowy działu')
@section('content')
@include('financial-plans.styles')
<div class="fp">
    <div class="fp-head"><div><h1>Plan finansowy działu</h1><div class="fp-muted">Plan na wybrany zakres miesięcy, aktualna prognoza i przepływy. Wszystkie kwoty w PLN.</div></div><span class="fp-badge">Dane poufne · wynagrodzenia</span></div>
    @if(session('success'))<div class="fp-note" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="fp-note fp-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="fp-note">Wynik działu = przychody − bezpośrednie koszty realizacji − wynagrodzenia − samochody − pozostałe koszty działu. Przypisanie do zespołu nie daje dostępu do wynagrodzeń.</div>
    @forelse($departments as $department)
        <section class="fp-card">
            <div class="fp-head fp-department-heading"><h2>{{ $department->name }}</h2>
                @if($canManage)<details class="fp-new-plan"><summary class="fp-btn primary">+ Nowy plan</summary><form method="POST" action="{{ route('financial-plans.store', $department) }}" class="fp-plan-settings">@csrf
                    @include('financial-plans.plan-settings', ['plan' => null])
                    <button class="fp-btn primary">Utwórz plan</button>
                </form></details>@endif
            </div>
            <ul class="fp-plan-list">
                @forelse($department->plans as $plan)<li class="fp-plan-item">
                    <div class="fp-plan-description"><a class="fp-plan-name" href="{{ route('financial-plans.show', $plan) }}">{{ $plan->name ?: $department->name }}</a><div class="fp-plan-meta"><span>{{ $plan->periodLabel() }}</span><span>Utworzono <time datetime="{{ $plan->created_at->toIso8601String() }}">{{ $plan->created_at->timezone('Europe/Warsaw')->format('d.m.Y H:i') }}</time></span></div></div>
                    @if($canManage)<div class="fp-plan-controls">
                        <a class="fp-btn fp-icon-btn" href="{{ route('financial-plans.show', $plan) }}" title="Edytuj plan" aria-label="Edytuj plan">@include('financial-plans.action-icon', ['icon' => 'edit'])</a>
                        <form method="POST" action="{{ route('financial-plans.destroy', $plan) }}" onsubmit="return confirm('Usunąć ten plan wraz z jego pozycjami? Projekty i oferty pozostaną zachowane.')">@csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $plan->revision }}"><button class="fp-btn danger fp-icon-btn" title="Usuń plan" aria-label="Usuń plan">@include('financial-plans.action-icon', ['icon' => 'trash'])</button></form>
                    </div>@endif
                </li>@empty<li class="fp-plan-empty">Brak planów. Utwórz pierwszy plan dla tego działu.</li>@endforelse
            </ul>
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
