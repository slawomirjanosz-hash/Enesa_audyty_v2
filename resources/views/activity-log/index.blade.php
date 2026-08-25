@extends('layouts.app')

@section('page-title', 'Lista zmian')

@section('content')
@php
    $formatLogValue = function ($value): string {
        if ($value === null || $value === '') return '—';
        if (is_bool($value)) return $value ? 'Tak' : 'Nie';
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return (string) $value;
    };
@endphp
<style>
    .log-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}.log-head h1{margin:0 0 5px;font-size:24px}.log-head p{margin:0;color:#6b776f;font-size:13px}.log-tabs{display:flex;gap:6px;margin-bottom:14px;border-bottom:1px solid #ddd8cd}.log-tab{padding:10px 14px;text-decoration:none;color:#66736b;font-weight:800;font-size:12px;border-bottom:3px solid transparent}.log-tab.active{color:var(--green);border-color:var(--green)}.month-filter{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px}.month-chip{padding:6px 10px;border:1px solid #ddd8cd;border-radius:999px;background:#fff;color:#536158;text-decoration:none;font-size:11px;font-weight:700}.month-chip.active{background:var(--green);border-color:var(--green);color:#fff}.log-list{display:grid;gap:10px}.log-card{background:#fff;border:1px solid #e5e1d8;border-radius:11px;padding:14px 16px}.log-card-top{display:grid;grid-template-columns:155px minmax(130px,180px) 110px 1fr;gap:12px;align-items:center}.log-time{font-size:11px;color:#66736b;white-space:nowrap}.log-user{font-weight:800;font-size:12px}.log-action{display:inline-flex;width:max-content;padding:4px 8px;border-radius:999px;background:#eef4ef;color:#285740;font-size:10px;font-weight:800}.log-action.deleted{background:#fee2e2;color:#b91c1c}.log-action.login{background:#dbeafe;color:#1d4ed8}.log-subject strong{font-size:12px}.log-subject small{display:block;color:#738078;margin-top:3px}.log-details{margin-top:11px;border-top:1px solid #eee;padding-top:10px}.log-details summary{cursor:pointer;color:var(--green);font-size:11px;font-weight:800}.change-grid{display:grid;grid-template-columns:minmax(110px,180px) 1fr 22px 1fr;gap:6px 10px;margin-top:9px;font-size:11px;align-items:start}.change-field{font-weight:800;color:#4d5a52}.change-value{overflow-wrap:anywhere;background:#fafaf6;border-radius:5px;padding:5px 7px}.change-arrow{text-align:center;color:#999;padding-top:5px}.log-meta{margin-top:9px;color:#7b847f;font-size:10px;overflow-wrap:anywhere}.log-empty{padding:50px;text-align:center;background:#fff;border:1px dashed #ccc;border-radius:12px;color:#777}@media(max-width:800px){.log-card-top{grid-template-columns:1fr 1fr}.log-subject{grid-column:1/-1}.change-grid{grid-template-columns:1fr}.change-arrow{display:none}}
</style>

<div class="log-head"><div><h1><i class="ti ti-history"></i> Lista zmian</h1><p>Historia operacji użytkowników i dostępu do aplikacji.</p></div></div>
<div class="log-tabs">
    <a class="log-tab {{$tab === 'changes' ? 'active' : ''}}" href="{{route('activity-log.index', ['tab'=>'changes'])}}"><i class="ti ti-edit"></i> Zmiany</a>
    <a class="log-tab {{$tab === 'logins' ? 'active' : ''}}" href="{{route('activity-log.index', ['tab'=>'logins'])}}"><i class="ti ti-login"></i> Logowania</a>
</div>
<div class="month-filter">
    <a class="month-chip {{$selectedMonth === null ? 'active' : ''}}" href="{{route('activity-log.index', ['tab'=>$tab])}}">Cała historia</a>
    @foreach($months as $month)
        <a class="month-chip {{$selectedMonth === $month ? 'active' : ''}}" href="{{route('activity-log.index', ['tab'=>$tab,'month'=>$month])}}">{{\Carbon\Carbon::createFromFormat('Y-m',$month)->locale('pl')->translatedFormat('F Y')}}</a>
    @endforeach
</div>

@if($logs->isEmpty())
    <div class="log-empty"><i class="ti ti-history-off" style="font-size:36px"></i><p>Brak zapisanych zdarzeń dla wybranego okresu.</p></div>
@else
<div class="log-list">
    @foreach($logs as $log)
    <article class="log-card">
        <div class="log-card-top">
            <div class="log-time">{{$log->created_at->format('d.m.Y H:i:s')}}</div>
            <div class="log-user">{{$log->user?->name ?? 'System / usunięty użytkownik'}}</div>
            <span class="log-action {{$log->action}}">{{$log->actionLabel()}}</span>
            <div class="log-subject"><strong>{{$log->areaLabel()}}: {{$log->subject_label ?: '—'}}</strong><small>{{$log->route_name ?: 'Brak nazwy trasy'}}</small></div>
        </div>
        @if($tab === 'changes' && !empty($log->changes))
        <details class="log-details">
            <summary>Pokaż zmienione pola ({{count($log->changes)}})</summary>
            <div class="change-grid">
                @foreach($log->changes as $field => $change)
                    <div class="change-field">{{str_replace('_',' ',$field)}}</div>
                    <div class="change-value">{{$formatLogValue($change['old'] ?? null)}}</div>
                    <div class="change-arrow">→</div>
                    <div class="change-value">{{$formatLogValue($change['new'] ?? null)}}</div>
                @endforeach
            </div>
        </details>
        @endif
        <div class="log-meta">IP: {{$log->ip_address ?: '—'}} · {{$log->url ?: '—'}} @if($tab === 'logins')<br>{{$log->user_agent ?: 'Nieznane urządzenie'}}@endif</div>
    </article>
    @endforeach
</div>
<div style="margin-top:18px">{{$logs->links()}}</div>
@endif
@endsection
