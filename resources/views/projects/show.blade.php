@extends('layouts.app')

@section('page-title', $project->number.' — '.$project->name)

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.0/dist/frappe-gantt.css">
@php
    $canEdit = auth()->user()->can('update', $project);
    $statusLabels = ['planned'=>'Planowany','active'=>'Aktywny','on_hold'=>'Wstrzymany','completed'=>'Zakończony','cancelled'=>'Anulowany'];
    $financeChartData = $project->financialEntries->sortBy('entry_date')->map(fn($entry) => [
        'date' => $entry->entry_date->format('Y-m-d'),
        'amount' => (float) $entry->amount,
        'type' => $entry->type,
        'status' => $entry->status,
        'name' => $entry->name,
    ])->values();
    $committedRequirements = $project->requirements
        ->whereIn('status', ['ordered', 'in_progress', 'purchased'])
        ->sum(fn($requirement) => (float) $requirement->estimated_cost);
@endphp
<style>
    .p-head{display:flex;justify-content:space-between;gap:20px;margin-bottom:18px}.p-kicker{font-size:12px;font-weight:800;color:var(--green);letter-spacing:.08em}.p-head h1{margin:4px 0 7px;font-size:25px}.p-meta{display:flex;flex-wrap:wrap;gap:9px 18px;color:#66736b;font-size:13px}.badge{padding:5px 10px;border-radius:999px;background:#edf5ef;color:#24543d;font-size:11px;font-weight:800}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}.sum{background:#fff;border:1px solid #e5e1d8;border-radius:11px;padding:16px}.sum small{display:block;color:#77827b;margin-bottom:6px}.sum strong{font-size:20px}.tabs{display:flex;gap:4px;border-bottom:2px solid #e5e1d8;overflow:auto}.tab{border:0;background:none;padding:12px 16px;font-weight:700;color:#66736b;cursor:pointer;white-space:nowrap;border-bottom:2px solid transparent;margin-bottom:-2px}.tab.active{color:var(--green);border-color:var(--green)}.pane{display:none;padding-top:20px}.pane.active{display:block}.card{background:#fff;border:1px solid #e5e1d8;border-radius:11px;padding:20px;margin-bottom:16px}.card h2{font-size:16px;margin:0 0 15px}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:flex;flex-direction:column;gap:5px}.field label{font-size:11px;font-weight:800;color:#4b5650}.field input,.field select,.field textarea{border:1px solid #d8d3c8;border-radius:7px;padding:9px 10px;font:inherit}.full{grid-column:1/-1}.member-checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px 12px;padding:10px;border:1px solid #d8d3c8;border-radius:8px;background:#fafaf7}.member-check{display:flex!important;flex-direction:row!important;align-items:center;gap:8px;font-size:12px!important;font-weight:600!important}.member-check input{width:16px;height:16px}.btn{border:0;border-radius:7px;padding:9px 13px;background:var(--green);color:#fff;font-weight:800;cursor:pointer;text-decoration:none}.btn-red{background:#b91c1c}.btn-soft{background:#edf4ef;color:var(--green)}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #eee;font-size:12px}th{font-size:10px;text-transform:uppercase;color:#7b847f;background:#fafaf6}.gantt{overflow-x:auto}.g-row{display:grid;grid-template-columns:220px minmax(700px,1fr);min-height:46px;border-bottom:1px solid #eee;align-items:center}.g-name{padding:8px;font-size:12px}.g-track{height:28px;background:repeating-linear-gradient(90deg,#f7f7f3 0,#f7f7f3 calc(10% - 1px),#e7e4dc calc(10% - 1px),#e7e4dc 10%);position:relative}.g-bar{position:absolute;top:4px;height:20px;border-radius:5px;min-width:4px;color:#fff;font-size:10px;display:flex;align-items:center;padding:0 6px;overflow:hidden}.g-progress{position:absolute;inset:0 auto 0 0;background:rgba(0,0,0,.18)}.legend{display:flex;gap:16px;font-size:11px;color:#66736b;margin:8px 0}.dot{width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:4px}.finance-chart{width:100%;height:auto;background:#fbfbf8;border-radius:8px}.status-select{padding:6px;border:1px solid #ddd;border-radius:6px}.empty{padding:28px;text-align:center;color:#888}.team{display:flex;flex-wrap:wrap;gap:8px}.person{padding:7px 10px;border-radius:999px;background:#f0f5f1;font-size:12px}@media(max-width:850px){.summary{grid-template-columns:1fr 1fr}.grid2{grid-template-columns:1fr}.full{grid-column:auto}.member-checks{grid-template-columns:1fr 1fr}.g-row{grid-template-columns:150px minmax(650px,1fr)}}
    .gantt-toolbar,.chart-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin-bottom:13px}.tool-label{font-size:11px;font-weight:800;color:#66736b;margin-right:3px}.tool-btn{border:1px solid #d8d3c8;background:#fff;color:#46524b;border-radius:7px;padding:7px 10px;font:inherit;font-size:11px;font-weight:800;cursor:pointer}.tool-btn:hover,.tool-btn.active{background:var(--green);border-color:var(--green);color:#fff}.tool-btn.primary{background:var(--green);border-color:var(--green);color:#fff}.gantt-help{padding:9px 11px;background:#f6f8f5;border:1px solid #e1e6df;border-radius:7px;color:#66736b;font-size:11px;margin-bottom:12px}.frappe-gantt-wrap{min-height:260px;overflow-x:auto;border:1px solid #e3e0d8;border-radius:9px;background:#fff}.frappe-gantt-wrap .gantt-container{overflow:visible}.frappe-gantt-wrap svg{min-width:100%}.frappe-gantt-wrap.gantt-readonly svg{pointer-events:none}.frappe-gantt-wrap .bar-wrapper.stage-row .bar{fill:#2563eb}.frappe-gantt-wrap .bar-wrapper.stage-row .bar-progress{fill:#1d4ed8}.frappe-gantt-wrap .bar-wrapper.task-row .bar{fill:#8b5cf6}.frappe-gantt-wrap .bar-wrapper.task-row .bar-progress{fill:#6d28d9}.frappe-gantt-wrap .bar-label{font-size:11px;font-weight:700}.gantt-fallback{padding:45px;text-align:center;color:#777}.project-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center;padding:16px}.project-modal.open{display:flex}.project-modal-box{width:min(760px,100%);max-height:calc(100vh - 32px);overflow:auto;background:#fff;border-radius:13px;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,.22)}.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}.modal-head h2{margin:0}.modal-close{border:0;background:none;font-size:25px;cursor:pointer}.finance-summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin-bottom:16px}.finance-kpi{border-radius:9px;padding:12px 13px;background:#f7f8f5;border:1px solid #e4e5df}.finance-kpi small{display:block;color:#6d786f;font-size:10px;font-weight:800;margin-bottom:5px}.finance-kpi strong{font-size:15px}.chart-shell{height:330px;position:relative}.chart-range{font-size:11px;color:#66736b;font-weight:700;padding:7px 10px;background:#f7f8f5;border-radius:7px}.finance-register-tabs{display:flex;gap:5px;margin-bottom:12px}.register-tab{border:1px solid #d8d3c8;background:#f7f8f5;border-radius:7px;padding:7px 11px;font-size:11px;font-weight:800;cursor:pointer}.register-tab.active{background:#243f31;color:#fff;border-color:#243f31}.finance-row-hidden{display:none}@media(max-width:1050px){.finance-summary-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:700px){.finance-summary-grid{grid-template-columns:1fr 1fr}.chart-shell{height:280px}}
    .gantt-list-table{min-width:980px}.gantt-list-table tr.done-row{background:#f2fbf5}.gantt-list-table tr.overdue-row{background:#fff1f1}.progress-wrap{display:flex;align-items:center;gap:7px;min-width:190px}.progress-wrap input[type=range]{width:150px;height:6px;accent-color:#2563eb;cursor:pointer}.progress-wrap strong{min-width:36px;font-size:11px}.task-status{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap}.task-status.done{background:#d1fae5;color:#047857}.task-status.overdue{background:#fee2e2;color:#b91c1c}.task-status.active{background:#dbeafe;color:#1d4ed8}.days-value{font-size:11px;font-weight:800}.days-value.late{color:#dc2626}.days-value.ok{color:#15803d}.mini-actions{display:flex;gap:4px;justify-content:flex-end}.mini-btn{width:27px;height:25px;border:0;border-radius:5px;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;cursor:pointer;color:#fff}.mini-btn.edit{background:#2563eb}.mini-btn.delete{background:#dc2626}.mini-btn.move{background:#93c5fd}.mini-btn:disabled{opacity:.35;cursor:not-allowed}
    .finance-section{background:#fff;border:1px solid #e5e1d8;border-radius:11px;margin-bottom:14px;overflow:hidden}.finance-section>summary{display:flex;align-items:center;gap:10px;padding:15px 18px;cursor:pointer;font-size:15px;font-weight:800;list-style:none}.finance-section>summary::-webkit-details-marker{display:none}.finance-section>summary:before{content:'›';font-size:22px;line-height:1;transition:transform .18s}.finance-section[open]>summary:before{transform:rotate(90deg)}.finance-section>summary small{margin-left:auto;color:#77827b;font-size:10px;font-weight:700}.finance-section-body{border-top:1px solid #eee;padding:18px}.finance-import-note{font-size:11px;color:#66736b;line-height:1.5;background:#f7f8f5;border-radius:7px;padding:10px;margin-bottom:13px}.import-report{border:1px solid #bae6c6;background:#f0fdf4;border-radius:9px;padding:14px;margin-bottom:14px}.report-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.report-value{background:#fff;border-radius:7px;padding:9px}.report-value small{display:block;color:#66736b;font-size:9px;text-transform:uppercase}.report-value strong{font-size:15px}.finance-groups{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}.finance-group-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;background:#f0f5f1;border-radius:999px;font-size:11px}.finance-group-chip form{display:inline}.finance-group-chip button{border:0;background:none;color:#b91c1c;cursor:pointer;padding:0}.finance-table{min-width:1050px}.source-badge{display:inline-block;border-radius:999px;background:#eef2ff;color:#4338ca;padding:3px 6px;font-size:9px;font-weight:800}.finance-edit{position:relative}.finance-edit>summary{list-style:none}.finance-edit-box{position:absolute;right:0;z-index:20;width:min(650px,80vw);background:#fff;border:1px solid #d8d3c8;border-radius:9px;padding:14px;box-shadow:0 12px 35px rgba(0,0,0,.16)}.chart-overview-shell{height:90px;margin-top:8px;position:relative;border-top:1px solid #eee;padding-top:7px}@media(max-width:700px){.report-grid{grid-template-columns:1fr 1fr}.finance-section-body{padding:12px}}
</style>

<div class="p-head"><div><div class="p-kicker">{{ $project->number }}</div><h1>{{ $project->name }}</h1><div class="p-meta"><span><i class="ti ti-building"></i> {{ $project->company?->name ?? 'Projekt wewnętrzny' }}</span><span><i class="ti ti-user-star"></i> {{ $project->manager?->name }}</span><span><i class="ti ti-calendar"></i> {{ $project->start_date?->format('d.m.Y') ?? '—' }} – {{ $project->end_date?->format('d.m.Y') ?? '—' }}</span></div></div><div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end">@if($canEdit)<button class="btn btn-soft" type="button" onclick="document.getElementById('project-edit-modal').classList.add('open')"><i class="ti ti-edit"></i> Edytuj projekt</button>@endif<span class="badge">{{ $statusLabels[$project->status] ?? $project->status }}</span></div></div>

@if(session('success'))<div style="padding:12px 15px;background:#ecfdf5;color:#166534;border-radius:8px;margin-bottom:15px;">{{ session('success') }}</div>@endif
@if($errors->any())<div style="padding:12px 15px;background:#fef2f2;color:#991b1b;border-radius:8px;margin-bottom:15px;">{{ $errors->first() }}</div>@endif

<div class="summary">
    <div class="sum"><small>Wartość kontraktu</small><strong>{{ number_format((float)$project->contract_value,2,',',' ') }} zł</strong></div>
    <div class="sum"><small>Wystawione faktury</small><strong>{{ number_format($project->totalInvoiced(),2,',',' ') }} zł</strong></div>
    <div class="sum"><small>Koszty</small><strong>{{ number_format($project->totalCosts(),2,',',' ') }} zł</strong></div>
    <div class="sum"><small>Wynik</small><strong style="color:{{ $project->result()>=0?'#15803d':'#b91c1c' }}">{{ number_format($project->result(),2,',',' ') }} zł</strong></div>
</div>

@if($canEdit)
<div id="project-edit-modal" class="project-modal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="project-modal-box">
        <div class="modal-head"><div><h2>Edytuj projekt</h2><small style="color:#718078">Zmień dane projektu, jego typ lub przypisanego klienta.</small></div><button type="button" class="modal-close" onclick="document.getElementById('project-edit-modal').classList.remove('open')">×</button></div>
        @if($errors->projectEdit->any())
            <div style="padding:11px 13px;background:#fef2f2;color:#991b1b;border-radius:8px;margin-bottom:14px;">{{ $errors->projectEdit->first() }}</div>
        @endif
        <form method="POST" action="{{ route('projects.update',$project) }}">@csrf @method('PUT')
            <div class="grid2">
                <div class="field"><label>Numer projektu</label><input name="number" value="{{ old('number',$project->number) }}" required></div>
                <div class="field"><label>Nazwa projektu</label><input name="name" value="{{ old('name',$project->name) }}" required></div>
                <div class="field full"><label>Typ projektu / klient</label><select name="company_id"><option value="">Projekt wewnętrzny</option>@foreach($companies as $company)<option value="{{ $company->id }}" {{ (string)old('company_id',$project->company_id)===(string)$company->id?'selected':'' }}>Projekt dla klienta: {{ $company->name }}</option>@endforeach</select><small style="color:#718078">Wybierz klienta, aby zmienić projekt wewnętrzny w projekt klienta.</small></div>
                <div class="field"><label>Kierownik</label><select name="manager_id" required>@foreach($users as $user)<option value="{{ $user->id }}" {{ (string)old('manager_id',$project->manager_id)===(string)$user->id?'selected':'' }}>{{ $user->name }}</option>@endforeach</select></div>
                <div class="field"><label>Status</label><select name="status">@foreach($statusLabels as $value=>$label)<option value="{{$value}}" {{old('status',$project->status)===$value?'selected':''}}>{{$label}}</option>@endforeach</select></div>
                <div class="field"><label>Data rozpoczęcia</label><input type="date" name="start_date" value="{{ old('start_date',$project->start_date?->format('Y-m-d')) }}"></div>
                <div class="field"><label>Data zakończenia</label><input type="date" name="end_date" value="{{ old('end_date',$project->end_date?->format('Y-m-d')) }}"></div>
                <div class="field"><label>Wartość kontraktu netto</label><input type="number" step="0.01" min="0" name="contract_value" value="{{ old('contract_value',$project->contract_value) }}" required></div>
                <div class="field full"><label>Osoby zaangażowane</label><div class="member-checks">@foreach($users as $user)<label class="member-check"><input type="checkbox" name="member_ids[]" value="{{ $user->id }}" {{ collect(old('member_ids',$project->members->pluck('id')->all()))->contains(fn($id)=>(int)$id===$user->id) ? 'checked' : '' }}><span>{{ $user->name }}</span></label>@endforeach</div></div>
                <div class="field full"><label>Opis</label><textarea name="description" rows="4">{{ old('description',$project->description) }}</textarea></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px"><button type="button" class="btn btn-soft" onclick="document.getElementById('project-edit-modal').classList.remove('open')">Anuluj</button><button class="btn">Zapisz zmiany</button></div>
        </form>
    </div>
</div>
@if($errors->projectEdit->any())<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('project-edit-modal')?.classList.add('open'));</script>@endif
@endif

<div class="tabs">
    @foreach(['overview'=>'Przegląd','gantt'=>'Harmonogram i zadania','finances'=>'Finanse','requirements'=>'Materiały i usługi','documents'=>'Dokumenty'] as $id=>$label)
    <button class="tab {{ $loop->first?'active':'' }}" onclick="openProjectTab('{{ $id }}',this)">{{ $label }}</button>
    @endforeach
</div>

<section id="pane-overview" class="pane active">
    <div class="grid2">
        <div class="card"><h2>Zespół projektu</h2><div class="team"><span class="person"><strong>Kierownik:</strong> {{ $project->manager?->name ?? '—' }}</span>@foreach($project->members as $member)<span class="person">{{ $member->name }}</span>@endforeach</div></div>
        <div class="card"><h2>Opis</h2><div style="font-size:13px;line-height:1.6;color:#55625a;white-space:pre-line;">{{ $project->description ?: 'Brak opisu.' }}</div></div>
    </div>
</section>

<section id="pane-gantt" class="pane">
    <div class="card"><h2>Interaktywny wykres Gantta</h2>
        <div class="gantt-toolbar">
            @if($canEdit)<button type="button" class="tool-btn primary" id="gantt-add-task"><i class="ti ti-plus"></i> Dodaj zadanie</button>@endif
            <button type="button" class="tool-btn" id="gantt-export"><i class="ti ti-file-spreadsheet"></i> Eksport Excel</button>
            @if($canEdit)<button type="button" class="tool-btn" id="gantt-share"><i class="ti ti-link"></i> Link dla klienta</button>@endif
            <span class="tool-label">Widok:</span>
            @foreach(['Day'=>'Dzień','Week'=>'Tydzień','Month'=>'Miesiąc'] as $mode=>$label)<button type="button" class="tool-btn gantt-mode {{ $mode==='Week'?'active':'' }}" data-mode="{{ $mode }}">{{ $label }}</button>@endforeach
            <button type="button" class="tool-btn" id="gantt-today"><i class="ti ti-calendar-event"></i> Dzisiaj</button>
            <span class="legend" style="margin:0 0 0 auto"><span><i class="dot" style="background:#7C3AED"></i>Pozycje harmonogramu</span><span>Strzałki oznaczają zależności</span></span>
        </div>
        @if($canEdit)<div class="gantt-help"><strong>Obsługa:</strong> przeciągnij pasek, aby przesunąć termin; przeciągnij jego krawędź, aby zmienić czas trwania; przeciągnij uchwyt postępu, aby zapisać procent wykonania.</div>@endif
        <div id="project-frappe-gantt" class="frappe-gantt-wrap"></div>
    </div>
    <div class="card"><h2>Lista zadań <small style="font-weight:500;color:#78827b">(kolejność jak na Gantcie)</small></h2><div id="gantt-task-list"></div></div>
</section>

<section id="pane-finances" class="pane">
    @if(session('finance_import_report'))
        @php($report = session('finance_import_report'))
        <div class="import-report">
            <strong>Raport ostatniego importu</strong>
            <div class="report-grid" style="margin-top:10px">
                <div class="report-value"><small>Dodano</small><strong>{{$report['inserted']}}</strong></div>
                <div class="report-value"><small>Wartość dodana</small><strong>{{number_format($report['inserted_amount'],2,',',' ')}} zł</strong></div>
                <div class="report-value"><small>Duplikaty</small><strong>{{$report['duplicates']}}</strong></div>
                <div class="report-value"><small>Błędne wiersze</small><strong>{{$report['invalid']}}</strong></div>
            </div>
            @if(!empty($report['duplicate_preview']))<details style="margin-top:10px"><summary>Pokaż rozpoznane duplikaty</summary><div style="overflow:auto"><table><thead><tr><th>Wiersz</th><th>Data</th><th>Dokument</th><th>Nazwa</th><th>Kwota</th></tr></thead><tbody>@foreach($report['duplicate_preview'] as $row)<tr><td>{{$row['row']}}</td><td>{{$row['date']}}</td><td>{{$row['document'] ?: '—'}}</td><td>{{$row['name']}}</td><td>{{number_format($row['amount'],2,',',' ')}} zł</td></tr>@endforeach</tbody></table></div></details>@endif
        </div>
    @endif

    <details class="finance-section" open data-finance-section="summary">
        <summary>Podsumowanie i cash flow <small>Kliknij, aby zwinąć</small></summary>
        <div class="finance-section-body">
            <div class="finance-summary-grid">
                <div class="finance-kpi"><small>Wartość kontraktu</small><strong>{{number_format((float)$project->contract_value,2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Faktury wystawione / opłacone</small><strong style="color:#15803d">{{number_format($project->totalInvoiced(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Faktury planowane</small><strong style="color:#7c3aed">{{number_format($project->plannedInvoiced(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Koszty</small><strong style="color:#b91c1c">{{number_format($project->totalCosts(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Koszty planowane</small><strong style="color:#d97706">{{number_format($project->plannedCosts(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Zamówienia</small><strong style="color:#b45309">{{number_format($committedRequirements,2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Wynik na dziś</small><strong style="color:{{$project->result()>=0?'#15803d':'#b91c1c'}}">{{number_format($project->result(),2,',',' ')}} zł</strong></div>
            </div>
            @if($financeChartData->isEmpty())<div class="empty">Dodaj koszt lub fakturę, aby zobaczyć interaktywny wykres.</div>@else
                <div class="chart-toolbar">
                    <span class="tool-label">Grupowanie:</span>
                    @foreach(['day'=>'Dzień','week'=>'Tydzień','month'=>'Miesiąc','year'=>'Rok'] as $mode=>$label)<button type="button" class="tool-btn cashflow-mode {{$mode==='month'?'active':''}}" data-mode="{{$mode}}">{{$label}}</button>@endforeach
                    <button type="button" class="tool-btn" id="cashflow-prev">‹ Wcześniej</button><span class="chart-range" id="cashflow-range"></span><button type="button" class="tool-btn" id="cashflow-next">Dalej ›</button>
                    <button type="button" class="tool-btn active" id="cashflow-cumulative">Narastająco</button><button type="button" class="tool-btn" id="cashflow-reset">Resetuj</button>
                </div>
                <div class="chart-shell"><canvas id="project-cashflow-chart"></canvas></div>
                <div class="chart-overview-shell"><canvas id="project-cashflow-overview"></canvas></div>
            @endif
        </div>
    </details>

    @if($canEdit)
    <details class="finance-section" {{session('finance_import_report') || $errors->has('file') ? 'open' : ''}} data-finance-section="import">
        <summary>Import z Excela <small>xlsx, xls lub csv · ochrona przed duplikatami</small></summary>
        <div class="finance-section-body">
            <div class="finance-import-note">Rozpoznawane nagłówki: <strong>Data</strong>, <strong>Kwota netto / Netto / Kwota</strong>, a opcjonalnie: Podmiot/Dostawca, Dokument/Nr faktury, Opis, Status i Termin płatności. Ten sam dokument i kwota nie zostaną zaimportowane ponownie.</div>
            <form method="POST" enctype="multipart/form-data" action="{{route('projects.finances.import',$project)}}">@csrf
                <div class="grid2">
                    <div class="field"><label>Rodzaj importowanych danych</label><select name="type" required><option value="cost">Koszty</option><option value="invoice">Faktury dla klienta</option></select></div>
                    <div class="field"><label>Istniejąca grupa</label><select name="finance_group_id"><option value="">Bez grupy</option>@foreach($project->financeGroups as $group)<option value="{{$group->id}}">{{$group->name}}</option>@endforeach</select></div>
                    <div class="field"><label>Lub utwórz nową grupę</label><input name="new_group_name" placeholder="np. Koszty sierpień 2026"></div>
                    <div class="field"><label>Plik Excel / CSV</label><input type="file" name="file" accept=".xlsx,.xls,.csv" required></div>
                </div>
                <button class="btn" style="margin-top:12px"><i class="ti ti-file-spreadsheet"></i> Wczytaj i sprawdź duplikaty</button>
            </form>
        </div>
    </details>

    <details class="finance-section" data-finance-section="manual">
        <summary>Dodaj pozycję ręcznie <small>Koszt lub faktura</small></summary>
        <div class="finance-section-body"><form method="POST" action="{{route('projects.finances.store',$project)}}">@csrf
            <div class="grid2">
                <div class="field"><label>Rodzaj</label><select name="type"><option value="cost">Koszt</option><option value="invoice">Faktura dla klienta</option></select></div>
                <div class="field"><label>Nazwa</label><input name="name" required></div>
                <div class="field"><label>Numer dokumentu</label><input name="document_number"></div>
                <div class="field"><label>Dostawca z bazy</label><select name="supplier_company_id"><option value="">Niepowiązany</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}">{{$supplier->name}}</option>@endforeach</select></div>
                <div class="field"><label>Dostawca spoza bazy / klient</label><input name="supplier"></div>
                <div class="field"><label>Data dokumentu</label><input type="date" name="entry_date" value="{{now()->format('Y-m-d')}}" required></div>
                <div class="field"><label>Termin płatności</label><input type="date" name="payment_date"></div>
                <div class="field"><label>Kwota netto</label><input type="number" step="0.01" min="0" name="amount" required></div>
                <div class="field"><label>Status</label><select name="status"><option value="planned">Planowana</option><option value="issued">Wystawiona / zaksięgowana</option><option value="paid">Opłacona</option></select></div>
                <div class="field"><label>Grupa</label><select name="finance_group_id"><option value="">Bez grupy</option>@foreach($project->financeGroups as $group)<option value="{{$group->id}}">{{$group->name}}</option>@endforeach</select></div>
                <div class="field full"><label>Uwagi</label><textarea name="notes" rows="2"></textarea></div>
            </div><button class="btn" style="margin-top:12px">Dodaj pozycję</button>
        </form></div>
    </details>
    @endif

    <details class="finance-section" open data-finance-section="register">
        <summary>Rejestr finansowy <small>{{$project->financialEntries->count()}} pozycji</small></summary>
        <div class="finance-section-body">
            @if($canEdit)<form method="POST" action="{{route('projects.finance-groups.store',$project)}}" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:10px">@csrf<div class="field"><label>Nowa grupa kosztów / faktur</label><input name="name" required></div><button class="btn btn-soft">Dodaj grupę</button></form>
            <div class="finance-groups">@foreach($project->financeGroups as $group)<span class="finance-group-chip">{{$group->name}} ({{$group->entries->count()}})<form method="POST" action="{{route('projects.finance-groups.destroy',[$project,$group])}}">@csrf @method('DELETE')<button title="Usuń grupę">×</button></form></span>@endforeach</div>@endif
            @if($project->financialEntries->isEmpty())<div class="empty">Brak pozycji.</div>@else
                <div class="finance-register-tabs" style="margin-top:12px"><button type="button" class="register-tab active" data-finance-filter="all">Wszystko</button><button type="button" class="register-tab" data-finance-filter="invoice">Faktury dla klienta</button><button type="button" class="register-tab" data-finance-filter="cost">Koszty</button></div>
                @if($canEdit)<form id="finance-bulk-form" method="POST" action="{{route('projects.finances.bulk',$project)}}" onsubmit="return this.elements.action.value !== 'delete' || confirm('Usunąć zaznaczone pozycje?')">@csrf<div style="display:flex;gap:7px;align-items:center;margin-bottom:10px"><select class="status-select" name="action" required><option value="">Operacja grupowa…</option><option value="planned">Oznacz jako planowane</option><option value="issued">Oznacz jako wystawione / zaksięgowane</option><option value="paid">Oznacz jako opłacone</option><option value="delete">Usuń zaznaczone</option></select><button class="btn btn-soft">Wykonaj</button></div></form>@endif
                <div style="overflow-x:auto"><table class="finance-table"><thead><tr>@if($canEdit)<th><input type="checkbox" id="finance-select-all" title="Zaznacz wszystko"></th>@endif<th>Data / płatność</th><th>Rodzaj / grupa</th><th>Nazwa / dokument</th><th>Dostawca / klient</th><th>Status</th><th>Kwota</th><th>Źródło</th><th></th></tr></thead><tbody>
                @foreach($project->financialEntries->sortByDesc('entry_date') as $entry)<tr data-finance-type="{{$entry->type}}">@if($canEdit)<td><input type="checkbox" name="entry_ids[]" value="{{$entry->id}}" form="finance-bulk-form" class="finance-entry-check"></td>@endif<td>{{$entry->entry_date->format('d.m.Y')}}<br><small>{{$entry->payment_date?->format('d.m.Y') ?: '—'}}</small></td><td>{{$entry->type==='invoice'?'Faktura':'Koszt'}}<br><small>{{$entry->financeGroup?->name ?: 'Bez grupy'}}</small></td><td><strong>{{$entry->name}}</strong><br><small>{{$entry->document_number ?: '—'}}</small></td><td>@if($entry->supplierCompany)<a href="{{route('suppliers.show',$entry->supplierCompany)}}" style="color:var(--green);font-weight:700">{{$entry->supplierCompany->name}}</a>@else{{$entry->supplier ?: '—'}}@endif</td><td>{{['planned'=>'Planowana','issued'=>'Wystawiona / zaksięgowana','paid'=>'Opłacona'][$entry->status]??$entry->status}}</td><td style="font-weight:800;color:{{$entry->type==='invoice'?'#15803d':'#b91c1c'}}">{{number_format((float)$entry->amount,2,',',' ')}} zł</td><td><span class="source-badge">{{$entry->source==='excel_import'?'Excel':'Ręcznie'}}</span></td><td>@if($canEdit)<div class="mini-actions"><details class="finance-edit"><summary class="mini-btn edit" title="Edytuj">✎</summary><div class="finance-edit-box"><form method="POST" action="{{route('projects.finances.update',[$project,$entry])}}">@csrf @method('PATCH')<div class="grid2"><div class="field"><label>Rodzaj</label><select name="type"><option value="cost" {{$entry->type==='cost'?'selected':''}}>Koszt</option><option value="invoice" {{$entry->type==='invoice'?'selected':''}}>Faktura</option></select></div><div class="field"><label>Nazwa</label><input name="name" value="{{$entry->name}}" required></div><div class="field"><label>Dokument</label><input name="document_number" value="{{$entry->document_number}}"></div><div class="field"><label>Dostawca z bazy</label><select name="supplier_company_id"><option value="">Niepowiązany</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}" {{$entry->supplier_company_id===$supplier->id?'selected':''}}>{{$supplier->name}}</option>@endforeach</select></div><div class="field"><label>Dostawca / klient tekstowo</label><input name="supplier" value="{{$entry->supplier}}"></div><div class="field"><label>Data</label><input type="date" name="entry_date" value="{{$entry->entry_date->format('Y-m-d')}}" required></div><div class="field"><label>Termin płatności</label><input type="date" name="payment_date" value="{{$entry->payment_date?->format('Y-m-d')}}"></div><div class="field"><label>Kwota</label><input type="number" step="0.01" min="0" name="amount" value="{{$entry->amount}}" required></div><div class="field"><label>Status</label><select name="status">@foreach(['planned'=>'Planowana','issued'=>'Wystawiona / zaksięgowana','paid'=>'Opłacona'] as $value=>$label)<option value="{{$value}}" {{$entry->status===$value?'selected':''}}>{{$label}}</option>@endforeach</select></div><div class="field"><label>Grupa</label><select name="finance_group_id"><option value="">Bez grupy</option>@foreach($project->financeGroups as $group)<option value="{{$group->id}}" {{$entry->finance_group_id===$group->id?'selected':''}}>{{$group->name}}</option>@endforeach</select></div><div class="field full"><label>Uwagi</label><textarea name="notes">{{$entry->notes}}</textarea></div></div><button class="btn" style="margin-top:10px">Zapisz</button></form></div></details><form method="POST" action="{{route('projects.finances.destroy',[$project,$entry])}}" onsubmit="return confirm('Usunąć tę pozycję?')">@csrf @method('DELETE')<button class="mini-btn delete" title="Usuń">×</button></form></div>@endif</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </div>
    </details>
</section>

@if(false)
<section id="pane-finances-legacy" class="pane" style="display:none">
    <div class="card"><h2>Harmonogram finansowy i cash flow</h2>
        <div class="finance-summary-grid">
            <div class="finance-kpi"><small>Wartość kontraktu</small><strong>{{number_format((float)$project->contract_value,2,',',' ')}} zł</strong></div>
            <div class="finance-kpi"><small>Faktury dla klienta</small><strong style="color:#15803d">{{number_format($project->totalInvoiced(),2,',',' ')}} zł</strong></div>
            <div class="finance-kpi"><small>Koszty zaksięgowane</small><strong style="color:#b91c1c">{{number_format($project->totalCosts(),2,',',' ')}} zł</strong></div>
            <div class="finance-kpi"><small>Zamówione materiały/usługi</small><strong style="color:#b45309">{{number_format($committedRequirements,2,',',' ')}} zł</strong></div>
            <div class="finance-kpi"><small>Wynik na dziś</small><strong style="color:{{$project->result()>=0?'#15803d':'#b91c1c'}}">{{number_format($project->result(),2,',',' ')}} zł</strong></div>
        </div>
        @if($financeChartData->isEmpty())<div class="empty">Dodaj koszt lub fakturę, aby zobaczyć interaktywny wykres.</div>@else
        <div class="chart-toolbar">
            <span class="tool-label">Grupowanie:</span>
            @foreach(['day'=>'Dzień','week'=>'Tydzień','month'=>'Miesiąc','year'=>'Rok'] as $mode=>$label)<button type="button" class="tool-btn cashflow-mode {{$mode==='month'?'active':''}}" data-mode="{{$mode}}">{{$label}}</button>@endforeach
            <button type="button" class="tool-btn" id="cashflow-prev">‹ Wcześniej</button><span class="chart-range" id="cashflow-range"></span><button type="button" class="tool-btn" id="cashflow-next">Dalej ›</button>
            <button type="button" class="tool-btn active" id="cashflow-cumulative">Narastająco</button><button type="button" class="tool-btn" id="cashflow-reset">Resetuj</button>
        </div>
        <div class="chart-shell"><canvas id="project-cashflow-chart"></canvas></div>
        @endif
    </div>
    @if($canEdit)<div class="card"><h2>Dodaj pozycję finansową</h2><form method="POST" action="{{route('projects.finances.store',$project)}}">@csrf<div class="grid2"><div class="field"><label>Rodzaj</label><select name="type"><option value="cost">Koszt</option><option value="invoice">Faktura dla klienta</option></select></div><div class="field"><label>Nazwa</label><input name="name" required></div><div class="field"><label>Numer dokumentu</label><input name="document_number"></div><div class="field"><label>Data</label><input type="date" name="entry_date" value="{{now()->format('Y-m-d')}}" required></div><div class="field"><label>Kwota netto</label><input type="number" step="0.01" min="0" name="amount" required></div><div class="field"><label>Status</label><select name="status"><option value="planned">Planowana</option><option value="issued">Wystawiona / zaksięgowana</option><option value="paid">Opłacona</option></select></div></div><button class="btn" style="margin-top:12px">Dodaj pozycję</button></form></div>@endif
    <div class="card"><h2>Rejestr finansowy</h2>@if($project->financialEntries->isEmpty())<div class="empty">Brak pozycji.</div>@else<div class="finance-register-tabs"><button type="button" class="register-tab active" data-finance-filter="all">Wszystko</button><button type="button" class="register-tab" data-finance-filter="invoice">Faktury dla klienta</button><button type="button" class="register-tab" data-finance-filter="cost">Koszty</button></div><div style="overflow-x:auto"><table><thead><tr><th>Data</th><th>Rodzaj</th><th>Nazwa / dokument</th><th>Status</th><th>Kwota</th><th></th></tr></thead><tbody>@foreach($project->financialEntries->sortByDesc('entry_date') as $entry)<tr data-finance-type="{{$entry->type}}"><td>{{$entry->entry_date->format('d.m.Y')}}</td><td>{{$entry->type==='invoice'?'Faktura':'Koszt'}}</td><td><strong>{{$entry->name}}</strong><br><small>{{$entry->document_number}}</small></td><td>{{['planned'=>'Planowana','issued'=>'Wystawiona / zaksięgowana','paid'=>'Opłacona'][$entry->status]??$entry->status}}</td><td style="font-weight:800;color:{{$entry->type==='invoice'?'#15803d':'#b91c1c'}}">{{number_format((float)$entry->amount,2,',',' ')}} zł</td><td>@if($canEdit)<form method="POST" action="{{route('projects.finances.destroy',[$project,$entry])}}">@csrf @method('DELETE')<button class="btn btn-red">×</button></form>@endif</td></tr>@endforeach</tbody></table></div>@endif</div>
</section>

@endif
<section id="pane-requirements" class="pane">
    @if($canEdit)<div class="card"><h2>Nowe zapotrzebowanie</h2><form method="POST" action="{{route('projects.requirements.store',$project)}}">@csrf<div class="grid2"><div class="field"><label>Rodzaj</label><select name="type"><option value="material">Materiał</option><option value="service">Usługa</option></select></div><div class="field"><label>Nazwa</label><input name="name" required></div><div class="field"><label>Ilość</label><input type="number" step="0.01" min="0.01" name="quantity" value="1" required></div><div class="field"><label>Jednostka</label><input name="unit" placeholder="szt., kg, usł."></div><div class="field"><label>Szacowany koszt</label><input type="number" step="0.01" min="0" name="estimated_cost"></div><div class="field"><label>Potrzebne do</label><input type="date" name="needed_by"></div><div class="field"><label>Odpowiedzialny</label><select name="responsible_id"><option value="">—</option>@foreach($project->members as $member)<option value="{{$member->id}}">{{$member->name}}</option>@endforeach</select></div><div class="field"><label>Dostawca z bazy</label><select name="supplier_company_id"><option value="">Nie wybrano</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}">{{$supplier->name}}</option>@endforeach</select></div><div class="field"><label>Dostawca spoza bazy</label><input name="supplier"></div><input type="hidden" name="status" value="requested"><div class="field full"><label>Opis</label><textarea name="description"></textarea></div></div><button class="btn" style="margin-top:12px">Dodaj zapotrzebowanie</button></form></div>@endif
    <div class="card"><h2>Materiały i usługi</h2>@if($project->requirements->isEmpty())<div class="empty">Brak zapotrzebowań.</div>@else<table><thead><tr><th>Pozycja</th><th>Ilość</th><th>Odpowiedzialny</th><th>Termin</th><th>Koszt</th><th>Status i dostawca</th><th></th></tr></thead><tbody>@foreach($project->requirements as $req)<tr><td><strong>{{$req->name}}</strong><br><small>{{$req->type==='material'?'Materiał':'Usługa'}} · @if($req->supplierCompany)<a href="{{route('suppliers.show',$req->supplierCompany)}}">{{$req->supplierCompany->name}}</a>@else{{$req->supplier}}@endif</small></td><td>{{$req->quantity}} {{$req->unit}}</td><td>{{$req->responsible?->name??'—'}}</td><td>{{$req->needed_by?->format('d.m.Y')??'—'}}</td><td>{{number_format((float)$req->estimated_cost,2,',',' ')}} zł</td><td>@if($canEdit)<form method="POST" action="{{route('projects.requirements.update',[$project,$req])}}" style="display:flex;gap:5px;flex-wrap:wrap">@csrf @method('PATCH')<select name="status" class="status-select"><option value="requested" {{$req->status==='requested'?'selected':''}}>Zapotrzebowanie</option><option value="ordered" {{$req->status==='ordered'?'selected':''}}>Zamówione</option><option value="in_progress" {{$req->status==='in_progress'?'selected':''}}>W realizacji</option><option value="purchased" {{$req->status==='purchased'?'selected':''}}>Kupione</option><option value="cancelled" {{$req->status==='cancelled'?'selected':''}}>Anulowane</option></select><select name="supplier_company_id" class="status-select"><option value="">Bez dostawcy</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}" {{$req->supplier_company_id===$supplier->id?'selected':''}}>{{$supplier->name}}</option>@endforeach</select><input name="supplier" value="{{$req->supplier}}" placeholder="Dostawca spoza bazy" style="width:140px"><button class="btn btn-soft">Zapisz</button></form>@else {{$req->status}} @endif</td><td>@if($canEdit)<form method="POST" action="{{route('projects.requirements.destroy',[$project,$req])}}">@csrf @method('DELETE')<button class="btn btn-red">×</button></form>@endif</td></tr>@endforeach</tbody></table>@endif</div>
</section>

<section id="pane-documents" class="pane">
    @if($canEdit)<div class="card"><h2>Dodaj dokument projektu</h2><form method="POST" enctype="multipart/form-data" action="{{route('projects.documents.store',$project)}}">@csrf<div style="display:flex;gap:10px;align-items:center"><input type="file" name="file" required><button class="btn">Wgraj dokument</button></div><small>PDF, Word, Excel, obrazy lub ZIP, maks. 20 MB.</small></form></div>@endif
    <div class="card"><h2>Dokumenty projektu</h2>@if($project->documents->isEmpty())<div class="empty">Brak dokumentów.</div>@else<table><thead><tr><th>Plik</th><th>Rozmiar</th><th>Dodał</th><th>Data</th><th></th></tr></thead><tbody>@foreach($project->documents as $document)<tr><td><a href="{{route('projects.documents.download',[$project,$document])}}"><strong>{{$document->original_filename}}</strong></a></td><td>{{$document->formattedSize()}}</td><td>{{$document->uploader?->name??'System'}}</td><td>{{$document->created_at->format('d.m.Y H:i')}}</td><td>@if($canEdit)<form method="POST" action="{{route('projects.documents.destroy',[$project,$document])}}">@csrf @method('DELETE')<button class="btn btn-red">Usuń</button></form>@endif</td></tr>@endforeach</tbody></table>@endif</div>
</section>

@if($canEdit)
<div id="gantt-task-modal" class="project-modal" onclick="if(event.target===this)closeGanttTaskModal()">
    <div class="project-modal-box">
        <div class="modal-head"><h2 id="gantt-modal-title">Dodaj zadanie</h2><button type="button" class="modal-close" onclick="closeGanttTaskModal()">×</button></div>
        <form id="gantt-task-form">
            <input type="hidden" id="gantt-task-id">
            <div class="grid2">
                <div class="field full"><label>Nazwa zadania *</label><input id="gantt-task-title" required></div>
                <div class="field"><label>Data rozpoczęcia *</label><input type="date" id="gantt-task-start" required></div>
                <div class="field"><label>Liczba dni *</label><input type="number" id="gantt-task-duration" min="1" value="1" required></div>
                <div class="field"><label>Data zakończenia *</label><input type="date" id="gantt-task-end" required></div>
                <div class="field"><label>Postęp (%)</label><input type="number" id="gantt-task-progress" min="0" max="100" value="0" required></div>
                <div class="field"><label>Zależne od zadania</label><select id="gantt-task-dependency"><option value="">Brak — zadanie główne</option></select></div>
                <div class="field"><label>Osoba odpowiedzialna</label><select id="gantt-task-assignee"><option value="">Nieprzypisane</option>@foreach($project->members as $member)<option value="{{$member->id}}">{{$member->name}}</option>@endforeach</select></div>
                <div class="field"><label>Priorytet</label><select id="gantt-task-priority"><option value="low">Niski</option><option value="medium" selected>Średni</option><option value="high">Wysoki</option></select></div>
                <div class="field full"><label>Opis</label><textarea id="gantt-task-description" rows="3"></textarea></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px"><button type="button" class="btn btn-soft" onclick="closeGanttTaskModal()">Anuluj</button><button class="btn" id="gantt-task-save">Zapisz zadanie</button></div>
        </form>
    </div>
</div>
@endif

<script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.0/dist/frappe-gantt.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<script>
const projectTimelineItems = @json($timelineItems);
const projectFinanceItems = @json($financeChartData);
const projectContractValue = @json((float) $project->contract_value);
const requestedProjectTab = @json(request('tab'));
const projectCanEdit = @json($canEdit);
const projectCsrfToken = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
let projectGantt = null;
let projectCashflowChart = null;
let projectCashflowOverview = null;
let projectGanttMode = 'Week';

function openProjectTab(id, button) {
    document.querySelectorAll('.pane').forEach(element => element.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(element => element.classList.remove('active'));
    document.getElementById('pane-' + id).classList.add('active');
    button.classList.add('active');
    if (id === 'gantt') setTimeout(initProjectGantt, 30);
    if (id === 'finances') setTimeout(initProjectCashflow, 30);
}

function localDate(date) {
    const value = new Date(date);
    return [value.getFullYear(), String(value.getMonth() + 1).padStart(2, '0'), String(value.getDate()).padStart(2, '0')].join('-');
}
function escapeProjectHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
}

async function saveGanttChange(task, start, end, progress) {
    if (!projectCanEdit) return;
    const source = projectTimelineItems.find(item => item.id === task.id);
    if (!source) return;
    const payload = { progress: Math.round(progress ?? task.progress ?? 0) };
    payload.start_date = localDate(start);
    payload.due_date = localDate(end);
    const response = await fetch(source.update_url, {
        method: 'PATCH',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': projectCsrfToken},
        body: JSON.stringify(payload),
    });
    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(data.message || 'Nie udało się zapisać zmiany harmonogramu.');
    }
    const responseData = await response.json();
    source.start = payload.start_date;
    source.end = payload.end_date || payload.due_date;
    source.progress = payload.progress;
    if (Array.isArray(responseData.project_tasks)) {
        responseData.project_tasks.forEach(updated => {
            const index = projectTimelineItems.findIndex(item => item.id === updated.id);
            if (index >= 0) projectTimelineItems[index] = updated;
        });
        renderProjectGantt();
    }
}

function renderProjectGantt() {
    const container = document.getElementById('project-frappe-gantt');
    if (!container) return;
    container.innerHTML = '';
    projectGantt = null;
    initProjectGantt();
}

function initProjectGantt() {
    const container = document.getElementById('project-frappe-gantt');
    if (!container || projectGantt) return;
    renderGanttTaskList();
    if (!projectTimelineItems.length) {
        container.innerHTML = '<div class="gantt-fallback">Dodaj pierwsze zadanie, aby utworzyć harmonogram.</div>';
        return;
    }
    if (typeof Gantt === 'undefined') {
        container.innerHTML = '<div class="gantt-fallback">Nie udało się załadować wykresu Gantta. Sprawdź połączenie z internetem i odśwież stronę.</div>';
        return;
    }
    if (!projectCanEdit) container.classList.add('gantt-readonly');
    const tasks = projectTimelineItems.map(item => ({
        id: item.id,
        name: item.assignee ? item.name + ' · ' + item.assignee : item.name,
        start: item.start,
        end: item.end,
        progress: item.progress,
        dependencies: item.dependencies || '',
        custom_class: 'task-row',
    }));
    projectGantt = new Gantt('#project-frappe-gantt', tasks, {
        view_mode: projectGanttMode,
        language: 'en',
        popup_trigger: 'click',
        on_click: task => { if (projectCanEdit && task.id?.startsWith('task-')) openGanttTaskModal(task.id); },
        on_date_change: (task, start, end) => saveGanttChange(task, start, end, task.progress).catch(error => { alert(error.message); window.location.reload(); }),
        on_progress_change: (task, progress) => saveGanttChange(task, task._start || task.start, task._end || task.end, progress).catch(error => { alert(error.message); window.location.reload(); }),
        custom_popup_html: task => {
            const source = projectTimelineItems.find(item => item.id === task.id);
            const kind = 'Zadanie harmonogramu';
            const person = source?.assignee ? '<div style="margin-top:4px">Osoba: ' + escapeProjectHtml(source.assignee) + '</div>' : '';
            const dependency = source?.dependencies ? projectTimelineItems.find(item => item.id === source.dependencies)?.name : null;
            return '<div class="details-container"><strong>' + escapeProjectHtml(task.name) + '</strong><div>' + kind + ' · ' + task.progress + '%</div><div>' + localDate(task._start || task.start) + ' – ' + localDate(task._end || task.end) + '</div>' + person + (dependency ? '<div>Zależne od: ' + escapeProjectHtml(dependency) + '</div>' : '') + '</div>';
        },
    });
    setTimeout(bindGanttTaskEditing, 50);
}

document.querySelectorAll('.gantt-mode').forEach(button => button.addEventListener('click', () => {
    initProjectGantt();
    if (!projectGantt) return;
    projectGanttMode = button.dataset.mode;
    projectGantt.change_view_mode(projectGanttMode);
    document.querySelectorAll('.gantt-mode').forEach(item => item.classList.toggle('active', item === button));
}));
document.getElementById('gantt-today')?.addEventListener('click', () => {
    initProjectGantt();
    const highlight = document.querySelector('#project-frappe-gantt .today-highlight');
    const scroller = document.querySelector('#project-frappe-gantt .gantt-container') || document.getElementById('project-frappe-gantt');
    if (highlight && scroller) scroller.scrollLeft = Math.max(0, parseFloat(highlight.getAttribute('x') || 0) - scroller.clientWidth / 2);
});

function taskDurationDays(start, end) {
    return Math.max(1, Math.round((new Date(end + 'T12:00:00') - new Date(start + 'T12:00:00')) / 86400000) + 1);
}
function populateDependencyOptions(editingId = null) {
    const select = document.getElementById('gantt-task-dependency');
    if (!select) return;
    select.innerHTML = '<option value="">Brak — zadanie główne</option>';
    projectTimelineItems.filter(item => item.kind === 'task' && item.id !== editingId).forEach(item => {
        const option = document.createElement('option'); option.value = item.id; option.textContent = item.name; select.appendChild(option);
    });
}
function openGanttTaskModal(taskId = null) {
    const modal = document.getElementById('gantt-task-modal');
    if (!modal) return;
    document.getElementById('gantt-task-form').reset();
    document.getElementById('gantt-task-id').value = taskId || '';
    populateDependencyOptions(taskId);
    const task = taskId ? projectTimelineItems.find(item => item.id === taskId && item.kind === 'task') : null;
    const today = localDate(new Date());
    document.getElementById('gantt-modal-title').textContent = task ? 'Edytuj zadanie' : 'Dodaj zadanie';
    document.getElementById('gantt-task-title').value = task?.name || '';
    document.getElementById('gantt-task-start').value = task?.start || today;
    document.getElementById('gantt-task-end').value = task?.end || today;
    document.getElementById('gantt-task-duration').value = task ? taskDurationDays(task.start, task.end) : 1;
    document.getElementById('gantt-task-progress').value = task?.progress || 0;
    document.getElementById('gantt-task-dependency').value = task?.dependencies || '';
    document.getElementById('gantt-task-assignee').value = task?.assigned_to || '';
    document.getElementById('gantt-task-priority').value = task?.priority || 'medium';
    document.getElementById('gantt-task-description').value = task?.description || '';
    modal.classList.add('open');
}
function closeGanttTaskModal() { document.getElementById('gantt-task-modal')?.classList.remove('open'); }
function bindGanttTaskEditing() {
    if (!projectCanEdit) return;
    document.querySelectorAll('#project-frappe-gantt .bar-wrapper').forEach(wrapper => {
        const id = wrapper.getAttribute('data-id');
        if (id?.startsWith('task-')) wrapper.addEventListener('dblclick', () => openGanttTaskModal(id));
    });
}
function ganttTaskTiming(task) {
    const today = new Date(); today.setHours(0,0,0,0);
    const end = new Date(task.end + 'T00:00:00');
    const days = Math.ceil((end - today) / 86400000);
    if (Number(task.progress) >= 100) return {status:'done',label:'✓ Wykonano',days,daysLabel:days >= 0 ? '+' + days : String(days)};
    if (days < 0) return {status:'overdue',label:'Po terminie',days,daysLabel:String(days)};
    return {status:'active',label:'W realizacji',days,daysLabel:'+' + days};
}
function renderGanttTaskList() {
    const container = document.getElementById('gantt-task-list');
    if (!container) return;
    if (!projectTimelineItems.length) {container.innerHTML='<div class="empty">Brak zadań w harmonogramie.</div>';return;}
    const rows = projectTimelineItems.map((task,index) => {
        const timing=ganttTaskTiming(task), dependency=task.dependencies ? projectTimelineItems.find(item=>item.id===task.dependencies)?.name || '—' : '—';
        const actions=projectCanEdit ? '<div class="mini-actions"><button class="mini-btn edit list-edit" data-index="'+index+'" title="Edytuj">✎</button><button class="mini-btn delete list-delete" data-index="'+index+'" title="Usuń">×</button><button class="mini-btn move list-up" data-index="'+index+'" title="Przesuń wyżej" '+(index===0?'disabled':'')+'>↑</button><button class="mini-btn move list-down" data-index="'+index+'" title="Przesuń niżej" '+(index===projectTimelineItems.length-1?'disabled':'')+'>↓</button></div>' : '';
        const slider=projectCanEdit ? '<div class="progress-wrap"><input class="list-progress" data-index="'+index+'" type="range" min="0" max="100" value="'+task.progress+'"><strong>'+task.progress+'%</strong></div>' : task.progress+'%';
        return '<tr class="'+(timing.status==='done'?'done-row':timing.status==='overdue'?'overdue-row':'')+'"><td><strong>'+escapeProjectHtml(task.name)+'</strong><br><small>Zależne od: '+escapeProjectHtml(dependency)+'</small></td><td>'+escapeProjectHtml(task.assignee||'—')+'</td><td title="'+escapeProjectHtml(task.description||'')+'">'+escapeProjectHtml(task.description ? (task.description.length>55?task.description.slice(0,55)+'…':task.description) : '—')+'</td><td>'+localDate(task.end)+'</td><td>'+slider+'</td><td><span class="task-status '+timing.status+'">'+timing.label+'</span></td><td><span class="days-value '+(timing.days<0?'late':'ok')+'">'+timing.daysLabel+'</span></td><td>'+actions+'</td></tr>';
    }).join('');
    container.innerHTML='<div style="overflow-x:auto"><table class="gantt-list-table"><thead><tr><th>Zadanie</th><th>Osoba</th><th>Opis</th><th>Termin</th><th>Wykonanie</th><th>Status</th><th>Dni</th><th style="text-align:right">Akcje</th></tr></thead><tbody>'+rows+'</tbody></table></div>';
    container.querySelectorAll('.list-edit').forEach(button=>button.addEventListener('click',()=>openGanttTaskModal(projectTimelineItems[Number(button.dataset.index)].id)));
    container.querySelectorAll('.list-delete').forEach(button=>button.addEventListener('click',()=>deleteGanttTask(Number(button.dataset.index))));
    container.querySelectorAll('.list-up').forEach(button=>button.addEventListener('click',()=>moveGanttTask(Number(button.dataset.index),-1)));
    container.querySelectorAll('.list-down').forEach(button=>button.addEventListener('click',()=>moveGanttTask(Number(button.dataset.index),1)));
    container.querySelectorAll('.list-progress').forEach(slider=>slider.addEventListener('change',()=>{const task=projectTimelineItems[Number(slider.dataset.index)];saveGanttChange(task,task.start,task.end,Number(slider.value)).then(()=>renderGanttTaskList()).catch(error=>alert(error.message));}));
}
async function persistGanttOrder() {
    const response=await fetch(@json(route('projects.tasks.reorder',$project)),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':projectCsrfToken},body:JSON.stringify({order:projectTimelineItems.map(item=>item.db_id)})});
    if(!response.ok){const data=await response.json().catch(()=>({}));throw new Error(data.message||'Nie udało się zapisać kolejności.');}
}
async function moveGanttTask(index,direction) {
    const target=index+direction;if(target<0||target>=projectTimelineItems.length)return;
    [projectTimelineItems[index],projectTimelineItems[target]]=[projectTimelineItems[target],projectTimelineItems[index]];
    try{await persistGanttOrder();renderProjectGantt();}catch(error){[projectTimelineItems[index],projectTimelineItems[target]]=[projectTimelineItems[target],projectTimelineItems[index]];alert(error.message);renderGanttTaskList();}
}
async function deleteGanttTask(index) {
    const task=projectTimelineItems[index];if(!window.confirm('Usunąć zadanie „'+task.name+'”?'))return;
    const response=await fetch(task.delete_url,{method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':projectCsrfToken}});
    if(!response.ok){const data=await response.json().catch(()=>({}));alert(data.message||'Nie udało się usunąć zadania.');return;}
    projectTimelineItems.splice(index,1);projectTimelineItems.forEach(item=>{if(item.dependencies===task.id)item.dependencies='';});renderProjectGantt();
}
document.getElementById('gantt-add-task')?.addEventListener('click', () => openGanttTaskModal());
document.getElementById('gantt-task-start')?.addEventListener('change', event => {
    const duration = Number(document.getElementById('gantt-task-duration').value || 1);
    const end = new Date(event.target.value + 'T12:00:00'); end.setDate(end.getDate() + duration - 1);
    document.getElementById('gantt-task-end').value = localDate(end);
});
document.getElementById('gantt-task-duration')?.addEventListener('input', event => {
    const start = new Date(document.getElementById('gantt-task-start').value + 'T12:00:00'); start.setDate(start.getDate() + Math.max(1, Number(event.target.value || 1)) - 1);
    document.getElementById('gantt-task-end').value = localDate(start);
});
document.getElementById('gantt-task-end')?.addEventListener('change', event => {
    const start = document.getElementById('gantt-task-start').value;
    if (event.target.value < start) event.target.value = start;
    document.getElementById('gantt-task-duration').value = taskDurationDays(start, event.target.value);
});
document.getElementById('gantt-task-form')?.addEventListener('submit', async event => {
    event.preventDefault();
    const editingId = document.getElementById('gantt-task-id').value;
    const existing = editingId ? projectTimelineItems.find(item => item.id === editingId) : null;
    const progress = Number(document.getElementById('gantt-task-progress').value || 0);
    const dependency = document.getElementById('gantt-task-dependency').value;
    const payload = {
        title: document.getElementById('gantt-task-title').value,
        start_date: document.getElementById('gantt-task-start').value,
        due_date: document.getElementById('gantt-task-end').value,
        progress,
        status: progress >= 100 ? 'done' : (progress > 0 ? 'in_progress' : 'todo'),
        priority: document.getElementById('gantt-task-priority').value,
        assigned_to: document.getElementById('gantt-task-assignee').value || null,
        depends_on_task_id: dependency ? Number(dependency.replace('task-', '')) : null,
        description: document.getElementById('gantt-task-description').value || null,
    };
    const saveButton = document.getElementById('gantt-task-save'); saveButton.disabled = true;
    try {
        const response = await fetch(existing?.update_url || @json(route('projects.tasks.store', $project)), {method: existing ? 'PATCH' : 'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':projectCsrfToken}, body:JSON.stringify(payload)});
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Nie udało się zapisać zadania.');
        if (Array.isArray(data.project_tasks)) {
            data.project_tasks.forEach(updated => {const index=projectTimelineItems.findIndex(item=>item.id===updated.id);if(index>=0)projectTimelineItems[index]=updated;});
        } else if (existing) {
            const index = projectTimelineItems.findIndex(item => item.id === existing.id); projectTimelineItems[index] = data;
        } else projectTimelineItems.push(data);
        closeGanttTaskModal(); renderProjectGantt();
    } catch (error) { alert(error.message); } finally { saveButton.disabled = false; }
});

document.getElementById('gantt-share')?.addEventListener('click', async () => {
    try {
        const response = await fetch(@json(route('projects.public-gantt.generate', $project)), {method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':projectCsrfToken}});
        const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Nie udało się utworzyć linku.');
        try { await navigator.clipboard.writeText(data.url); alert('Link dla klienta został skopiowany:\n\n' + data.url); }
        catch { window.prompt('Skopiuj publiczny link do harmonogramu:', data.url); }
    } catch (error) { alert(error.message); }
});
document.getElementById('gantt-export')?.addEventListener('click', () => {
    if (typeof XLSX === 'undefined') { alert('Nie udało się załadować eksportu Excel. Odśwież stronę.'); return; }
    const rows = projectTimelineItems.map(item => ({
        'Rodzaj': 'Zadanie', 'Nazwa': item.name,
        'Data rozpoczęcia': item.start, 'Data zakończenia': item.end,
        'Czas trwania (dni)': taskDurationDays(item.start,item.end), 'Postęp (%)': item.progress,
        'Zależne od': item.dependencies ? projectTimelineItems.find(source=>source.id===item.dependencies)?.name || '' : '',
        'Osoba odpowiedzialna': item.assignee || '', 'Opis': item.description || '',
    }));
    const workbook=XLSX.utils.book_new(), sheet=XLSX.utils.json_to_sheet(rows); sheet['!cols']=[{wch:12},{wch:35},{wch:18},{wch:18},{wch:20},{wch:13},{wch:35},{wch:28},{wch:45}];
    XLSX.utils.book_append_sheet(workbook,sheet,'Harmonogram'); XLSX.writeFile(workbook,'Harmonogram_{{ preg_replace('/[^A-Za-z0-9_-]/','_',$project->number) }}.xlsx');
});

const cashflowState = {mode: 'month', cumulative: true, offset: 0};
function financePeriodKey(date, mode) {
    if (mode === 'day') return date;
    if (mode === 'month') return date.slice(0, 7);
    if (mode === 'year') return date.slice(0, 4);
    const value = new Date(date + 'T12:00:00');
    value.setDate(value.getDate() - ((value.getDay() + 6) % 7));
    return localDate(value);
}
function financePeriodLabel(key, mode) {
    if (mode === 'year') return key;
    if (mode === 'month') return new Date(key + '-01T12:00:00').toLocaleDateString('pl-PL', {month:'short', year:'numeric'});
    if (mode === 'week') return 'Tydz. ' + new Date(key + 'T12:00:00').toLocaleDateString('pl-PL');
    return new Date(key + 'T12:00:00').toLocaleDateString('pl-PL');
}
function groupedFinanceData() {
    const grouped = new Map();
    projectFinanceItems.forEach(item => {
        const key = financePeriodKey(item.date, cashflowState.mode);
        if (!grouped.has(key)) grouped.set(key, {invoice:0, plannedInvoice:0, cost:0, plannedCost:0});
        const row = grouped.get(key);
        if (item.type === 'invoice' && item.status === 'planned') row.plannedInvoice += Number(item.amount);
        else if (item.type === 'cost' && item.status === 'planned') row.plannedCost += Number(item.amount);
        else row[item.type] += Number(item.amount);
    });
    return [...grouped.entries()].sort((a, b) => a[0].localeCompare(b[0]));
}
function renderProjectCashflow() {
    if (typeof Chart === 'undefined' || !projectFinanceItems.length) return;
    const allRows = groupedFinanceData();
    const pageSize = {day:31, week:16, month:12, year:6}[cashflowState.mode];
    const maxOffset = Math.max(0, allRows.length - pageSize);
    cashflowState.offset = Math.max(0, Math.min(cashflowState.offset, maxOffset));
    const rows = allRows.slice(cashflowState.offset, cashflowState.offset + pageSize);
    let invoiceTotal = 0, costTotal = 0, plannedInvoiceTotal = 0, plannedCostTotal = 0;
    if (cashflowState.cumulative) {
        allRows.slice(0, cashflowState.offset).forEach(([, row]) => {invoiceTotal += row.invoice; costTotal += row.cost; plannedInvoiceTotal += row.plannedInvoice; plannedCostTotal += row.plannedCost;});
    }
    const invoice = [], cost = [], plannedInvoice = [], plannedCost = [], result = [], forecast = [], contract = [];
    rows.forEach(([, row]) => {
        if (cashflowState.cumulative) {invoiceTotal += row.invoice; costTotal += row.cost; plannedInvoiceTotal += row.plannedInvoice; plannedCostTotal += row.plannedCost;} else {invoiceTotal = row.invoice; costTotal = row.cost; plannedInvoiceTotal = row.plannedInvoice; plannedCostTotal = row.plannedCost;}
        invoice.push(invoiceTotal); cost.push(costTotal); plannedInvoice.push(plannedInvoiceTotal); plannedCost.push(plannedCostTotal); result.push(invoiceTotal - costTotal); forecast.push(invoiceTotal + plannedInvoiceTotal - costTotal - plannedCostTotal); contract.push(projectContractValue);
    });
    const context = document.getElementById('project-cashflow-chart');
    projectCashflowChart?.destroy();
    projectCashflowChart = new Chart(context, {
        data: {labels: rows.map(([key]) => financePeriodLabel(key, cashflowState.mode)), datasets: [
            {type:'bar', label:'Faktury', data:invoice, backgroundColor:'rgba(22,163,74,.72)', borderColor:'#15803d', borderWidth:1},
            {type:'bar', label:'Koszty', data:cost, backgroundColor:'rgba(220,38,38,.66)', borderColor:'#b91c1c', borderWidth:1},
            {type:'bar', label:'Planowane faktury', data:plannedInvoice, backgroundColor:'rgba(124,58,237,.35)', borderColor:'#7c3aed', borderWidth:1},
            {type:'bar', label:'Planowane koszty', data:plannedCost, backgroundColor:'rgba(245,158,11,.35)', borderColor:'#d97706', borderWidth:1},
            {type:'line', label:'Zysk / strata', data:result, borderColor:'#2563eb', backgroundColor:'#2563eb', borderWidth:3, pointRadius:4, tension:.25},
            {type:'line', label:'Prognozowany wynik', data:forecast, borderColor:'#7c3aed', backgroundColor:'#7c3aed', borderDash:[7,5], borderWidth:2, pointRadius:2, tension:.25},
            {type:'line', label:'Wartość kontraktu', data:contract, borderColor:'#64748b', borderDash:[3,5], borderWidth:1, pointRadius:0},
        ]},
        options: {responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false}, plugins:{tooltip:{callbacks:{label:context => context.dataset.label + ': ' + Number(context.raw).toLocaleString('pl-PL',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' zł'}}}, scales:{y:{beginAtZero:true,ticks:{callback:value => Number(value).toLocaleString('pl-PL') + ' zł'}},x:{grid:{display:false}}}},
    });
    renderCashflowOverview(allRows, pageSize);
    const range = document.getElementById('cashflow-range');
    range.textContent = rows.length ? financePeriodLabel(rows[0][0], cashflowState.mode) + ' – ' + financePeriodLabel(rows.at(-1)[0], cashflowState.mode) : 'Brak danych';
}
function renderCashflowOverview(allRows, pageSize) {
    const canvas = document.getElementById('project-cashflow-overview');
    if (!canvas) return;
    let balance = 0;
    const values = allRows.map(([, row]) => balance += row.invoice + row.plannedInvoice - row.cost - row.plannedCost);
    projectCashflowOverview?.destroy();
    projectCashflowOverview = new Chart(canvas, {
        type:'line',
        data:{labels:allRows.map(([key])=>financePeriodLabel(key,cashflowState.mode)),datasets:[{data:values,borderColor:'#64748b',backgroundColor:'rgba(100,116,139,.12)',fill:true,pointRadius:0,borderWidth:1.5,tension:.2}]},
        options:{responsive:true,maintainAspectRatio:false,onClick:(_,points)=>{if(points[0]){cashflowState.offset=Math.max(0,Math.min(points[0].index-Math.floor(pageSize/2),Math.max(0,allRows.length-pageSize)));renderProjectCashflow();}},plugins:{legend:{display:false},tooltip:{enabled:false}},scales:{x:{display:false},y:{display:false}}}
    });
}
function initProjectCashflow() { if (!projectCashflowChart) renderProjectCashflow(); }
document.querySelectorAll('.cashflow-mode').forEach(button => button.addEventListener('click', () => {
    cashflowState.mode = button.dataset.mode; cashflowState.offset = Number.MAX_SAFE_INTEGER;
    document.querySelectorAll('.cashflow-mode').forEach(item => item.classList.toggle('active', item === button)); renderProjectCashflow();
}));
document.getElementById('cashflow-prev')?.addEventListener('click', () => {cashflowState.offset -= {day:31,week:16,month:12,year:6}[cashflowState.mode];renderProjectCashflow();});
document.getElementById('cashflow-next')?.addEventListener('click', () => {cashflowState.offset += {day:31,week:16,month:12,year:6}[cashflowState.mode];renderProjectCashflow();});
document.getElementById('cashflow-reset')?.addEventListener('click', () => {cashflowState.offset = Number.MAX_SAFE_INTEGER;renderProjectCashflow();});
document.getElementById('cashflow-cumulative')?.addEventListener('click', event => {cashflowState.cumulative = !cashflowState.cumulative;event.currentTarget.classList.toggle('active',cashflowState.cumulative);event.currentTarget.textContent = cashflowState.cumulative ? 'Narastająco' : 'W okresie';renderProjectCashflow();});
document.querySelectorAll('.register-tab').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('.register-tab').forEach(item => item.classList.toggle('active', item === button));
    document.querySelectorAll('[data-finance-type]').forEach(row => row.classList.toggle('finance-row-hidden', button.dataset.financeFilter !== 'all' && row.dataset.financeType !== button.dataset.financeFilter));
}));
document.getElementById('finance-select-all')?.addEventListener('change', event => {
    document.querySelectorAll('.finance-entry-check').forEach(checkbox => checkbox.checked = event.currentTarget.checked);
});
document.querySelectorAll('[data-finance-section]').forEach(section => {
    const key = 'project-finance-section-{{$project->id}}-' + section.dataset.financeSection;
    const remembered = localStorage.getItem(key);
    if (remembered !== null && !section.matches('[data-finance-section="import"]')) section.open = remembered === '1';
    section.addEventListener('toggle', () => localStorage.setItem(key, section.open ? '1' : '0'));
});
if (requestedProjectTab) {
    const requestedButton = [...document.querySelectorAll('.tab')].find(button => button.getAttribute('onclick')?.includes("'" + requestedProjectTab + "'"));
    if (requestedButton && document.getElementById('pane-' + requestedProjectTab)) openProjectTab(requestedProjectTab, requestedButton);
}
</script>
@endsection
