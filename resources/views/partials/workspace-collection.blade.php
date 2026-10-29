@php
    $isProject = $kind === 'projects';
    $moneyVisible = $canViewFinances ?? false;
    $labels = $isProject ? ['planned'=>'Planowany','active'=>'Aktywny','on_hold'=>'Wstrzymany','completed'=>'Zakończony','cancelled'=>'Anulowany'] : ['draft'=>'Roboczy','in_progress'=>'W toku','done'=>'Zakończony','cancelled'=>'Anulowany'];
@endphp
@if(request('view') === 'list')
<div class="workspace-list-scroll"><table class="workspace-list" data-server-sort="number,name,company,manager,team,date,end,status{{ $moneyVisible ? ',amount' : '' }}" data-sort-prefix="{{ $prefix }}" data-page-parameter="{{ $prefix === 'completed' ? 'completed_page' : 'page' }}">
<thead><tr><th>Numer</th><th>Nazwa</th><th>Firma / klient</th><th>Kierownik</th><th>Zespół</th><th>Data rozpoczęcia</th><th>Data zakończenia</th><th>Status</th>@if($moneyVisible)<th>Wartość netto</th>@endif<th data-sortable="false">Akcje</th></tr></thead>
<tbody>
@foreach($items as $item)
<tr>
    <td><a href="{{ route($kind.'.show', $item) }}">{{ $item->number }}</a></td>
    <td><a href="{{ route($kind.'.show', $item) }}">{{ $isProject ? $item->name : $item->title }}</a>@if($isProject && $item->overdue_tasks_count > 0)<span class="workspace-overdue" title="{{ $item->overdue_tasks_count }} zadań po terminie"> !</span>@endif</td>
    <td>{{ $item->company?->name ?? ($isProject ? 'Projekt wewnętrzny' : '—') }}</td><td>{{ $item->manager?->name ?? 'Brak kierownika' }}</td>
    <td data-sort-value="{{ $item->members_count }}">{{ $item->members_count }}</td>
    <td class="date" data-sort-value="{{ $item->start_date?->format('Y-m-d') }}">{{ $item->start_date?->format('d.m.Y') ?? '—' }}</td>
    <td class="date" data-sort-value="{{ $item->end_date?->format('Y-m-d') }}">{{ $item->end_date?->format('d.m.Y') ?? '—' }}</td>
    <td>{{ $labels[$item->status] ?? $item->status }}</td>
    @if($moneyVisible)<td data-sort-value="{{ $item->contract_value }}">{{ number_format((float)$item->contract_value, 2, ',', ' ') }} zł</td>@endif
    <td><a href="{{ route($kind.'.show', $item) }}">Otwórz</a></td>
</tr>
@endforeach
</tbody></table></div>
@else
<div class="workspace-tiles">
@foreach($items as $item)
<a class="workspace-tile" href="{{ route($kind.'.show', $item) }}">
    <div class="workspace-tile-head"><span>{{ $item->number }} @if($isProject && $item->overdue_tasks_count > 0)<span class="workspace-overdue" title="{{ $item->overdue_tasks_count }} zadań po terminie">!</span>@endif</span><span class="badge">{{ $labels[$item->status] ?? $item->status }}</span></div>
    <h2>{{ $isProject ? $item->name : $item->title }}</h2>
    <div class="workspace-tile-meta"><span><i class="ti ti-building"></i> {{ $item->company?->name ?? ($isProject ? 'Projekt wewnętrzny' : '—') }}</span><span><i class="ti ti-user-star"></i> {{ $item->manager?->name ?? 'Brak kierownika' }}</span><span><i class="ti ti-users"></i> Zespół: {{ $item->members_count }}</span><span><i class="ti ti-calendar"></i> {{ $item->start_date?->format('d.m.Y') ?? '—' }} – {{ $item->end_date?->format('d.m.Y') ?? '—' }}</span></div>
    @if($moneyVisible)<div class="workspace-amount">{{ number_format((float)$item->contract_value, 2, ',', ' ') }} zł</div>@endif
</a>
@endforeach
</div>
@endif
