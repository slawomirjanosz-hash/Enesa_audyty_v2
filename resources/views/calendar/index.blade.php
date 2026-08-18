@extends('layouts.app')

@section('title', 'Kalendarz')
@section('page-title', 'Kalendarz')

@push('styles')
<style>
    .calendar-header { display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:20px; }
    .calendar-header h1 { margin:0;color:var(--green);font:700 24px 'Lato',sans-serif;display:flex;align-items:center;gap:10px; }
    .calendar-header p { margin:6px 0 0;color:#728078;font-size:13px; }
    .calendar-actions { display:flex;align-items:center;gap:8px;flex-wrap:wrap; }
    .calendar-btn { min-height:38px;display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 12px;border:1px solid #D8D3C8;border-radius:8px;background:#fff;color:#30463a;text-decoration:none;font-size:12px;font-weight:700;cursor:pointer; }
    .calendar-btn:hover,.calendar-btn.active { background:var(--green);border-color:var(--green);color:#fff; }
    .calendar-filter { min-height:38px;padding:8px 34px 8px 11px;border:1px solid #D8D3C8;border-radius:8px;background:#fff;color:#30463a;font:600 12px 'Manrope',sans-serif; }
    .calendar-stats { display:grid;grid-template-columns:repeat(4,minmax(130px,1fr));gap:12px;margin-bottom:16px; }
    .calendar-stat { background:#fff;border:1px solid #E4E0D7;border-radius:10px;padding:12px 14px; }
    .calendar-stat span { display:block;color:#7a867f;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em; }
    .calendar-stat strong { display:block;margin-top:4px;color:#1e2d25;font-size:20px; }
    .calendar-shell { background:#fff;border:1px solid #DED9CF;border-radius:13px;overflow:hidden;box-shadow:0 2px 8px rgba(31,54,42,.05); }
    .calendar-titlebar { display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 18px;background:#FAFAF7;border-bottom:1px solid #E8E4DB; }
    .calendar-titlebar h2 { margin:0;color:#263a2f;font:700 18px 'Lato',sans-serif;text-transform:capitalize; }
    .calendar-grid-wrap { overflow-x:auto; }
    .calendar-grid { min-width:980px;display:grid;grid-template-columns:repeat(7,minmax(140px,1fr)); }
    .calendar-weekday { padding:9px 10px;background:#F4F5F1;color:#68756e;border-right:1px solid #E8E4DB;border-bottom:1px solid #DED9CF;text-align:center;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em; }
    .calendar-day { min-height:145px;padding:8px;border-right:1px solid #ECE8E0;border-bottom:1px solid #ECE8E0;background:#fff; }
    .calendar-day:nth-child(7n) { border-right:0; }
    .calendar-day.outside { background:#FAFAF8; }
    .calendar-day.today { background:#F1F8F4;box-shadow:inset 0 0 0 2px var(--green); }
    .calendar-day-head { display:flex;align-items:center;justify-content:space-between;margin-bottom:7px; }
    .calendar-day-number { width:27px;height:27px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;font-size:12px;font-weight:800;color:#405047; }
    .outside .calendar-day-number { color:#ABB2AE; }
    .today .calendar-day-number { background:var(--green);color:#fff; }
    .calendar-day-count { color:#98A19C;font-size:10px;font-weight:700; }
    .calendar-events { display:flex;flex-direction:column;gap:5px;max-height:112px;overflow-y:auto;scrollbar-width:thin; }
    .calendar-event { display:block;padding:6px 7px;border-radius:6px;border-left:3px solid #64748B;background:#F5F7F6;color:#25342c;text-decoration:none;line-height:1.25; }
    .calendar-event:hover { filter:brightness(.97); }
    .calendar-event.priority-high { border-left-color:#DC2626;background:#FEF2F2; }
    .calendar-event.priority-medium { border-left-color:#D97706;background:#FFF8E8; }
    .calendar-event.priority-low { border-left-color:#2563EB;background:#EFF6FF; }
    .calendar-event.done { opacity:.62;border-left-color:#16A34A;background:#F0FDF4; }
    .calendar-event-title { display:block;font-size:11px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .calendar-event-meta { display:block;margin-top:2px;color:#68756e;font-size:9px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .calendar-empty { padding:38px;text-align:center;color:#89958e;font-size:13px; }
    .calendar-legend { display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:11px 18px;border-top:1px solid #E8E4DB;background:#FAFAF7;color:#68756e;font-size:10px; }
    .calendar-legend span { display:inline-flex;align-items:center;gap:5px; }
    .calendar-dot { width:8px;height:8px;border-radius:50%;display:inline-block; }
    @media(max-width:800px) { .calendar-stats{grid-template-columns:repeat(2,1fr)}.calendar-header h1{font-size:21px}.calendar-actions{width:100%}.calendar-filter{flex:1;min-width:150px} }
</style>
@endpush

@section('content')
@php
    $previousMonth = $month->subMonth()->format('Y-m');
    $nextMonth = $month->addMonth()->format('Y-m');
    $queryBase = ['scope' => $scope] + ($selectedUserId ? ['user_id' => $selectedUserId] : []);
    $statusLabels = ['todo' => 'Do zrobienia', 'in_progress' => 'W trakcie', 'done' => 'Wykonane'];
@endphp

<div class="calendar-header">
    <div>
        <h1><i class="ti ti-calendar-month"></i> Kalendarz zadań</h1>
        <p>Zadania CRM i projektowe uporządkowane według terminu wykonania.</p>
    </div>
    <div class="calendar-actions">
        <a class="calendar-btn {{ $scope === 'mine' ? 'active' : '' }}" href="{{ route('calendar.index', ['month' => $month->format('Y-m'), 'scope' => 'mine']) }}"><i class="ti ti-user"></i> Moje zadania</a>
        @if($canViewTeam)
            <a class="calendar-btn {{ $scope === 'team' && !$selectedUserId ? 'active' : '' }}" href="{{ route('calendar.index', ['month' => $month->format('Y-m'), 'scope' => 'team']) }}"><i class="ti ti-users"></i> Cały zespół</a>
            <form method="GET" action="{{ route('calendar.index') }}">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <input type="hidden" name="scope" value="team">
                <select class="calendar-filter" name="user_id" onchange="this.form.submit()" aria-label="Wybierz użytkownika">
                    <option value="">Wszyscy użytkownicy</option>
                    @foreach($users as $calendarUser)
                        <option value="{{ $calendarUser->id }}" {{ $selectedUserId === $calendarUser->id ? 'selected' : '' }}>{{ $calendarUser->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>
</div>

<div class="calendar-stats">
    <div class="calendar-stat"><span>Zadania w miesiącu</span><strong>{{ $stats['month'] }}</strong></div>
    <div class="calendar-stat"><span>Do wykonania</span><strong>{{ $stats['open'] }}</strong></div>
    <div class="calendar-stat"><span>Po terminie</span><strong style="color:{{ $stats['overdue'] ? '#B91C1C' : '#1e2d25' }}">{{ $stats['overdue'] }}</strong></div>
    <div class="calendar-stat"><span>Wykonane</span><strong style="color:#15803D">{{ $stats['done'] }}</strong></div>
</div>

<section class="calendar-shell">
    <div class="calendar-titlebar">
        <a class="calendar-btn" href="{{ route('calendar.index', ['month' => $previousMonth] + $queryBase) }}" aria-label="Poprzedni miesiąc"><i class="ti ti-chevron-left"></i></a>
        <div style="display:flex;align-items:center;gap:9px;">
            <h2>{{ $month->locale('pl')->translatedFormat('F Y') }}</h2>
            <a class="calendar-btn" href="{{ route('calendar.index', ['month' => now()->format('Y-m')] + $queryBase) }}">Dzisiaj</a>
        </div>
        <a class="calendar-btn" href="{{ route('calendar.index', ['month' => $nextMonth] + $queryBase) }}" aria-label="Następny miesiąc"><i class="ti ti-chevron-right"></i></a>
    </div>

    <div class="calendar-grid-wrap">
        <div class="calendar-grid">
            @foreach(['Poniedziałek','Wtorek','Środa','Czwartek','Piątek','Sobota','Niedziela'] as $weekday)
                <div class="calendar-weekday">{{ $weekday }}</div>
            @endforeach

            @foreach($days as $day)
                @php
                    $dayTasks = $tasksByDate->get($day->format('Y-m-d'), collect());
                @endphp
                <div class="calendar-day {{ !$day->isSameMonth($month) ? 'outside' : '' }} {{ $day->isToday() ? 'today' : '' }}">
                    <div class="calendar-day-head">
                        <span class="calendar-day-number">{{ $day->day }}</span>
                        @if($dayTasks->isNotEmpty())<span class="calendar-day-count">{{ $dayTasks->count() }} zadań</span>@endif
                    </div>
                    <div class="calendar-events">
                        @foreach($dayTasks as $task)
                            @php
                                $taskUrl = $task->project ? route('projects.show', $task->project) : route('crm.index', ['tab' => 'tasks']);
                                $context = $task->project?->name ?? $task->company?->name ?? 'Bez firmy';
                            @endphp
                            <a href="{{ $taskUrl }}" class="calendar-event priority-{{ $task->priority }} {{ $task->status === 'done' ? 'done' : '' }}" title="{{ $task->title }} · {{ $task->assignedUser?->name ?? 'Nieprzypisane' }} · {{ $statusLabels[$task->status] ?? $task->status }}">
                                <span class="calendar-event-title">{{ $task->is_milestone ? '◆ ' : '' }}{{ $task->title }}</span>
                                <span class="calendar-event-meta">{{ $task->assignedUser?->name ?? 'Nieprzypisane' }} · {{ $context }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="calendar-legend">
        <span><i class="calendar-dot" style="background:#DC2626"></i> wysoki priorytet</span>
        <span><i class="calendar-dot" style="background:#D97706"></i> średni priorytet</span>
        <span><i class="calendar-dot" style="background:#2563EB"></i> niski priorytet</span>
        <span><i class="calendar-dot" style="background:#16A34A"></i> wykonane</span>
        <span>◆ kamień milowy projektu</span>
    </div>
</section>
@endsection
