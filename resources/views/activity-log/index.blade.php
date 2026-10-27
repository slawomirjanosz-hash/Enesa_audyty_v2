@extends('layouts.app')

@section('page-title', 'Lista zmian')

@section('content')
@php
    $presentation = app(\App\Support\ActivityLogPresentation::class);
@endphp
<style>
    .log-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}.log-head h1{margin:0 0 5px;font-size:24px}.log-head p{margin:0;color:#6b776f;font-size:13px}.log-tabs{display:flex;gap:6px;margin-bottom:14px;border-bottom:1px solid #ddd8cd}.log-tab{padding:10px 14px;text-decoration:none;color:#66736b;font-weight:800;font-size:12px;border-bottom:3px solid transparent}.log-tab.active{color:var(--green);border-color:var(--green)}.month-filter{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px}.month-chip{padding:6px 10px;border:1px solid #ddd8cd;border-radius:999px;background:#fff;color:#536158;text-decoration:none;font-size:11px;font-weight:700}.month-chip.active{background:var(--green);border-color:var(--green);color:#fff}.log-list{display:grid;gap:10px}.log-card{background:#fff;border:1px solid #e5e1d8;border-radius:11px;padding:14px 16px}.log-card-top{display:grid;grid-template-columns:155px minmax(130px,180px) 110px 1fr;gap:12px;align-items:center}.log-time{font-size:11px;color:#66736b;white-space:nowrap}.log-user{font-weight:800;font-size:12px}.log-action{display:inline-flex;width:max-content;padding:4px 8px;border-radius:999px;background:#eef4ef;color:#285740;font-size:10px;font-weight:800}.log-action.deleted{background:#fee2e2;color:#b91c1c}.log-action.login{background:#dbeafe;color:#1d4ed8}.log-subject strong{font-size:12px}.log-subject small{display:block;color:#738078;margin-top:3px}.log-details{margin-top:11px;border-top:1px solid #eee;padding-top:10px}.log-details summary{cursor:pointer;color:var(--green);font-size:11px;font-weight:800}.change-grid{display:grid;grid-template-columns:minmax(110px,180px) 1fr 22px 1fr;gap:6px 10px;margin-top:9px;font-size:11px;align-items:start}.change-field{font-weight:800;color:#4d5a52}.change-value{overflow-wrap:anywhere;background:#fafaf6;border-radius:5px;padding:5px 7px}.change-arrow{text-align:center;color:#999;padding-top:5px}.log-meta{margin-top:9px;color:#7b847f;font-size:10px;overflow-wrap:anywhere}.log-empty{padding:50px;text-align:center;background:#fff;border:1px dashed #ccc;border-radius:12px;color:#777}.compact-pagination{margin-top:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.compact-pagination-summary{font-size:11px;color:#68746d}.compact-pagination-links{display:flex;gap:5px;align-items:center;flex-wrap:wrap}.compact-page{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:6px 10px;border:1px solid #ddd8cd;border-radius:7px;background:#fff;color:#405047;text-decoration:none;font-size:11px;font-weight:800}.compact-page:hover{border-color:var(--green);color:var(--green)}.compact-page.active{background:var(--green);border-color:var(--green);color:#fff}.compact-page.disabled{color:#a1aaa5;background:#f5f4f0;cursor:default}@media(max-width:800px){.log-card-top{grid-template-columns:1fr 1fr}.log-subject{grid-column:1/-1}.change-grid{grid-template-columns:1fr}.change-arrow{display:none}}
</style>

<div class="log-head"><div><h1><i class="ti ti-history"></i> Lista zmian</h1><p>Kto, kiedy i co zmienił. Logowania znajdziesz w osobnej zakładce.</p></div></div>
<form id="activity-log-search" class="log-search" method="GET" action="{{route('activity-log.index')}}">
<input type="hidden" name="tab" value="{{$tab}}">@if($selectedMonth)<input type="hidden" name="month" value="{{$selectedMonth}}">@endif
<label for="activity-search">Szukaj w całej historii</label><div><input id="activity-search" name="search" value="{{$search}}" maxlength="200" placeholder="Użytkownik, samochód, nr rejestracyjny, pole lub wartość przed / po zmianie…"><button type="submit">Szukaj</button></div>
<small>Możesz połączyć kilka słów, np. nazwisko i samochód. Wyszukiwanie obejmuje wszystkie strony wybranej zakładki i okresu.</small><p id="activity-search-status" role="status"></p></form>
<div id="activity-log-results" aria-live="polite">@include('activity-log.results')</div>
<style>
    .change-grid{font-size:13px;grid-template-columns:minmax(160px,240px) minmax(0,1fr) 22px minmax(0,1fr);gap:10px;align-items:center}
    .change-value{white-space:pre-wrap;padding:9px 12px;background:#f5f7f5}
    .change-value small{display:block;font-size:10px;color:#65736b;margin-bottom:3px;white-space:normal}
    .change-field{font-size:12px}.log-meta summary{cursor:pointer}
    @media(max-width:800px){.change-grid{grid-template-columns:1fr}.change-field{margin-top:8px}}
</style>
<style>
.log-search{margin-bottom:18px}.log-search>label{display:block;font-size:13px;font-weight:700;margin-bottom:6px}.log-search>div{display:flex;gap:8px}.log-search input{flex:1;min-width:0;padding:11px 13px;border:1px solid #cbd5e1;border-radius:8px}.log-search button{padding:10px 18px;border:0;border-radius:8px;background:var(--green);color:#fff;cursor:pointer}.log-search small{display:block;margin-top:6px;color:#66736b;font-size:12px}.log-search [role=status]{font-size:12px;color:#66736b}.log-preview{margin:7px 0 0;font-size:12px;line-height:1.6;overflow-wrap:anywhere}.log-preview span{display:block}.log-user small{font-size:10px;color:#66736b;font-weight:400}.log-card-top{align-items:start}.log-subject{min-width:0}.log-details summary{padding:4px 0}
</style>
@endsection
@push('scripts')<script src="{{asset('js/activity-log-search.js')}}?v=1" defer></script>@endpush
