@extends($clientView ? 'layouts.client' : 'layouts.app')
@section('page-title',$audit->title)
@push('styles')
<style>
.aw-head{display:flex;justify-content:space-between;gap:16px;margin-bottom:18px}.aw-head h1{font-size:24px;margin:3px 0}.aw-meta{display:flex;gap:14px;flex-wrap:wrap;color:#65736b;font-size:12px}.aw-badge{display:inline-flex;padding:5px 9px;border-radius:20px;background:#eaf4ef;color:var(--green);font-size:11px;font-weight:800}.aw-tabs{display:flex;overflow:auto;border-bottom:2px solid #dedbd3}.aw-tab{border:0;background:transparent;padding:12px 14px;font:700 12px Manrope;color:#69766e;white-space:nowrap;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px}.aw-tab.active{color:var(--green);border-color:var(--green)}.aw-pane{display:none;padding-top:17px}.aw-pane.active{display:block}.aw-card{background:#fff;border:1px solid #e3dfd7;border-radius:11px;padding:18px;margin-bottom:14px}.aw-card h2{font-size:15px;margin-bottom:13px}.aw-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:11px}.aw-grid>div:not(.aw-field){display:flex;flex-direction:column;gap:5px}.aw-grid small{color:#718078;font-weight:700}.aw-field{display:flex;flex-direction:column;gap:5px}.aw-field.wide,.wide{grid-column:span 2}.aw-field.full,.full{grid-column:1/-1}.aw-field label{font-size:10px;font-weight:800;color:#56635b}.aw-input{border:1px solid #d7d2c8;border-radius:7px;padding:9px 10px;font:12px Manrope;background:#fff}.aw-btn{border:0;border-radius:7px;background:var(--green);color:#fff;padding:9px 12px;font:700 12px Manrope;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px}.aw-btn.soft{background:#eaf4ef;color:var(--green)}.aw-btn.red{background:#b91c1c}.aw-actions{display:flex;justify-content:flex-end;gap:7px;margin-bottom:12px}.aw-table{width:100%;border-collapse:collapse}.aw-table th,.aw-table td{text-align:left;padding:10px;border-bottom:1px solid #eee;font-size:11px}.aw-table th{background:#faf9f5;color:#738078;text-transform:uppercase;font-size:9px}.aw-empty{text-align:center;padding:28px;color:#7b8780}.aw-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:16px}.aw-modal.open{display:flex}.aw-modal-box{background:#fff;border-radius:13px;padding:20px;width:min(760px,100%);max-height:90vh;overflow:auto}.team{display:flex;flex-wrap:wrap;gap:8px}.person{padding:7px 10px;border-radius:999px;background:#f0f5f1;font-size:12px}.member-checks{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:10px;border:1px solid #ddd;border-radius:8px}.member-check{display:flex;align-items:center;gap:7px;font-size:12px}.finance-summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:15px}.finance-kpi{background:#fff;border:1px solid #e3dfd7;border-radius:10px;padding:14px}.finance-kpi small{display:block;color:#6d786f}.finance-kpi strong{font-size:17px}.finance-search{display:flex;align-items:center;gap:8px;border:1px solid #ddd;border-radius:8px;padding:8px 10px;margin-bottom:12px}.finance-search input{border:0;outline:0;width:100%}.gantt-scale,.gantt-row{display:grid;grid-template-columns:240px 1fr;gap:14px;padding:9px;border-bottom:1px solid #eee}.gantt-scale{font-size:10px;font-weight:800;text-transform:uppercase;color:#78827c}.gantt-row>div:first-child{display:flex;flex-direction:column}.gantt-track{background:#f3f4f1;border-radius:6px;padding:5px;position:relative}.gantt-bar{height:19px;min-width:4%;background:#8b5cf6;border-radius:5px;color:#fff;text-align:right;padding:2px 5px;font-size:10px}.gantt-bar.done{background:#16a34a}.gantt-track small{display:block;margin-top:4px}.mini-actions{display:flex;gap:5px}.mini-btn{border:0;border-radius:5px;width:28px;height:27px;color:#fff;cursor:pointer}.mini-btn.edit{background:#2563eb}.mini-btn.delete{background:#dc2626}@media(max-width:850px){.aw-grid{grid-template-columns:1fr 1fr}.finance-summary-grid{grid-template-columns:1fr 1fr}.member-checks{grid-template-columns:1fr 1fr}}@media(max-width:550px){.aw-grid{grid-template-columns:1fr}.aw-field.full,.full,.wide{grid-column:auto}.gantt-scale,.gantt-row{grid-template-columns:1fr}.member-checks{grid-template-columns:1fr}}
    .gantt-toolbar,.chart-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin-bottom:13px}.tool-label{font-size:11px;font-weight:800;color:#66736b;margin-right:3px}.tool-btn{border:1px solid #d8d3c8;background:#fff;color:#46524b;border-radius:7px;padding:7px 10px;font:inherit;font-size:11px;font-weight:800;cursor:pointer}.tool-btn:hover,.tool-btn.active{background:var(--green);border-color:var(--green);color:#fff}.tool-btn.primary{background:var(--green);border-color:var(--green);color:#fff}.gantt-help{padding:9px 11px;background:#f6f8f5;border:1px solid #e1e6df;border-radius:7px;color:#66736b;font-size:11px;margin-bottom:12px}.frappe-gantt-wrap{min-height:260px;overflow-x:auto;border:1px solid #e3e0d8;border-radius:9px;background:#fff}.frappe-gantt-wrap .gantt-container{overflow:visible}.frappe-gantt-wrap svg{min-width:100%}.frappe-gantt-wrap.gantt-readonly svg{pointer-events:none}.frappe-gantt-wrap .bar-wrapper.stage-row .bar{fill:#2563eb}.frappe-gantt-wrap .bar-wrapper.stage-row .bar-progress{fill:#1d4ed8}.frappe-gantt-wrap .bar-wrapper.task-row .bar{fill:#8b5cf6}.frappe-gantt-wrap .bar-wrapper.task-row .bar-progress{fill:#6d28d9}.frappe-gantt-wrap .bar-label{font-size:11px;font-weight:700}.gantt-fallback{padding:45px;text-align:center;color:#777}.project-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center;padding:16px}.project-modal.open{display:flex}.project-modal-box{width:min(760px,100%);max-height:calc(100vh - 32px);overflow:auto;background:#fff;border-radius:13px;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,.22)}.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}.modal-head h2{margin:0}.modal-close{border:0;background:none;font-size:25px;cursor:pointer}.finance-summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin-bottom:16px}.finance-kpi{border-radius:9px;padding:12px 13px;background:#f7f8f5;border:1px solid #e4e5df}.finance-kpi small{display:block;color:#6d786f;font-size:10px;font-weight:800;margin-bottom:5px}.finance-kpi strong{font-size:15px}.chart-shell{height:330px;position:relative}.chart-range{font-size:11px;color:#66736b;font-weight:700;padding:7px 10px;background:#f7f8f5;border-radius:7px}.finance-register-tabs{display:flex;gap:5px;margin-bottom:12px}.register-tab{border:1px solid #d8d3c8;background:#f7f8f5;border-radius:7px;padding:7px 11px;font-size:11px;font-weight:800;cursor:pointer}.register-tab.active{background:#243f31;color:#fff;border-color:#243f31}.finance-row-hidden{display:none}@media(max-width:1050px){.finance-summary-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:700px){.finance-summary-grid{grid-template-columns:1fr 1fr}.chart-shell{height:280px}}
    .gantt-list-table{min-width:1020px}.gantt-list-table tr.done-row{background:#f2fbf5}.gantt-list-table tr.overdue-row{background:#fff1f1}.gantt-select-cell{width:38px;text-align:center}.gantt-task-check,.gantt-check-all{width:16px;height:16px;accent-color:var(--green);cursor:pointer}.gantt-bulk-toolbar{display:flex;justify-content:flex-end;align-items:center;gap:9px;margin-bottom:10px}.gantt-bulk-count{font-size:11px;color:#66736b;font-weight:700}.gantt-bulk-delete{border:0;border-radius:7px;padding:7px 10px;background:#b91c1c;color:#fff;font-size:11px;font-weight:800;cursor:pointer}.gantt-bulk-delete:disabled{opacity:.4;cursor:not-allowed}.progress-wrap{display:flex;align-items:center;gap:7px;min-width:190px}.progress-wrap input[type=range]{width:150px;height:6px;accent-color:#2563eb;cursor:pointer}.progress-wrap strong{min-width:36px;font-size:11px}.task-status{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap}.task-status.done{background:#d1fae5;color:#047857}.task-status.overdue{background:#fee2e2;color:#b91c1c}.task-status.active{background:#dbeafe;color:#1d4ed8}.days-value{font-size:11px;font-weight:800}.days-value.late{color:#dc2626}.days-value.ok{color:#15803d}.mini-actions{display:flex;gap:4px;justify-content:flex-end}.mini-btn{width:27px;height:25px;border:0;border-radius:5px;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;cursor:pointer;color:#fff}.mini-btn.edit{background:#2563eb}.mini-btn.delete{background:#dc2626}.mini-btn.move{background:#93c5fd}.mini-btn:disabled{opacity:.35;cursor:not-allowed}
    .frappe-gantt-wrap .today-highlight{fill:#ef4444!important;opacity:.16!important}.frappe-gantt-wrap .bar-wrapper.project-range-row{pointer-events:none}.frappe-gantt-wrap .bar-wrapper.project-range-row .bar{fill:#cbd5e1}.frappe-gantt-wrap .bar-wrapper.project-range-row .bar-progress{fill:#64748b}.frappe-gantt-wrap .bar-wrapper.project-range-row .handle{display:none}.frappe-gantt-wrap .bar-wrapper.milestone-row .bar{fill:#f59e0b;stroke:#b45309;stroke-width:1;transform:rotate(45deg) scale(.72);transform-box:fill-box;transform-origin:center;rx:0;ry:0}.frappe-gantt-wrap .bar-wrapper.milestone-row .bar-progress,.frappe-gantt-wrap .bar-wrapper.milestone-row .handle{display:none}.frappe-gantt-wrap .bar-wrapper.milestone-row.done-milestone .bar{fill:#16a34a;stroke:#15803d}.project-date-marker{pointer-events:none}.project-date-marker line{stroke-width:2}.project-date-marker.today line{stroke:#ef4444;stroke-dasharray:5 4}.project-date-marker.deadline line{stroke:#111827;stroke-dasharray:8 4}.project-date-marker text{font-size:10px;font-weight:800;paint-order:stroke;stroke:#fff;stroke-width:3px;stroke-linejoin:round}.project-date-marker.today text{fill:#dc2626}.project-date-marker.deadline text{fill:#111827}.milestone-badge{display:inline-flex;align-items:center;gap:4px;margin-left:6px;padding:3px 7px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:9px;font-weight:800}.milestone-note{padding:9px 11px;border-radius:7px;background:#fffbeb;color:#92400e;font-size:11px}.field-hidden{display:none!important}
    .finance-section{background:#fff;border:1px solid #e5e1d8;border-radius:11px;margin-bottom:14px;overflow:hidden}.finance-section>summary{display:flex;align-items:center;gap:10px;padding:15px 18px;cursor:pointer;font-size:15px;font-weight:800;list-style:none}.finance-section>summary::-webkit-details-marker{display:none}.finance-section>summary:before{content:'›';font-size:22px;line-height:1;transition:transform .18s}.finance-section[open]>summary:before{transform:rotate(90deg)}.finance-section>summary small{margin-left:auto;color:#77827b;font-size:10px;font-weight:700}.finance-section-body{border-top:1px solid #eee;padding:18px}.finance-import-note{font-size:11px;color:#66736b;line-height:1.5;background:#f7f8f5;border-radius:7px;padding:10px;margin-bottom:13px}.import-report{border:1px solid #bae6c6;background:#f0fdf4;border-radius:9px;padding:14px;margin-bottom:14px}.report-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.report-value{background:#fff;border-radius:7px;padding:9px}.report-value small{display:block;color:#66736b;font-size:9px;text-transform:uppercase}.report-value strong{font-size:15px}.finance-groups{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}.finance-group-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;background:#f0f5f1;border-radius:999px;font-size:11px}.finance-group-chip form{display:inline}.finance-group-chip button{border:0;background:none;color:#b91c1c;cursor:pointer;padding:0}.finance-table{min-width:1050px}.finance-table .finance-name-column{width:17%;max-width:220px}.finance-table .finance-name-column strong,.finance-table .finance-name-column small{overflow-wrap:anywhere}.finance-table .finance-amount-column{width:155px;min-width:155px;white-space:nowrap}.finance-invoice-note{padding:10px 12px;border-radius:8px;background:#edf5ef;color:#285740;font-size:11px;font-weight:700}.source-badge{display:inline-block;border-radius:999px;background:#eef2ff;color:#4338ca;padding:3px 6px;font-size:9px;font-weight:800}.finance-edit{position:relative}.finance-edit>summary{list-style:none}.finance-edit-box{position:absolute;right:0;z-index:20;width:min(650px,80vw);background:#fff;border:1px solid #d8d3c8;border-radius:9px;padding:14px;box-shadow:0 12px 35px rgba(0,0,0,.16)}.chart-overview-shell{height:90px;margin-top:8px;position:relative;border-top:1px solid #eee;padding-top:7px}@media(max-width:700px){.report-grid{grid-template-columns:1fr 1fr}.finance-section-body{padding:12px}}
    .requirements-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.requirements-head h2{margin:0}.requirements-head p{margin:4px 0 0;color:#6b776f;font-size:12px}.requirements-head-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.requirement-import{border:1px solid #dfe5df;border-radius:9px;background:#f8faf7;margin-bottom:14px}.requirement-import>summary{list-style:none;cursor:pointer;padding:11px 13px;font-size:12px;font-weight:800;color:var(--green)}.requirement-import>summary::-webkit-details-marker{display:none}.requirement-import>summary:before{content:'›';display:inline-block;margin-right:7px;font-size:18px;vertical-align:-1px;transition:transform .15s}.requirement-import[open]>summary:before{transform:rotate(90deg)}.requirement-import-body{border-top:1px solid #e4e8e3;padding:13px}.requirement-import-form{display:flex;align-items:center;gap:9px;flex-wrap:wrap}.requirement-import-form input{flex:1;min-width:230px;border:1px solid #d8d3c8;border-radius:7px;background:#fff;padding:8px}.requirement-import-help{font-size:11px;line-height:1.55;color:#657169;margin:0 0 11px}.requirement-search{display:flex;align-items:center;gap:9px;margin:0 0 12px;padding:10px 12px;border:1px solid #dfe5df;border-radius:9px;background:#fff}.requirement-search i{color:#66736b;font-size:18px}.requirement-search input{flex:1;min-width:180px;border:0;outline:0;background:transparent;font:inherit}.requirement-search-count{font-size:11px;font-weight:700;color:#66736b;white-space:nowrap}.requirement-search-empty{padding:22px;text-align:center;color:#66736b;background:#f8faf7;border-radius:8px;margin-top:10px}.requirement-search-empty[hidden]{display:none}.requirement-bulk-toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px;margin:0 0 12px;border:1px solid #dfe5df;border-radius:9px;background:#f8faf7}.requirement-bulk-toolbar select,.requirement-bulk-toolbar input{min-width:180px;padding:7px 9px;border:1px solid #d8d3c8;border-radius:7px;background:#fff}.requirement-bulk-value[hidden]{display:none}.requirement-selected-count{font-size:11px;font-weight:700;color:#66736b}.requirements-table-wrap{overflow-x:auto}.requirements-table{min-width:850px;table-layout:fixed}.requirements-table th,.requirements-table td{padding:9px 8px;vertical-align:middle}.requirements-table:not(.with-selection) th:nth-child(1){width:29%}.requirements-table:not(.with-selection) th:nth-child(2){width:9%}.requirements-table:not(.with-selection) th:nth-child(3){width:16%}.requirements-table:not(.with-selection) th:nth-child(4){width:15%}.requirements-table:not(.with-selection) th:nth-child(5){width:11%}.requirements-table:not(.with-selection) th:nth-child(6){width:11%}.requirements-table:not(.with-selection) th:nth-child(7){width:9%}.requirements-table.with-selection th:nth-child(1){width:4%}.requirements-table.with-selection th:nth-child(2){width:27%}.requirements-table.with-selection th:nth-child(3){width:9%}.requirements-table.with-selection th:nth-child(4){width:15%}.requirements-table.with-selection th:nth-child(5){width:14%}.requirements-table.with-selection th:nth-child(6){width:11%}.requirements-table.with-selection th:nth-child(7){width:12%}.requirements-table.with-selection th:nth-child(8){width:8%}.requirement-name{display:block;line-height:1.3}.requirement-meta{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:4px;color:#758078}.requirement-type{padding:3px 6px;border-radius:999px;background:#eef4ef;color:#285740;font-size:9px;font-weight:800}.requirement-description{display:block;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.requirement-qty{font-size:13px;font-weight:800;white-space:nowrap}.requirement-cost{font-weight:800;white-space:nowrap}.requirement-status{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:9px;font-weight:800;white-space:nowrap}.requirement-status.planned{background:#ede9fe;color:#6d28d9}.requirement-status.requested{background:#f1f5f9;color:#475569}.requirement-status.ordered{background:#fef3c7;color:#92400e}.requirement-status.in_progress{background:#dbeafe;color:#1d4ed8}.requirement-status.purchased{background:#d1fae5;color:#047857}.requirement-status.cancelled{background:#fee2e2;color:#b91c1c}.requirement-actions{display:flex;justify-content:flex-end;gap:5px}.requirement-modal-box{width:min(760px,100%)}@media(max-width:700px){.requirements-head{align-items:flex-start;flex-direction:column}.requirements-table{min-width:820px}.requirement-import-form{align-items:stretch;flex-direction:column}.requirement-import-form input{width:100%;min-width:0}.requirement-bulk-toolbar{align-items:stretch;flex-direction:column}.requirement-bulk-toolbar select,.requirement-bulk-toolbar input,.requirement-bulk-toolbar button{width:100%}}
    .requirements-table.with-selection th:nth-child(1){width:36px!important}.requirements-table.with-selection th:nth-child(2){width:27%!important}.requirements-table.with-selection th:nth-child(3){width:13%!important}.requirements-table.with-selection th:nth-child(4){width:68px!important}.requirements-table.with-selection th:nth-child(5){width:14%!important}.requirements-table.with-selection th:nth-child(6){width:14%!important}.requirements-table.with-selection th:nth-child(7){width:116px!important}.requirements-table.with-selection th:nth-child(8){width:155px!important}.requirements-table.with-selection th:nth-child(9){width:66px!important}.requirements-table:not(.with-selection) th:nth-child(1){width:29%!important}.requirements-table:not(.with-selection) th:nth-child(2){width:14%!important}.requirements-table:not(.with-selection) th:nth-child(3){width:68px!important}.requirements-table:not(.with-selection) th:nth-child(4){width:15%!important}.requirements-table:not(.with-selection) th:nth-child(5){width:15%!important}.requirements-table:not(.with-selection) th:nth-child(6){width:116px!important}.requirements-table:not(.with-selection) th:nth-child(7){width:155px!important}.requirements-table:not(.with-selection) th:nth-child(8){width:24px!important}.requirements-table td:last-child{min-width:66px;padding-left:4px;padding-right:4px}.requirements-table .requirement-actions{min-width:58px;justify-content:flex-end}.requirements-table .requirement-status{width:112px;max-width:112px}.requirements-table .requirement-qty{white-space:nowrap}
    .requirement-export-statuses{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px 12px;padding:10px;border:1px solid #d8d3c8;border-radius:8px;background:#fafaf7}.requirement-export-status{display:flex!important;flex-direction:row!important;align-items:center;gap:8px;font-size:12px!important;font-weight:600!important}.requirement-export-status input{width:16px;height:16px;accent-color:var(--green)}.requirement-export-note{padding:10px 12px;border-radius:8px;background:#f3f7f4;color:#536158;font-size:11px;line-height:1.5}@media(max-width:700px){.requirement-export-statuses{grid-template-columns:1fr}}
    .requirement-summary-kpi{cursor:pointer;transition:border-color .15s,box-shadow .15s,transform .15s}.requirement-summary-kpi:hover,.requirement-summary-kpi:focus{outline:0;border-color:var(--green);box-shadow:0 4px 14px rgba(26,77,58,.14);transform:translateY(-1px)}.requirement-summary-kpi.active{background:#edf5ef;border-color:var(--green);box-shadow:0 0 0 2px rgba(26,77,58,.12)}.requirement-filter-state{display:flex;align-items:center;gap:8px;margin:-3px 0 10px;padding:8px 10px;border-radius:8px;background:#edf5ef;color:#285740;font-size:11px;font-weight:700}.requirement-filter-state button{margin-left:auto;border:0;background:none;color:#285740;font:inherit;text-decoration:underline;cursor:pointer}
</style>
@endpush
@section('content')
@php
    $statusLabels = ['draft' => 'Roboczy', 'in_progress' => 'W trakcie', 'done' => 'Zakończony', 'cancelled' => 'Anulowany'];
    $income = $canViewFinances ? (float) $audit->financialEntries->where('type', 'invoice')->sum('amount') : 0;
    $cost = $canViewFinances ? (float) $audit->financialEntries->where('type', 'cost')->sum('amount') : 0;
@endphp
<a href="{{$clientView?route('client.audits'):route('companies.show',$audit->company)}}" style="color:var(--green);font-size:12px;font-weight:700;text-decoration:none"><i class="ti ti-arrow-left"></i> {{$clientView?'Moje audyty':'Karta klienta'}}</a>
<div class="aw-head"><div><small style="font-weight:800;color:var(--green)">{{$audit->number}}</small><h1>{{$audit->title}}</h1><div class="aw-meta"><span><i class="ti ti-building"></i> {{$audit->company->name}}</span><span><i class="ti ti-user-star"></i> {{$audit->manager?->name??'Brak kierownika'}}</span><span><i class="ti ti-calendar"></i> {{$audit->start_date?->format('d.m.Y')??'—'}} – {{$audit->end_date?->format('d.m.Y')??'—'}}</span></div></div><span class="aw-badge">{{$statusLabels[$audit->status]??$audit->status}}</span></div>
@if(session('success'))<div style="padding:11px 14px;background:#eaf7ee;color:#176139;border-radius:8px;margin-bottom:14px">{{session('success')}}</div>@endif
@if($errors->any())<div style="padding:11px 14px;background:#fff0f0;color:#991b1b;border-radius:8px;margin-bottom:14px">{{$errors->first()}}</div>@endif
<nav class="aw-tabs">@foreach(collect(['overview'=>'Podgląd','schedule'=>'Harmonogram i zadania','finances'=>'Finanse','documents'=>'Dokumenty','surveys'=>'Ankiety Audytowe','passports'=>'Paszporty Energetyczne'])->when(!$canViewFinances,fn($tabs)=>$tabs->forget('finances')) as $id=>$label)<button class="aw-tab {{$loop->first?'active':''}}" data-audit-tab="{{$id}}" onclick="showAuditTab('{{$id}}',this)">{{$label}}</button>@endforeach</nav>

<section id="aw-overview" class="aw-pane active"><div class="aw-actions">@if($canManage)<button type="button" class="aw-btn" onclick="openAwModal('audit-edit-modal')"><i class="ti ti-edit"></i> Edytuj audyt</button>@endif</div><div class="aw-card"><h2>Informacje o audycie</h2><div class="aw-grid"><div><small>Numer</small><strong>{{$audit->number}}</strong></div><div class="wide"><small>Nazwa</small><strong>{{$audit->title}}</strong></div><div><small>Status</small><strong>{{$statusLabels[$audit->status]??$audit->status}}</strong></div><div><small>Kierownik</small><strong>{{$audit->manager?->name??'—'}}</strong></div><div><small>Termin</small><strong>{{$audit->start_date?->format('d.m.Y')??'—'}} – {{$audit->end_date?->format('d.m.Y')??'—'}}</strong></div>@if($canViewFinances)<div><small>Wartość netto</small><strong>{{number_format((float)$audit->contract_value,2,',',' ')}} zł</strong></div>@endif<div class="full"><small>Opis</small><div style="white-space:pre-line">{{$audit->description?:'Brak opisu.'}}</div></div></div></div><div class="aw-card"><h2>Osoby przypisane do audytu</h2><div class="team"><span class="person"><strong>Kierownik:</strong> {{$audit->manager?->name??'—'}}</span>@foreach($audit->members as $member)<span class="person">{{$member->name}}</span>@endforeach</div></div></section>

<section id="aw-schedule" class="aw-pane">
    @if(session('gantt_import_report'))
        @php($ganttReport = session('gantt_import_report'))
        <div class="import-report" style="margin-bottom:16px"><strong>Import harmonogramu zakończony</strong><div class="report-grid" style="margin-top:10px"><div class="report-value"><small>Dodano</small><strong>{{$ganttReport['inserted']}}</strong></div><div class="report-value"><small>Duplikaty</small><strong>{{$ganttReport['duplicates']}}</strong></div><div class="report-value"><small>Błędne wiersze</small><strong>{{$ganttReport['invalid']}}</strong></div><div class="report-value"><small>Bez przypisanej osoby</small><strong>{{$ganttReport['unassigned']}}</strong></div></div></div>
    @endif
    <div class="card"><h2>Interaktywny wykres Gantta</h2>
        <div class="gantt-toolbar">
            @if($canManage)<button type="button" class="tool-btn primary" id="gantt-add-task"><i class="ti ti-plus"></i> Dodaj zadanie</button><button type="button" class="tool-btn" id="gantt-add-milestone"><i class="ti ti-diamond"></i> Dodaj kamień milowy</button>@endif
            <a class="tool-btn" style="text-decoration:none" href="{{$clientView?route('client.audits.gantt.export',$audit):route('audits.gantt.export',$audit)}}"><i class="ti ti-file-spreadsheet"></i> Eksport Excel</a>
            @if($canManage)<button type="button" class="tool-btn" onclick="document.getElementById('gantt-import-modal').classList.add('open')"><i class="ti ti-file-upload"></i> Import Excel</button>@endif
            <span class="tool-label">Widok:</span>
            @foreach(['Day'=>'Dzień','Week'=>'Tydzień','Month'=>'Miesiąc'] as $mode=>$label)<button type="button" class="tool-btn gantt-mode {{ $mode==='Week'?'active':'' }}" data-mode="{{ $mode }}">{{ $label }}</button>@endforeach
            <button type="button" class="tool-btn" id="gantt-today"><i class="ti ti-calendar-event"></i> Dzisiaj</button>
            <span class="legend" style="margin:0 0 0 auto"><span><i class="dot" style="background:#7C3AED"></i>Zadania</span><span><i class="dot" style="background:#f59e0b;transform:rotate(45deg);border-radius:1px"></i>Kamienie milowe</span><span>Linie: dziś i koniec projektu {{ $audit->end_date?->format('d.m.Y') ?? '—' }}</span></span>
        </div>
        @if($canManage)<div class="gantt-help"><strong>Obsługa:</strong> przeciągnij pasek, aby przesunąć termin; przeciągnij jego krawędź, aby zmienić czas trwania; przeciągnij uchwyt postępu, aby zapisać procent wykonania.</div>@endif
        <div id="project-frappe-gantt" class="frappe-gantt-wrap"></div>
    </div>
    <div class="card"><h2>Lista zadań i kamieni milowych <small style="font-weight:500;color:#78827b">(kolejność jak na Gantcie)</small></h2><div id="gantt-task-list"></div></div>
</section>

@if($canViewFinances)
<section id="aw-finances" class="aw-pane"><div class="finance-summary-grid"><div class="finance-kpi"><small>Wartość audytu</small><strong>{{number_format((float)$audit->contract_value,2,',',' ')}} zł</strong></div><div class="finance-kpi"><small>Faktury wystawione</small><strong>{{number_format($income,2,',',' ')}} zł</strong></div><div class="finance-kpi"><small>Koszty</small><strong>{{number_format($cost,2,',',' ')}} zł</strong></div><div class="finance-kpi"><small>Wynik</small><strong>{{number_format($income-$cost,2,',',' ')}} zł</strong></div></div>@if($canManage)<div class="aw-actions"><button class="aw-btn" type="button" onclick="openAuditFinance()"><i class="ti ti-plus"></i> Dodaj pozycję</button></div>@endif<div class="aw-card"><h2>Rejestr finansowy</h2><label class="finance-search"><i class="ti ti-search"></i><input id="audit-finance-search" type="search" placeholder="Szukaj po nazwie, dokumencie, statusie, kwocie lub dacie…"></label><div style="overflow:auto"><table class="aw-table"><thead><tr><th>Data</th><th>Rodzaj</th><th>Nazwa dokumentu</th><th>Numer</th><th>Status</th><th>Kwota</th><th>Akcje</th></tr></thead><tbody id="audit-finance-rows">@forelse($audit->financialEntries as $entry)<tr data-search="{{collect([$entry->entry_date->format('d.m.Y'),$entry->type,$entry->name,$entry->document_number,$entry->status,$entry->amount,$entry->notes])->filter()->implode(' ')}}"><td>{{$entry->entry_date->format('d.m.Y')}}</td><td>{{$entry->type==='invoice'?'Faktura':'Koszt'}}</td><td><strong>{{$entry->name}}</strong><br><small>{{$entry->notes}}</small></td><td>{{$entry->document_number?:'—'}}</td><td>{{['planned'=>'Planowana','issued'=>'Wystawiona / zaksięgowana','paid'=>'Opłacona'][$entry->status]??$entry->status}}</td><td style="white-space:nowrap">{{number_format((float)$entry->amount,2,',',' ')}} zł</td><td>@if($canManage)<div class="mini-actions"><button type="button" class="mini-btn edit" onclick='openAuditFinance(@json($entry))'>✎</button><form method="POST" action="{{route('audits.finances.destroy',[$audit,$entry])}}" onsubmit="return confirm('Usunąć pozycję?')">@csrf @method('DELETE')<button class="mini-btn delete">×</button></form></div>@endif</td></tr>@empty<tr><td colspan="7" class="aw-empty">Brak pozycji finansowych.</td></tr>@endforelse</tbody></table></div></div></section>
@endif

<section id="aw-documents" class="aw-pane">@if($canManage)<div class="aw-card"><h2>Dodaj dokument audytu</h2><form method="POST" enctype="multipart/form-data" action="{{route('audits.documents.store',$audit)}}">@csrf<input type="file" name="file" required> <button class="aw-btn">Wgraj dokument</button></form></div>@endif<div class="aw-card"><table class="aw-table"><thead><tr><th>Plik</th><th>Rozmiar</th><th>Dodał</th><th>Data</th><th></th></tr></thead><tbody>@forelse($audit->documents as $document)<tr><td><a href="{{$clientView ? route('client.audits.documents.download',[$audit,$document]) : route('audits.documents.download',[$audit,$document])}}">{{$document->original_filename}}</a></td><td>{{$document->formattedSize()}}</td><td>{{$document->uploader?->name??'System'}}</td><td>{{$document->created_at->format('d.m.Y H:i')}}</td><td>@if($canManage)<form method="POST" action="{{route('audits.documents.destroy',[$audit,$document])}}">@csrf @method('DELETE')<button class="aw-btn red">Usuń</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="aw-empty">Brak dokumentów.</td></tr>@endforelse</tbody></table></div></section>

<section id="aw-surveys" class="aw-pane">@if($canManage)<div class="aw-actions"><button class="aw-btn" onclick="openAwModal('survey-modal')"><i class="ti ti-plus"></i> Dodaj ankietę</button></div>@endif<div class="aw-card"><h2>Ankiety Audytowe</h2><table class="aw-table"><thead><tr><th>Nazwa</th><th>Status</th><th>Notatki</th><th></th></tr></thead><tbody>@forelse($audit->surveys as $survey)<tr><td><strong>{{$survey->title}}</strong></td><td>{{['draft'=>'Robocza','ready'=>'Gotowa','completed'=>'Wypełniona'][$survey->status]??$survey->status}}</td><td>{{$survey->notes}}</td><td>@if($canManage)<form method="POST" action="{{route('audits.surveys.destroy',[$audit,$survey])}}">@csrf @method('DELETE')<button class="aw-btn red">Usuń</button></form>@endif</td></tr>@empty<tr><td colspan="4" class="aw-empty">Dodaj pierwszą ankietę dla tego audytu.</td></tr>@endforelse</tbody></table></div></section>

<section id="aw-passports" class="aw-pane">@if($canManage)<div class="aw-actions"><button class="aw-btn" onclick="openAwModal('passport-modal')"><i class="ti ti-plus"></i> Dodaj paszport</button></div>@endif<div class="aw-card"><h2>Paszporty Energetyczne</h2><table class="aw-table"><thead><tr><th>Nazwa</th><th>Szablon</th><th>ID urządzenia</th><th>Status</th><th></th></tr></thead><tbody>@forelse($audit->energyPassports as $passport)<tr><td><strong>{{$passport->name}}</strong></td><td>{{$passport->template?->name}}</td><td>{{$passport->asset_identifier??'—'}}</td><td>{{$passport->status}}</td><td>@if(!$clientView)<a class="aw-btn soft" href="{{route('energy-passports.edit',$passport)}}">Otwórz</a>@else<span style="color:#718078">Podgląd</span>@endif</td></tr>@empty<tr><td colspan="5" class="aw-empty">Brak paszportów przypisanych do audytu.</td></tr>@endforelse</tbody></table></div></section>

@if($canManage)
<div id="audit-edit-modal" class="aw-modal"><div class="aw-modal-box"><h2>Edytuj audyt</h2><form method="POST" action="{{route('audits.update',$audit)}}">@csrf @method('PUT')<div class="aw-grid"><div class="aw-field"><label>Numer</label><input class="aw-input" name="number" value="{{$audit->number}}" required></div><div class="aw-field wide"><label>Nazwa</label><input class="aw-input" name="title" value="{{$audit->title}}" required></div><div class="aw-field"><label>Status</label><select class="aw-input" name="status">@foreach($statusLabels as $value=>$label)<option value="{{$value}}" @selected($audit->status===$value)>{{$label}}</option>@endforeach</select></div><div class="aw-field"><label>Kierownik</label><select class="aw-input" name="manager_id">@foreach($users as $user)<option value="{{$user->id}}" @selected($audit->manager_id===$user->id)>{{$user->name}}</option>@endforeach</select></div><div class="aw-field"><label>Start</label><input class="aw-input" type="date" name="start_date" value="{{$audit->start_date?->format('Y-m-d')}}"></div><div class="aw-field"><label>Koniec</label><input class="aw-input" type="date" name="end_date" value="{{$audit->end_date?->format('Y-m-d')}}"></div><div class="aw-field"><label>Wartość netto</label><input class="aw-input" type="number" step="0.01" min="0" name="contract_value" value="{{$audit->contract_value}}"></div><div class="aw-field full"><label>Osoby przypisane</label><div class="member-checks">@foreach($users as $user)<label class="member-check"><input type="checkbox" name="member_ids[]" value="{{$user->id}}" @checked($audit->members->contains($user))> {{$user->name}}</label>@endforeach</div></div><div class="aw-field full"><label>Opis</label><textarea class="aw-input" name="description" rows="4">{{$audit->description}}</textarea></div></div><div class="aw-actions"><button type="button" class="aw-btn soft" onclick="closeAwModal('audit-edit-modal')">Anuluj</button><button class="aw-btn">Zapisz zmiany</button></div></form></div></div>
<div id="gantt-task-modal" class="project-modal" onclick="if(event.target===this)closeGanttTaskModal()">
    <div class="project-modal-box">
        <div class="modal-head"><h2 id="gantt-modal-title">Dodaj zadanie</h2><button type="button" class="modal-close" onclick="closeGanttTaskModal()">×</button></div>
        <form id="gantt-task-form">
            <input type="hidden" id="gantt-task-id">
            <div class="grid2">
                <div class="field full"><label>Nazwa zadania *</label><input id="gantt-task-title" required></div>
                <div class="field full"><label>Typ pozycji</label><select id="gantt-task-type"><option value="task">Zadanie</option><option value="milestone">Kamień milowy — etap projektu</option></select></div>
                <div class="field" id="gantt-task-start-field"><label id="gantt-task-start-label">Data rozpoczęcia *</label><input type="date" id="gantt-task-start" required></div>
                <div class="field" id="gantt-task-duration-field"><label>Liczba dni *</label><input type="number" id="gantt-task-duration" min="1" value="1" required></div>
                <div class="field" id="gantt-task-end-field"><label>Data zakończenia *</label><input type="date" id="gantt-task-end" required></div>
                <div class="field full milestone-note field-hidden" id="gantt-milestone-note">Kamień milowy ma jeden termin. Na wykresie pojawi się jako romb, a jego status pokaże, czy etap został wykonany w terminie.</div>
                <div class="field"><label>Postęp (%)</label><input type="number" id="gantt-task-progress" min="0" max="100" value="0" required></div>
                <div class="field"><label>Zależne od zadania</label><select id="gantt-task-dependency"><option value="">Brak — zadanie główne</option></select></div>
                <div class="field"><label>Osoba odpowiedzialna</label><select id="gantt-task-assignee"><option value="">Nieprzypisane</option>@foreach($audit->members as $member)<option value="{{$member->id}}">{{$member->name}}</option>@endforeach</select></div>
                <div class="field"><label>Priorytet</label><select id="gantt-task-priority"><option value="low">Niski</option><option value="medium" selected>Średni</option><option value="high">Wysoki</option></select></div>
                <div class="field full"><label>Opis</label><textarea id="gantt-task-description" rows="3"></textarea></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px"><button type="button" class="btn btn-soft" onclick="closeGanttTaskModal()">Anuluj</button><button class="btn" id="gantt-task-save">Zapisz zadanie</button></div>
        </form>
    </div>
</div>
<div id="gantt-import-modal" class="project-modal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="project-modal-box" style="width:min(620px,100%)">
        <div class="modal-head"><div><h2>Import harmonogramu z Excela</h2><small style="color:#718078">Zadania, kolejność i zależności zostaną przeniesione do tego audytu.</small></div><button type="button" class="modal-close" onclick="document.getElementById('gantt-import-modal').classList.remove('open')">×</button></div>
        @if($errors->ganttImport->any())<div style="padding:11px 13px;background:#fef2f2;color:#991b1b;border-radius:8px;margin-bottom:14px;">{{$errors->ganttImport->first()}}</div>@endif
        <form method="POST" enctype="multipart/form-data" action="{{route('audits.gantt.import',$audit)}}">@csrf
            <div class="grid2">
                <div class="field full"><label>Plik harmonogramu *</label><input type="file" name="file" accept=".xlsx,.xls,.csv" required><small style="color:#718078">Obsługiwany jest nowy eksport oraz wcześniejszy format eksportu Gantta.</small></div>
                <div class="field full"><label>Nowa data rozpoczęcia harmonogramu</label><input type="date" name="new_start_date" value="{{old('new_start_date',$audit->start_date?->format('Y-m-d'))}}"><small style="color:#718078">Wszystkie zadania przesuną się o tę samą liczbę dni. Wyczyść pole, aby zachować daty z pliku.</small></div>
            </div>
            <div style="padding:10px 12px;background:#f6f8f5;border-radius:8px;margin-top:14px;font-size:12px;color:#66736b">Istniejące identyczne zadania nie zostaną dodane ponownie. Osoby z zespołu audytu są dopasowywane po adresie e-mail, a potem po unikalnej nazwie.</div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px"><button type="button" class="btn btn-soft" onclick="document.getElementById('gantt-import-modal').classList.remove('open')">Anuluj</button><button class="btn"><i class="ti ti-file-upload"></i> Importuj harmonogram</button></div>
        </form>
    </div>
</div>
@if($errors->ganttImport->any())<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('gantt-import-modal')?.classList.add('open'));</script>@endif
<div id="finance-modal" class="aw-modal"><div class="aw-modal-box"><h2 id="finance-modal-title">Nowa pozycja finansowa</h2><form id="audit-finance-form" method="POST" action="{{route('audits.finances.store',$audit)}}">@csrf<input type="hidden" name="_method" id="audit-finance-method" value="POST"><div class="aw-grid"><div class="aw-field"><label>Rodzaj</label><select class="aw-input" id="audit-finance-type" name="type"><option value="invoice">Faktura</option><option value="cost">Koszt</option></select></div><div class="aw-field wide"><label>Nazwa dokumentu</label><input class="aw-input" id="audit-finance-name" name="name" required></div><div class="aw-field"><label>Numer dokumentu</label><input class="aw-input" id="audit-finance-number" name="document_number"></div><div class="aw-field"><label>Data</label><input class="aw-input" id="audit-finance-date" type="date" name="entry_date" required></div><div class="aw-field"><label>Kwota netto</label><input class="aw-input" id="audit-finance-amount" type="number" step="0.01" min="0" name="amount" required></div><div class="aw-field"><label>Status</label><select class="aw-input" id="audit-finance-status" name="status"><option value="planned">Planowana</option><option value="issued">Wystawiona / zaksięgowana</option><option value="paid">Opłacona</option></select></div><div class="aw-field full"><label>Notatki</label><textarea class="aw-input" id="audit-finance-notes" name="notes"></textarea></div></div><div class="aw-actions"><button type="button" class="aw-btn soft" onclick="closeAwModal('finance-modal')">Anuluj</button><button class="aw-btn">Zapisz</button></div></form></div></div>
<div id="survey-modal" class="aw-modal"><div class="aw-modal-box"><h2>Nowa ankieta audytowa</h2><form method="POST" action="{{route('audits.surveys.store',$audit)}}">@csrf<div class="aw-field"><label>Typ audytu</label><select class="aw-input" name="audit_type_id" required><option value="">Wybierz typ audytu</option>@foreach($auditTypes as $auditType)<option value="{{$auditType->id}}">{{$auditType->name}}</option>@endforeach</select>@if($auditTypes->isEmpty())<small>Brak dostępnych typów audytu. Najpierw dodaj typ w ustawieniach audytów.</small>@endif</div><div class="aw-field"><label>Status</label><select class="aw-input" name="status"><option value="draft">Robocza</option><option value="ready">Gotowa</option><option value="completed">Wypełniona</option></select></div><div class="aw-field"><label>Notatki</label><textarea class="aw-input" name="notes"></textarea></div><div class="aw-actions"><button type="button" class="aw-btn soft" onclick="closeAwModal('survey-modal')">Anuluj</button><button class="aw-btn" @disabled($auditTypes->isEmpty())>Dodaj</button></div></form></div></div>
<div id="passport-modal" class="aw-modal"><div class="aw-modal-box"><h2>Nowy paszport energetyczny</h2><form method="POST" action="{{route('audits.passports.store',$audit)}}">@csrf<div class="aw-field"><label>Szablon z biblioteki</label><select class="aw-input" name="template_id" required>@foreach($passportTemplates as $template)<option value="{{$template->id}}">{{$template->name}} · {{$template->category}}</option>@endforeach</select></div><div class="aw-field"><label>Nazwa paszportu</label><input class="aw-input" name="name" required></div><div class="aw-grid"><div class="aw-field wide"><label>ID urządzenia</label><input class="aw-input" name="asset_identifier"></div><div class="aw-field wide"><label>Lokalizacja</label><input class="aw-input" name="location"></div></div><div class="aw-actions"><button type="button" class="aw-btn soft" onclick="closeAwModal('passport-modal')">Anuluj</button><button class="aw-btn">Dodaj i uzupełnij</button></div></form></div></div>
@endif
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.0/dist/frappe-gantt.min.js"></script>
<script>
const projectTimelineItems=@json($timelineItems),projectCanEdit=@json($canManage),projectStartDate=@json($audit->start_date?->format('Y-m-d')),projectEndDate=@json($audit->end_date?->format('Y-m-d')),projectCsrfToken=document.querySelector('meta[name="csrf-token"]')?.content||@json(csrf_token()),ganttBulkDeleteUrl=@json(route('audits.tasks.bulk-destroy',$audit));let projectGantt=null,projectGanttMode='Week';
function localDate(date){const value=new Date(date);return [value.getFullYear(),String(value.getMonth()+1).padStart(2,'0'),String(value.getDate()).padStart(2,'0')].join('-')}
function escapeProjectHtml(value){return String(value??'').replace(/[&<>'"]/g,character=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]))}
async function saveGanttChange(task, start, end, progress) {
    if (!projectCanEdit) return;
    const source = projectTimelineItems.find(item => item.id === task.id);
    if (!source) return;
    const payload = { progress: Math.round(progress ?? task.progress ?? 0) };
    payload.start_date = localDate(start);
    payload.due_date = source.is_milestone ? payload.start_date : localDate(end);
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
        custom_class: item.is_milestone ? 'milestone-row' + (Number(item.progress) >= 100 ? ' done-milestone' : '') : 'task-row',
    }));
    if(projectEndDate){
        const rangeStart=projectStartDate||projectTimelineItems.map(item=>item.start).sort()[0]||projectEndDate;
        const total=Math.max(1,new Date(projectEndDate+'T00:00:00')-new Date(rangeStart+'T00:00:00'));
        const elapsed=Math.max(0,Math.min(100,Math.round((new Date()-new Date(rangeStart+'T00:00:00'))/total*100)));
        tasks.unshift({id:'project-range',name:'Okres projektu',start:rangeStart,end:projectEndDate,progress:elapsed,dependencies:'',custom_class:'project-range-row'});
    }
    projectGantt = new Gantt('#project-frappe-gantt', tasks, {
        view_mode: projectGanttMode,
        language: 'en',
        popup_trigger: 'click',
        on_click: task => { if (projectCanEdit && task.id?.startsWith('task-')) openGanttTaskModal(task.id); },
        on_date_change: (task, start, end) => saveGanttChange(task, start, end, task.progress).catch(error => { alert(error.message); window.location.reload(); }),
        on_progress_change: (task, progress) => saveGanttChange(task, task._start || task.start, task._end || task.end, progress).catch(error => { alert(error.message); window.location.reload(); }),
        custom_popup_html: task => {
            const source = projectTimelineItems.find(item => item.id === task.id);
            const kind = source?.is_milestone ? 'Kamień milowy — etap projektu' : 'Zadanie harmonogramu';
            const person = source?.assignee ? '<div style="margin-top:4px">Osoba: ' + escapeProjectHtml(source.assignee) + '</div>' : '';
            const dependency = source?.dependencies ? projectTimelineItems.find(item => item.id === source.dependencies)?.name : null;
            const dates = source?.is_milestone ? localDate(task._start || task.start) : localDate(task._start || task.start) + ' – ' + localDate(task._end || task.end);
            return '<div class="details-container"><strong>' + escapeProjectHtml(task.name) + '</strong><div>' + kind + ' · ' + task.progress + '%</div><div>' + dates + '</div>' + person + (dependency ? '<div>Zależne od: ' + escapeProjectHtml(dependency) + '</div>' : '') + '</div>';
        },
        on_view_change: () => setTimeout(renderGanttDateMarkers, 0),
    });
    setTimeout(() => { bindGanttTaskEditing(); renderGanttDateMarkers(); }, 50);
}

function ganttMarkerX(date, kind) {
    const svg=document.querySelector('#project-frappe-gantt svg.gantt');
    if(!svg||!projectGantt)return null;
    if(kind==='today'){
        const highlight=svg.querySelector('.today-highlight');
        if(highlight)return Number(highlight.getAttribute('x'))+Number(highlight.getAttribute('width')||0)/2;
    }
    const start=new Date(projectGantt.gantt_start),target=new Date(date+'T00:00:00');
    const step=Number(projectGantt.options?.step||24),columnWidth=Number(projectGantt.options?.column_width||38);
    return ((target-start)/3600000/step)*columnWidth;
}
function renderGanttDateMarkers() {
    const svg=document.querySelector('#project-frappe-gantt svg.gantt');if(!svg||!projectGantt)return;
    svg.querySelectorAll('.project-date-marker').forEach(marker=>marker.remove());
    const background=svg.querySelector('.grid-background');
    const width=Number(background?.getAttribute('width')||svg.getAttribute('width')||0),height=Number(background?.getAttribute('height')||svg.getAttribute('height')||0);
    const markers=[{kind:'today',date:localDate(new Date()),label:'Dzisiaj',labelY:16}];
    if(projectEndDate)markers.push({kind:'deadline',date:projectEndDate,label:'Koniec projektu',labelY:29});
    markers.forEach(marker=>{
        const x=ganttMarkerX(marker.date,marker.kind);if(x===null||x<0||x>width)return;
        const group=document.createElementNS('http://www.w3.org/2000/svg','g');group.setAttribute('class','project-date-marker '+marker.kind);
        const line=document.createElementNS('http://www.w3.org/2000/svg','line');line.setAttribute('x1',x);line.setAttribute('x2',x);line.setAttribute('y1',0);line.setAttribute('y2',height);
        const label=document.createElementNS('http://www.w3.org/2000/svg','text');label.setAttribute('x',x+4);label.setAttribute('y',marker.labelY);label.textContent=marker.label;
        group.append(line,label);svg.appendChild(group);
    });
}

document.querySelectorAll('.gantt-mode').forEach(button => button.addEventListener('click', () => {
    initProjectGantt();
    if (!projectGantt) return;
    projectGanttMode = button.dataset.mode;
    projectGantt.change_view_mode(projectGanttMode);
    setTimeout(renderGanttDateMarkers, 0);
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
    projectTimelineItems.filter(item => item.id !== editingId).forEach(item => {
        const option = document.createElement('option'); option.value = item.id; option.textContent = item.name; select.appendChild(option);
    });
}
function toggleGanttMilestoneFields() {
    const milestone=document.getElementById('gantt-task-type')?.value==='milestone';
    document.getElementById('gantt-task-duration-field')?.classList.toggle('field-hidden',milestone);
    document.getElementById('gantt-task-end-field')?.classList.toggle('field-hidden',milestone);
    document.getElementById('gantt-milestone-note')?.classList.toggle('field-hidden',!milestone);
    const label=document.getElementById('gantt-task-start-label');if(label)label.textContent=milestone?'Termin kamienia milowego *':'Data rozpoczęcia *';
    if(milestone){document.getElementById('gantt-task-duration').value=1;document.getElementById('gantt-task-end').value=document.getElementById('gantt-task-start').value;}
}
function openGanttTaskModal(taskId = null, defaultType = 'task') {
    const modal = document.getElementById('gantt-task-modal');
    if (!modal) return;
    document.getElementById('gantt-task-form').reset();
    document.getElementById('gantt-task-id').value = taskId || '';
    populateDependencyOptions(taskId);
    const task = taskId ? projectTimelineItems.find(item => item.id === taskId) : null;
    const today = localDate(new Date());
    const milestone=task?.is_milestone||(!task&&defaultType==='milestone');
    document.getElementById('gantt-modal-title').textContent = task ? (milestone?'Edytuj kamień milowy':'Edytuj zadanie') : (milestone?'Dodaj kamień milowy':'Dodaj zadanie');
    document.getElementById('gantt-task-type').value = milestone ? 'milestone' : 'task';
    document.getElementById('gantt-task-title').value = task?.name || '';
    document.getElementById('gantt-task-start').value = task?.start || today;
    document.getElementById('gantt-task-end').value = task?.end || today;
    document.getElementById('gantt-task-duration').value = task ? taskDurationDays(task.start, task.end) : 1;
    document.getElementById('gantt-task-progress').value = task?.progress || 0;
    document.getElementById('gantt-task-dependency').value = task?.dependencies || '';
    document.getElementById('gantt-task-assignee').value = task?.assigned_to || '';
    document.getElementById('gantt-task-priority').value = task?.priority || 'medium';
    document.getElementById('gantt-task-description').value = task?.description || '';
    toggleGanttMilestoneFields();
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
    if (Number(task.progress) >= 100) return {status:'done',label:task.is_milestone?'✓ Etap wykonany':'✓ Wykonano',days,daysLabel:days >= 0 ? '+' + days : String(days)};
    if (days < 0) return {status:'overdue',label:task.is_milestone?'Etap po terminie':'Po terminie',days,daysLabel:String(days)};
    return {status:'active',label:task.is_milestone?'Etap w terminie':'W realizacji',days,daysLabel:'+' + days};
}
function renderGanttTaskList() {
    const container = document.getElementById('gantt-task-list');
    if (!container) return;
    if (!projectTimelineItems.length) {container.innerHTML='<div class="empty">Brak zadań w harmonogramie.</div>';return;}
    const rows = projectTimelineItems.map((task,index) => {
        const timing=ganttTaskTiming(task), dependency=task.dependencies ? projectTimelineItems.find(item=>item.id===task.dependencies)?.name || '—' : '—';
        const actions=projectCanEdit ? '<div class="mini-actions"><button class="mini-btn edit list-edit" data-index="'+index+'" title="Edytuj">✎</button><button class="mini-btn delete list-delete" data-index="'+index+'" title="Usuń">×</button><button class="mini-btn move list-up" data-index="'+index+'" title="Przesuń wyżej" '+(index===0?'disabled':'')+'>↑</button><button class="mini-btn move list-down" data-index="'+index+'" title="Przesuń niżej" '+(index===projectTimelineItems.length-1?'disabled':'')+'>↓</button></div>' : '';
        const slider=projectCanEdit ? '<div class="progress-wrap"><input class="list-progress" data-index="'+index+'" type="range" min="0" max="100" value="'+task.progress+'"><strong>'+task.progress+'%</strong></div>' : task.progress+'%';
        const checkbox=projectCanEdit ? '<td class="gantt-select-cell"><input class="gantt-task-check" type="checkbox" value="'+task.db_id+'" aria-label="Zaznacz zadanie '+escapeProjectHtml(task.name)+'"></td>' : '';
        const milestoneBadge=task.is_milestone?'<span class="milestone-badge">◆ Kamień milowy</span>':'';
        return '<tr class="'+(timing.status==='done'?'done-row':timing.status==='overdue'?'overdue-row':'')+'">'+checkbox+'<td><strong>'+escapeProjectHtml(task.name)+'</strong>'+milestoneBadge+'<br><small>Zależne od: '+escapeProjectHtml(dependency)+'</small></td><td>'+escapeProjectHtml(task.assignee||'—')+'</td><td title="'+escapeProjectHtml(task.description||'')+'">'+escapeProjectHtml(task.description ? (task.description.length>55?task.description.slice(0,55)+'…':task.description) : '—')+'</td><td>'+localDate(task.end)+'</td><td>'+slider+'</td><td><span class="task-status '+timing.status+'">'+timing.label+'</span></td><td><span class="days-value '+(timing.days<0?'late':'ok')+'">'+timing.daysLabel+'</span></td><td>'+actions+'</td></tr>';
    }).join('');
    const bulkToolbar=projectCanEdit ? '<div class="gantt-bulk-toolbar"><span class="gantt-bulk-count">Nie zaznaczono zadań</span><button type="button" class="gantt-bulk-delete" disabled><i class="ti ti-trash"></i> Usuń zaznaczone</button></div>' : '';
    const selectAllHeader=projectCanEdit ? '<th class="gantt-select-cell"><input class="gantt-check-all" type="checkbox" aria-label="Zaznacz wszystkie zadania" title="Zaznacz wszystkie"></th>' : '';
    container.innerHTML=bulkToolbar+'<div style="overflow-x:auto"><table class="gantt-list-table"><thead><tr>'+selectAllHeader+'<th>Zadanie</th><th>Osoba</th><th>Opis</th><th>Termin</th><th>Wykonanie</th><th>Status</th><th>Dni</th><th style="text-align:right">Akcje</th></tr></thead><tbody>'+rows+'</tbody></table></div>';
    container.querySelector('.gantt-check-all')?.addEventListener('change',event=>{container.querySelectorAll('.gantt-task-check').forEach(checkbox=>checkbox.checked=event.target.checked);updateGanttBulkControls();});
    container.querySelectorAll('.gantt-task-check').forEach(checkbox=>checkbox.addEventListener('change',updateGanttBulkControls));
    container.querySelector('.gantt-bulk-delete')?.addEventListener('click',deleteSelectedGanttTasks);
    container.querySelectorAll('.list-edit').forEach(button=>button.addEventListener('click',()=>openGanttTaskModal(projectTimelineItems[Number(button.dataset.index)].id)));
    container.querySelectorAll('.list-delete').forEach(button=>button.addEventListener('click',()=>deleteGanttTask(Number(button.dataset.index))));
    container.querySelectorAll('.list-up').forEach(button=>button.addEventListener('click',()=>moveGanttTask(Number(button.dataset.index),-1)));
    container.querySelectorAll('.list-down').forEach(button=>button.addEventListener('click',()=>moveGanttTask(Number(button.dataset.index),1)));
    container.querySelectorAll('.list-progress').forEach(slider=>slider.addEventListener('change',()=>{const task=projectTimelineItems[Number(slider.dataset.index)];saveGanttChange(task,task.start,task.end,Number(slider.value)).then(()=>renderGanttTaskList()).catch(error=>alert(error.message));}));
}
function selectedGanttTaskIds() {
    return Array.from(document.querySelectorAll('#gantt-task-list .gantt-task-check:checked')).map(checkbox=>Number(checkbox.value));
}
function updateGanttBulkControls() {
    const container=document.getElementById('gantt-task-list');if(!container)return;
    const selected=selectedGanttTaskIds(),all=container.querySelectorAll('.gantt-task-check'),selectAll=container.querySelector('.gantt-check-all');
    const button=container.querySelector('.gantt-bulk-delete'),count=container.querySelector('.gantt-bulk-count');
    if(button)button.disabled=!selected.length;
    if(count)count.textContent=selected.length ? 'Zaznaczono: '+selected.length : 'Nie zaznaczono zadań';
    if(selectAll){selectAll.checked=all.length>0&&selected.length===all.length;selectAll.indeterminate=selected.length>0&&selected.length<all.length;}
}
async function deleteSelectedGanttTasks() {
    const selected=selectedGanttTaskIds();if(!selected.length)return;
    if(!window.confirm('Usunąć zaznaczone zadania ('+selected.length+')? Tej operacji nie można cofnąć.'))return;
    const button=document.querySelector('#gantt-task-list .gantt-bulk-delete');if(button)button.disabled=true;
    const response=await fetch(ganttBulkDeleteUrl,{method:'DELETE',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':projectCsrfToken},body:JSON.stringify({task_ids:selected})});
    const data=await response.json().catch(()=>({}));
    if(!response.ok){alert(data.message||Object.values(data.errors||{}).flat()[0]||'Nie udało się usunąć zaznaczonych zadań.');updateGanttBulkControls();return;}
    const deletedIds=new Set(selected.map(id=>'task-'+id));
    for(let index=projectTimelineItems.length-1;index>=0;index--){if(deletedIds.has(projectTimelineItems[index].id))projectTimelineItems.splice(index,1);}
    projectTimelineItems.forEach(item=>{if(deletedIds.has(item.dependencies))item.dependencies='';});
    renderProjectGantt();
}
async function persistGanttOrder() {
    const response=await fetch(@json(route('audits.tasks.reorder',$audit)),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':projectCsrfToken},body:JSON.stringify({order:projectTimelineItems.map(item=>item.db_id)})});
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
document.getElementById('gantt-add-milestone')?.addEventListener('click', () => openGanttTaskModal(null,'milestone'));
document.getElementById('gantt-task-type')?.addEventListener('change',toggleGanttMilestoneFields);
document.getElementById('gantt-task-start')?.addEventListener('change', event => {
    if(document.getElementById('gantt-task-type').value==='milestone'){document.getElementById('gantt-task-end').value=event.target.value;return;}
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
    const isMilestone = document.getElementById('gantt-task-type').value === 'milestone';
    const startDate = document.getElementById('gantt-task-start').value;
    const payload = {
        title: document.getElementById('gantt-task-title').value,
        start_date: startDate,
        due_date: isMilestone ? startDate : document.getElementById('gantt-task-end').value,
        is_milestone: isMilestone,
        progress,
        status: progress >= 100 ? 'done' : (progress > 0 ? 'in_progress' : 'todo'),
        priority: document.getElementById('gantt-task-priority').value,
        assigned_to: document.getElementById('gantt-task-assignee').value || null,
        depends_on_task_id: dependency ? Number(dependency.replace('task-', '')) : null,
        description: document.getElementById('gantt-task-description').value || null,
    };
    const saveButton = document.getElementById('gantt-task-save'); saveButton.disabled = true;
    try {
        const response = await fetch(existing?.update_url || @json(route('audits.tasks.store', $audit)), {method: existing ? 'PATCH' : 'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':projectCsrfToken}, body:JSON.stringify(payload)});
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

</script>
@endpush
@push('scripts')<script>
const auditTaskStore=@json(route('audits.tasks.store',$audit)),auditTaskUpdate=@json(route('audits.tasks.update',[$audit,0])),auditFinanceStore=@json(route('audits.finances.store',$audit)),auditFinanceUpdate=@json(route('audits.finances.update',[$audit,0]));
function showAuditTab(id,button){document.querySelectorAll('.aw-pane').forEach(p=>p.classList.toggle('active',p.id==='aw-'+id));document.querySelectorAll('.aw-tab').forEach(t=>t.classList.remove('active'));button.classList.add('active');history.replaceState(null,'','?tab='+id)}
function openAwModal(id){document.getElementById(id)?.classList.add('open')}function closeAwModal(id){document.getElementById(id)?.classList.remove('open')}
function openAuditTask(task=null){const form=document.getElementById('audit-task-form');form.action=task?auditTaskUpdate.replace(/\/0$/, '/'+task.id):auditTaskStore;document.getElementById('audit-task-method').value=task?'PUT':'POST';document.getElementById('task-modal-title').textContent=task?'Edytuj zadanie':'Nowe zadanie audytowe';['title','start','end','assignee','priority','status','progress','description'].forEach(k=>{const el=document.getElementById('audit-task-'+k);if(!el)return;const map={start:'start_date',end:'due_date',assignee:'assigned_to'};let value=task?.[map[k]||k]??({priority:'medium',status:'todo',progress:0}[k]??'');el.value=['start','end'].includes(k)&&value?String(value).slice(0,10):value});openAwModal('task-modal')}
function openAuditFinance(entry=null){const form=document.getElementById('audit-finance-form');form.action=entry?auditFinanceUpdate.replace(/\/0$/, '/'+entry.id):auditFinanceStore;document.getElementById('audit-finance-method').value=entry?'PUT':'POST';document.getElementById('finance-modal-title').textContent=entry?'Edytuj pozycję finansową':'Nowa pozycja finansowa';['type','name','number','date','amount','status','notes'].forEach(k=>{const el=document.getElementById('audit-finance-'+k);if(!el)return;const map={number:'document_number',date:'entry_date'};let value=entry?.[map[k]||k]??({type:'invoice',status:'planned'}[k]??'');el.value=k==='date'&&value?String(value).slice(0,10):value});openAwModal('finance-modal')}
document.addEventListener('DOMContentLoaded',()=>{const tab=new URLSearchParams(location.search).get('tab');if(tab){const button=document.querySelector('.aw-tab[data-audit-tab="'+tab+'"]');if(button)showAuditTab(tab,button)}document.getElementById('audit-finance-search')?.addEventListener('input',e=>{const q=e.target.value.toLocaleLowerCase('pl');document.querySelectorAll('#audit-finance-rows tr[data-search]').forEach(row=>row.hidden=!row.dataset.search.toLocaleLowerCase('pl').includes(q))})});
document.querySelectorAll('.aw-modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open')}));
</script>@endpush
