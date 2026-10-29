@extends('layouts.app')
@section('page-title', 'Audyty')
@section('content')
<style>
    .audit-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(310px,100%),1fr));gap:16px}.audit-card{display:block;background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:20px;text-decoration:none;color:inherit;transition:.15s}.audit-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(20,45,35,.08)}.audit-card:focus-visible{outline:3px solid var(--green);outline-offset:3px}.audit-card-head{display:flex;justify-content:space-between;gap:10px;align-items:start}.audit-number{font-size:11px;color:#6b7a70;font-weight:800;letter-spacing:.08em;overflow-wrap:anywhere}.audit-status{border-radius:999px;padding:4px 9px;font-size:11px;font-weight:800;background:#eef4ef;color:#24543d;white-space:nowrap}.audit-card h2{font-size:17px;margin:7px 0 12px;overflow-wrap:anywhere}.audit-meta{display:grid;gap:7px;font-size:13px;color:#59665e}.audit-filters{margin-bottom:18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}.audit-filters select{padding:9px 12px;border:1px solid #d8d4ca;border-radius:8px;background:#fff}.audit-empty{padding:45px;text-align:center;background:#fff;border:1px dashed #ccc;border-radius:12px;color:#777}
</style>
@include('partials.header-search-styles')
<div class="app-search-header">
    <div><h1 style="margin:0 0 5px;">Audyty</h1><p style="margin:0;color:#6b7a70;">Bieżące audyty, ich zespoły i terminy w jednym miejscu.</p></div>
    <div class="app-search-toolbar">
        @include('partials.list-search', ['serverSearch' => true, 'searchPlaceholder' => 'Szukaj: audyt, numer, firma, kierownik…'])
    </div>
</div>
@php($statuses = ['draft' => 'Roboczy', 'in_progress' => 'W toku', 'done' => 'Zakończony', 'cancelled' => 'Anulowany'])
<form class="audit-filters" method="GET" action="{{ route('audits.index') }}">
    <input type="hidden" name="q" value="{{ request('q', '') }}">
    <label for="audit-status">Status</label>
    <select id="audit-status" name="status" onchange="this.form.submit()">
        @foreach(['active' => 'Aktywne (robocze i w toku)', 'all' => 'Wszystkie statusy'] + $statuses as $value => $label)
        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <span>Liczba audytów: {{ $audits->total() }}</span>
</form>
<div class="audit-grid">
    @foreach($audits as $audit)
    <a class="audit-card" href="{{ route('audits.show', $audit) }}">
        <div class="audit-card-head"><span class="audit-number">{{ $audit->number }}</span><span class="audit-status">{{ $statuses[$audit->status] ?? $audit->status }}</span></div>
        <h2>{{ $audit->title }}</h2>
        <div class="audit-meta">
            <span><i class="ti ti-building"></i> {{ $audit->company?->name ?? '—' }}</span>
            <span><i class="ti ti-user-star"></i> {{ $audit->manager?->name ?? 'Brak kierownika' }}</span>
            <span><i class="ti ti-users"></i> Zespół: {{ $audit->members_count }}</span>
            <span><i class="ti ti-calendar"></i> {{ $audit->start_date?->format('d.m.Y') ?? '—' }} – {{ $audit->end_date?->format('d.m.Y') ?? '—' }}</span>
        </div>
    </a>
    @endforeach
</div>
@if($audits->isEmpty())
<div class="audit-empty"><i class="ti ti-clipboard-check" style="font-size:36px;"></i><p>Brak audytów spełniających wybrane kryteria.</p></div>
@endif
<div style="margin-top:20px;">{{ $audits->links() }}</div>
@endsection
