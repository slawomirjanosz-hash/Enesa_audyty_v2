@extends('layouts.app')
@section('title', 'Plan finansowy · '.($plan->name ?: $plan->department->name).' · '.$plan->periodLabel())
@section('content')
@include('financial-plans.styles')
<div class="fp" id="financial-plan" data-calculation-url="{{ route('financial-plans.calculation', [$plan, 'scenario' => $scenario]) }}" data-revision="{{ $plan->revision }}">
    <div class="fp-head"><div><a href="{{ route('financial-plans.index') }}" class="fp-muted">← Działy i okresy</a><h1>{{ $plan->name ?: $plan->department->name }} · <span id="fp-period-label">{{ $plan->periodLabel() }}</span></h1><div class="fp-muted">Plan finansowy działu · kwoty przychodów i kosztów projektów netto · pełny koszt zatrudnienia</div></div><div class="fp-live-status"><span id="fp-refresh-status" role="status">Aktualizowanie na bieżąco</span><button type="button" class="fp-btn" id="fp-refresh">Odśwież teraz</button></div></div>
    @if(session('success'))<div class="fp-note" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="fp-note fp-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @if($canManage)<details class="fp-card"><summary>Ustawienia planu</summary><form class="fp-plan-settings" method="POST" action="{{ route('financial-plans.period.update', $plan) }}">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $plan->revision }}">
        @include('financial-plans.plan-settings', ['department' => $plan->department])
        <button class="fp-btn primary">Zapisz plan</button>
    </form></details>@endif
    <p class="fp-muted">Dodane oferty są liczone w 100% według harmonogramu przychodów i kosztów. Pozycję możesz wyłączyć ikoną oka.</p>
    <div id="fp-live-calculation">@include('financial-plans.calculation')</div>

    @if($canManage)@include('financial-plans.line-form')@endif
    @if($calculation['complete'])<details class="fp-card" id="fp-history"><summary>Historia zmian</summary><div style="margin-top:16px">
        @foreach($changes as $change)<details><summary>{{ $change->created_at->timezone('Europe/Warsaw')->format('d.m.Y H:i') }} · {{ $change->author?->name }} · {{ ['created' => 'Dodanie pozycji', 'updated' => 'Zmiana pozycji', 'deleted' => 'Usunięcie pozycji', 'approved' => 'Zatwierdzenie planu', 'period_updated' => 'Zmiana okresu planu', 'opening_balance_updated' => 'Zmiana salda początkowego', 'payment_created' => 'Dodanie płatności działu', 'payment_deleted' => 'Usunięcie płatności działu'][$change->action] ?? $change->action }}</summary><p class="fp-muted">{{ $change->payload['after']['name'] ?? $change->payload['before']['name'] ?? 'Plan wybranego okresu' }} · {{ $change->payload['after']['notes'] ?? ($change->action === 'approved' ? 'Zapisano plan bazowy' : 'Bez opisu') }}</p></details>@endforeach
        {{ $changes->links() }}
    </div></details>@endif
</div>
<script type="module" src="{{ asset('js/financial-plan.js') }}?v={{ filemtime(public_path('js/financial-plan.js')) }}"></script>
@endsection
