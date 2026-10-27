@php($presentation = app(\App\Support\ActivityLogPresentation::class))
<div class="log-tabs">
    <a class="log-tab {{$tab === 'changes' ? 'active' : ''}}" href="{{route('activity-log.index', ['tab'=>'changes','search'=>$search])}}"><i class="ti ti-edit"></i> Zmiany</a>
    <a class="log-tab {{$tab === 'logins' ? 'active' : ''}}" href="{{route('activity-log.index', ['tab'=>'logins','search'=>$search])}}"><i class="ti ti-login"></i> Logowania</a>
</div>
<div class="month-filter">
    <a class="month-chip {{$selectedMonth === null ? 'active' : ''}}" href="{{route('activity-log.index', ['tab'=>$tab,'search'=>$search])}}">Cała historia</a>
    @foreach($months as $month)
        <a class="month-chip {{$selectedMonth === $month ? 'active' : ''}}" href="{{route('activity-log.index', ['tab'=>$tab,'month'=>$month,'search'=>$search])}}">{{\Carbon\Carbon::createFromFormat('Y-m',$month)->locale('pl')->translatedFormat('F Y')}}</a>
    @endforeach
</div>

@if($logs->isEmpty())
    <div class="log-empty"><i class="ti ti-history-off" style="font-size:36px"></i><p>Brak zapisanych zdarzeń dla wybranego okresu.</p></div>
@else
<div class="log-list">
    @foreach($logs as $log)
    @php($visibleChanges = $presentation->visibleChanges($log))
    <article class="log-card">
        <div class="log-card-top">
            <div class="log-time">{{$log->created_at->format('d.m.Y H:i:s')}}</div>
            <div class="log-user"><small>Wykonał(a):</small><br>{{$log->user?->name ?? $log->actor_name ?? 'System / usunięty użytkownik'}}</div>
            <span class="log-action {{$log->action}}">{{$log->actionLabel()}}</span>
            <div class="log-subject"><strong>{{$log->areaLabel()}}: {{$presentation->subject($log)}}</strong><small>{{$presentation->context($log)}}</small>
            @if($tab==='changes' && $visibleChanges)<p class="log-preview">@foreach(array_slice($visibleChanges,0,3,true) as $field=>$change)<span><b>{{$presentation->fieldLabel($field)}}:</b> {{\Illuminate\Support\Str::limit($presentation->changeValue($field,$change,'old'),70)}} → {{\Illuminate\Support\Str::limit($presentation->changeValue($field,$change,'new'),70)}}</span>@endforeach @if(count($visibleChanges)>3)<span>i {{count($visibleChanges)-3}} pozostałych pól</span>@endif</p>@endif</div>
        </div>
        @if($tab === 'changes' && !empty($visibleChanges))
        <details class="log-details">
            <summary>Pokaż zmienione pola ({{count($visibleChanges)}})</summary>
            <div class="change-grid">
                @foreach($visibleChanges as $field => $change)
                    <div class="change-field" title="{{$field}}">{{$presentation->fieldLabel($field)}}</div>
                    <div class="change-value"><small>Przed zmianą</small>{{$presentation->changeValue($field, $change, 'old')}}</div>
                    <div class="change-arrow">→</div>
                    <div class="change-value"><small>Po zmianie</small>{{$presentation->changeValue($field, $change, 'new')}}</div>
                @endforeach
            </div>
        </details>
        @endif
        <details class="log-meta"><summary>Informacje techniczne</summary>IP: {{$log->ip_address ?: '—'}} · {{$log->url ?: '—'}}<br>Operacja: {{$log->route_name ?: 'Brak nazwy trasy'}} @if($tab === 'logins')<br>{{$log->user_agent ?: 'Nieznane urządzenie'}}@endif</details>
    </article>
    @endforeach
</div>
{{ $logs->onEachSide(1)->links('pagination.compact') }}
@endif
