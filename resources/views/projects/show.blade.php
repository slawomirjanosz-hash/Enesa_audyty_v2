@extends('layouts.app')

@section('page-title', $project->number.' — '.$project->name)

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.0/dist/frappe-gantt.css">
@php
    $canEdit = auth()->user()->can('update', $project);
    $projectTeam = collect([$project->manager])->filter()->merge($project->members)->unique('id')->values();
    $statusLabels = ['planned'=>'Planowany','active'=>'Aktywny','on_hold'=>'Wstrzymany','completed'=>'Zakończony','cancelled'=>'Anulowany'];
    $requirementStatusLabels = ['planned'=>'Planowane','requested'=>'Zapotrzebowanie','ordered'=>'Zamówione','in_progress'=>'W realizacji','purchased'=>'Kupione','cancelled'=>'Anulowane'];
    $financeStatusLabels = ['planned'=>'Planowana','issued'=>'Wystawiona / zaksięgowana','paid'=>'Opłacona'];
    $financialEntries = $canViewFinances ? $project->effectiveFinancialEntries() : collect();
    $financeChartData = $canViewFinances ? $financialEntries->sortBy('entry_date')->map(fn($entry) => [
        'id' => $entry->id,
        'date' => $entry->entry_date->format('Y-m-d'),
        'amount' => (float) $entry->amount,
        'type' => $entry->type,
        'status' => $entry->status,
        'name' => $entry->name,
    ])->concat($project->requirements
        ->where('status', 'planned')
        ->filter(fn($requirement) => $requirement->estimated_cost !== null)
        ->map(fn($requirement) => [
            'id' => 'requirement-'.$requirement->id,
            'date' => $requirement->needed_by?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'amount' => (float) $requirement->estimated_cost,
            'type' => 'cost',
            'status' => 'planned',
            'name' => $requirement->name,
        ]))
        ->sortBy('date')
        ->values() : collect();
    $committedRequirements = $project->requirements
        ->whereIn('status', ['ordered', 'in_progress', 'purchased'])
        ->sum(fn($requirement) => (float) $requirement->estimated_cost);
    $activeRequirements = $project->requirements->where('status', '!=', 'cancelled');
    $requirementsWithVisiblePrices = $activeRequirements->filter(fn($requirement) => $requirement->type === 'service' ? $canViewServicePrices : $canViewMaterialPrices);
    $requirementsTotal = $requirementsWithVisiblePrices->sum(fn($requirement) => (float) ($requirement->estimated_cost ?? 0));
    $plannedRequirements = $requirementsWithVisiblePrices->where('status', 'planned')->sum(fn($requirement) => (float) ($requirement->estimated_cost ?? 0));
    $requirementsBySupplier = $requirementsWithVisiblePrices
        ->groupBy(fn($requirement) => $requirement->supplierCompany?->name ?? ($requirement->supplier ?: 'Bez dostawcy'))
        ->map(fn($items, $supplier) => [
            'supplier' => $supplier,
            'count' => $items->count(),
            'total' => $items->sum(fn($item) => (float) ($item->estimated_cost ?? 0)),
        ])
        ->sortByDesc('total')
        ->values();
    $requirementExportSuppliers = $project->requirements
        ->map(function($requirement) {
            if ($requirement->supplierCompany) {
                return ['value' => 'company:'.$requirement->supplierCompany->id, 'label' => $requirement->supplierCompany->name];
            }
            $supplier = trim((string) $requirement->supplier);
            return $supplier !== '' ? ['value' => 'external:'.$supplier, 'label' => $supplier.' (spoza CRM)'] : null;
        })
        ->filter()
        ->unique('value')
        ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
        ->values();
    $requirementItems = $canViewRequirements ? $project->requirements->map(fn($requirement) => [
        'id' => $requirement->id,
        'type' => $requirement->type,
        'name' => $requirement->name,
        'technology' => $requirement->technology,
        'description' => $requirement->description,
        'quantity' => (float) $requirement->quantity,
        'unit' => $requirement->displayUnit(),
        'unit_cost' => ($requirement->type === 'service' ? $canViewServicePrices : $canViewMaterialPrices) ? $requirement->unitCost() : null,
        'estimated_cost' => ($requirement->type === 'service' ? $canViewServicePrices : $canViewMaterialPrices) && $requirement->estimated_cost !== null ? (float) $requirement->estimated_cost : null,
        'needed_by' => $requirement->needed_by?->format('Y-m-d'),
        'responsible_id' => $requirement->responsible_id,
        'supplier_company_id' => $requirement->supplier_company_id,
        'supplier' => $requirement->supplier,
        'status' => $requirement->status,
        'update_url' => route('projects.requirements.update', [$project, $requirement]),
    ])->values() : collect();
@endphp
<style>
    .p-head{display:flex;justify-content:space-between;gap:20px;margin-bottom:18px}.p-kicker{font-size:12px;font-weight:800;color:var(--green);letter-spacing:.08em}.p-head h1{margin:4px 0 7px;font-size:25px}.p-meta{display:flex;flex-wrap:wrap;gap:9px 18px;color:#66736b;font-size:13px}.badge{padding:5px 10px;border-radius:999px;background:#edf5ef;color:#24543d;font-size:11px;font-weight:800}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}.sum{background:#fff;border:1px solid #e5e1d8;border-radius:11px;padding:16px}.sum small{display:block;color:#77827b;margin-bottom:6px}.sum strong{font-size:20px}.tabs{display:flex;gap:4px;border-bottom:2px solid #e5e1d8;overflow:auto}.tab{border:0;background:none;padding:12px 16px;font-weight:700;color:#66736b;cursor:pointer;white-space:nowrap;border-bottom:2px solid transparent;margin-bottom:-2px}.tab.active{color:var(--green);border-color:var(--green)}.pane{display:none;padding-top:20px}.pane.active{display:block}.card{background:#fff;border:1px solid #e5e1d8;border-radius:11px;padding:20px;margin-bottom:16px}.card h2{font-size:16px;margin:0 0 15px}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:flex;flex-direction:column;gap:5px}.field label{font-size:11px;font-weight:800;color:#4b5650}.field input,.field select,.field textarea{border:1px solid #d8d3c8;border-radius:7px;padding:9px 10px;font:inherit}.full{grid-column:1/-1}.member-checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px 12px;padding:10px;border:1px solid #d8d3c8;border-radius:8px;background:#fafaf7}.member-check{display:flex!important;flex-direction:row!important;align-items:center;gap:8px;font-size:12px!important;font-weight:600!important}.member-check input{width:16px;height:16px}.btn{border:0;border-radius:7px;padding:9px 13px;background:var(--green);color:#fff;font-weight:800;cursor:pointer;text-decoration:none}.btn-red{background:#b91c1c}.btn-soft{background:#edf4ef;color:var(--green)}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #eee;font-size:12px}th{font-size:10px;text-transform:uppercase;color:#7b847f;background:#fafaf6}.gantt{overflow-x:auto}.g-row{display:grid;grid-template-columns:220px minmax(700px,1fr);min-height:46px;border-bottom:1px solid #eee;align-items:center}.g-name{padding:8px;font-size:12px}.g-track{height:28px;background:repeating-linear-gradient(90deg,#f7f7f3 0,#f7f7f3 calc(10% - 1px),#e7e4dc calc(10% - 1px),#e7e4dc 10%);position:relative}.g-bar{position:absolute;top:4px;height:20px;border-radius:5px;min-width:4px;color:#fff;font-size:10px;display:flex;align-items:center;padding:0 6px;overflow:hidden}.g-progress{position:absolute;inset:0 auto 0 0;background:rgba(0,0,0,.18)}.legend{display:flex;gap:16px;font-size:11px;color:#66736b;margin:8px 0}.dot{width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:4px}.finance-chart{width:100%;height:auto;background:#fbfbf8;border-radius:8px}.status-select{padding:6px;border:1px solid #ddd;border-radius:6px}.project-async-status:disabled{opacity:.55;cursor:wait}.empty{padding:28px;text-align:center;color:#888}.team{display:flex;flex-wrap:wrap;gap:8px}.person{padding:7px 10px;border-radius:999px;background:#f0f5f1;font-size:12px}@media(max-width:850px){.summary{grid-template-columns:1fr 1fr}.grid2{grid-template-columns:1fr}.full{grid-column:auto}.member-checks{grid-template-columns:1fr 1fr}.g-row{grid-template-columns:150px minmax(650px,1fr)}}
    .gantt-toolbar,.chart-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin-bottom:13px}.tool-label{font-size:11px;font-weight:800;color:#66736b;margin-right:3px}.tool-btn{border:1px solid #d8d3c8;background:#fff;color:#46524b;border-radius:7px;padding:7px 10px;font:inherit;font-size:11px;font-weight:800;cursor:pointer}.tool-btn:hover,.tool-btn.active{background:var(--green);border-color:var(--green);color:#fff}.tool-btn.primary{background:var(--green);border-color:var(--green);color:#fff}.gantt-help{padding:9px 11px;background:#f6f8f5;border:1px solid #e1e6df;border-radius:7px;color:#66736b;font-size:11px;margin-bottom:12px}.frappe-gantt-wrap{min-height:260px;overflow-x:auto;border:1px solid #e3e0d8;border-radius:9px;background:#fff}.frappe-gantt-wrap .gantt-container{overflow:visible}.frappe-gantt-wrap svg{min-width:100%}.frappe-gantt-wrap.gantt-readonly svg{pointer-events:none}.frappe-gantt-wrap .bar-wrapper.stage-row .bar{fill:#2563eb}.frappe-gantt-wrap .bar-wrapper.stage-row .bar-progress{fill:#1d4ed8}.frappe-gantt-wrap .bar-wrapper.task-row .bar{fill:#8b5cf6}.frappe-gantt-wrap .bar-wrapper.task-row .bar-progress{fill:#6d28d9}.frappe-gantt-wrap .bar-label{font-size:11px;font-weight:700}.gantt-fallback{padding:45px;text-align:center;color:#777}.project-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center;padding:16px}.project-modal.open{display:flex}.project-modal-box{width:min(760px,100%);max-height:calc(100vh - 32px);overflow:auto;background:#fff;border-radius:13px;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,.22)}.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}.modal-head h2{margin:0}.modal-close{border:0;background:none;font-size:25px;cursor:pointer}.finance-summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin-bottom:16px}.finance-kpi{border-radius:9px;padding:12px 13px;background:#f7f8f5;border:1px solid #e4e5df}.finance-kpi small{display:block;color:#6d786f;font-size:10px;font-weight:800;margin-bottom:5px}.finance-kpi strong{font-size:15px}.chart-shell{height:330px;position:relative}.chart-range{font-size:11px;color:#66736b;font-weight:700;padding:7px 10px;background:#f7f8f5;border-radius:7px}.finance-register-tabs{display:flex;gap:5px;margin-bottom:12px}.register-tab{border:1px solid #d8d3c8;background:#f7f8f5;border-radius:7px;padding:7px 11px;font-size:11px;font-weight:800;cursor:pointer}.register-tab.active{background:#243f31;color:#fff;border-color:#243f31}.finance-row-hidden{display:none}@media(max-width:1050px){.finance-summary-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:700px){.finance-summary-grid{grid-template-columns:1fr 1fr}.chart-shell{height:280px}}
    .gantt-list-table{min-width:1020px}.gantt-list-table tr.done-row{background:#f2fbf5}.gantt-list-table tr.overdue-row{background:#fff1f1}.gantt-select-cell{width:38px;text-align:center}.gantt-task-check,.gantt-check-all{width:16px;height:16px;accent-color:var(--green);cursor:pointer}.gantt-bulk-toolbar{display:flex;justify-content:flex-end;align-items:center;gap:9px;margin-bottom:10px}.gantt-bulk-count{font-size:11px;color:#66736b;font-weight:700}.gantt-bulk-delete{border:0;border-radius:7px;padding:7px 10px;background:#b91c1c;color:#fff;font-size:11px;font-weight:800;cursor:pointer}.gantt-bulk-delete:disabled{opacity:.4;cursor:not-allowed}.progress-wrap{display:flex;align-items:center;gap:7px;min-width:190px}.progress-wrap input[type=range]{width:150px;height:6px;accent-color:#2563eb;cursor:pointer}.progress-wrap strong{min-width:36px;font-size:11px}.task-status{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap}.task-status.done{background:#d1fae5;color:#047857}.task-status.overdue{background:#fee2e2;color:#b91c1c}.task-status.active{background:#dbeafe;color:#1d4ed8}.days-value{font-size:11px;font-weight:800}.days-value.late{color:#dc2626}.days-value.ok{color:#15803d}.mini-actions{display:flex;gap:4px;justify-content:flex-end}.mini-btn{width:27px;height:25px;border:0;border-radius:5px;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;cursor:pointer;color:#fff}.mini-btn.edit{background:#2563eb}.mini-btn.delete{background:#dc2626}.mini-btn.move{background:#93c5fd}.mini-btn:disabled{opacity:.35;cursor:not-allowed}
    .frappe-gantt-wrap .today-highlight{fill:#ef4444!important;opacity:.16!important}.frappe-gantt-wrap .bar-wrapper.project-range-row{pointer-events:none}.frappe-gantt-wrap .bar-wrapper.project-range-row .bar{fill:#cbd5e1}.frappe-gantt-wrap .bar-wrapper.project-range-row .bar-progress{fill:#64748b}.frappe-gantt-wrap .bar-wrapper.project-range-row .handle{display:none}.frappe-gantt-wrap .bar-wrapper.milestone-row .bar{fill:#f59e0b;stroke:#b45309;stroke-width:1;transform:rotate(45deg) scale(.72);transform-box:fill-box;transform-origin:center;rx:0;ry:0}.frappe-gantt-wrap .bar-wrapper.milestone-row .bar-progress,.frappe-gantt-wrap .bar-wrapper.milestone-row .handle{display:none}.frappe-gantt-wrap .bar-wrapper.milestone-row.done-milestone .bar{fill:#16a34a;stroke:#15803d}.project-date-marker{pointer-events:none}.project-date-marker line{stroke-width:2}.project-date-marker.today line{stroke:#ef4444;stroke-dasharray:5 4}.project-date-marker.deadline line{stroke:#111827;stroke-dasharray:8 4}.project-date-marker text{font-size:10px;font-weight:800;paint-order:stroke;stroke:#fff;stroke-width:3px;stroke-linejoin:round}.project-date-marker.today text{fill:#dc2626}.project-date-marker.deadline text{fill:#111827}.milestone-badge{display:inline-flex;align-items:center;gap:4px;margin-left:6px;padding:3px 7px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:9px;font-weight:800}.milestone-note{padding:9px 11px;border-radius:7px;background:#fffbeb;color:#92400e;font-size:11px}.field-hidden{display:none!important}
    .finance-section{background:#fff;border:1px solid #e5e1d8;border-radius:11px;margin-bottom:14px;overflow:hidden}.finance-section>summary{display:flex;align-items:center;gap:10px;padding:15px 18px;cursor:pointer;font-size:15px;font-weight:800;list-style:none}.finance-section>summary::-webkit-details-marker{display:none}.finance-section>summary:before{content:'›';font-size:22px;line-height:1;transition:transform .18s}.finance-section[open]>summary:before{transform:rotate(90deg)}.finance-section>summary small{margin-left:auto;color:#77827b;font-size:10px;font-weight:700}.finance-section-body{border-top:1px solid #eee;padding:18px}.finance-import-note{font-size:11px;color:#66736b;line-height:1.5;background:#f7f8f5;border-radius:7px;padding:10px;margin-bottom:13px}.import-report{border:1px solid #bae6c6;background:#f0fdf4;border-radius:9px;padding:14px;margin-bottom:14px}.report-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.report-value{background:#fff;border-radius:7px;padding:9px}.report-value small{display:block;color:#66736b;font-size:9px;text-transform:uppercase}.report-value strong{font-size:15px}.finance-groups{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}.finance-group-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;background:#f0f5f1;border-radius:999px;font-size:11px}.finance-group-chip form{display:inline}.finance-group-chip button{border:0;background:none;color:#b91c1c;cursor:pointer;padding:0}.finance-table{min-width:1050px}.finance-table .finance-name-column{width:17%;max-width:220px}.finance-table .finance-name-column strong,.finance-table .finance-name-column small{overflow-wrap:anywhere}.finance-table .finance-amount-column{width:155px;min-width:155px;white-space:nowrap}.finance-invoice-note{padding:10px 12px;border-radius:8px;background:#edf5ef;color:#285740;font-size:11px;font-weight:700}.source-badge{display:inline-block;border-radius:999px;background:#eef2ff;color:#4338ca;padding:3px 6px;font-size:9px;font-weight:800}.finance-edit{position:relative}.finance-edit>summary{list-style:none}.finance-edit-box{position:absolute;right:0;z-index:20;width:min(650px,80vw);background:#fff;border:1px solid #d8d3c8;border-radius:9px;padding:14px;box-shadow:0 12px 35px rgba(0,0,0,.16)}.chart-overview-shell{height:90px;margin-top:8px;position:relative;border-top:1px solid #eee;padding-top:7px}@media(max-width:700px){.report-grid{grid-template-columns:1fr 1fr}.finance-section-body{padding:12px}}
    .requirements-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.requirements-head h2{margin:0}.requirements-head p{margin:4px 0 0;color:#6b776f;font-size:12px}.requirements-head-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.requirement-import{border:1px solid #dfe5df;border-radius:9px;background:#f8faf7;margin-bottom:14px}.requirement-import>summary{list-style:none;cursor:pointer;padding:11px 13px;font-size:12px;font-weight:800;color:var(--green)}.requirement-import>summary::-webkit-details-marker{display:none}.requirement-import>summary:before{content:'›';display:inline-block;margin-right:7px;font-size:18px;vertical-align:-1px;transition:transform .15s}.requirement-import[open]>summary:before{transform:rotate(90deg)}.requirement-import-body{border-top:1px solid #e4e8e3;padding:13px}.requirement-import-form{display:flex;align-items:center;gap:9px;flex-wrap:wrap}.requirement-import-form input{flex:1;min-width:230px;border:1px solid #d8d3c8;border-radius:7px;background:#fff;padding:8px}.requirement-import-help{font-size:11px;line-height:1.55;color:#657169;margin:0 0 11px}.requirement-search{display:flex;align-items:center;gap:9px;margin:0 0 12px;padding:10px 12px;border:1px solid #dfe5df;border-radius:9px;background:#fff}.requirement-search i{color:#66736b;font-size:18px}.requirement-search input{flex:1;min-width:180px;border:0;outline:0;background:transparent;font:inherit}.requirement-search-count{font-size:11px;font-weight:700;color:#66736b;white-space:nowrap}.requirement-search-empty{padding:22px;text-align:center;color:#66736b;background:#f8faf7;border-radius:8px;margin-top:10px}.requirement-search-empty[hidden]{display:none}.requirement-bulk-toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px;margin:0 0 12px;border:1px solid #dfe5df;border-radius:9px;background:#f8faf7}.requirement-bulk-toolbar select,.requirement-bulk-toolbar input{min-width:180px;padding:7px 9px;border:1px solid #d8d3c8;border-radius:7px;background:#fff}.requirement-bulk-value[hidden]{display:none}.requirement-selected-count{font-size:11px;font-weight:700;color:#66736b}.requirements-table-wrap{overflow-x:auto}.requirements-table{min-width:850px;table-layout:fixed}.requirements-table th,.requirements-table td{padding:9px 8px;vertical-align:middle}.requirements-table:not(.with-selection) th:nth-child(1){width:29%}.requirements-table:not(.with-selection) th:nth-child(2){width:9%}.requirements-table:not(.with-selection) th:nth-child(3){width:16%}.requirements-table:not(.with-selection) th:nth-child(4){width:15%}.requirements-table:not(.with-selection) th:nth-child(5){width:11%}.requirements-table:not(.with-selection) th:nth-child(6){width:11%}.requirements-table:not(.with-selection) th:nth-child(7){width:9%}.requirements-table.with-selection th:nth-child(1){width:4%}.requirements-table.with-selection th:nth-child(2){width:27%}.requirements-table.with-selection th:nth-child(3){width:9%}.requirements-table.with-selection th:nth-child(4){width:15%}.requirements-table.with-selection th:nth-child(5){width:14%}.requirements-table.with-selection th:nth-child(6){width:11%}.requirements-table.with-selection th:nth-child(7){width:12%}.requirements-table.with-selection th:nth-child(8){width:8%}.requirement-name{display:block;line-height:1.3}.requirement-meta{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:4px;color:#758078}.requirement-type{padding:3px 6px;border-radius:999px;background:#eef4ef;color:#285740;font-size:9px;font-weight:800}.requirement-description{display:block;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.requirement-qty{font-size:13px;font-weight:800;white-space:nowrap}.requirement-cost{font-weight:800;white-space:nowrap}.requirement-status{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:9px;font-weight:800;white-space:nowrap}.requirement-status.planned{background:#ede9fe;color:#6d28d9}.requirement-status.requested{background:#f1f5f9;color:#475569}.requirement-status.ordered{background:#fef3c7;color:#92400e}.requirement-status.in_progress{background:#dbeafe;color:#1d4ed8}.requirement-status.purchased{background:#d1fae5;color:#047857}.requirement-status.cancelled{background:#fee2e2;color:#b91c1c}.requirement-actions{display:flex;justify-content:flex-end;gap:5px}.requirement-modal-box{width:min(760px,100%)}@media(max-width:700px){.requirements-head{align-items:flex-start;flex-direction:column}.requirements-table{min-width:820px}.requirement-import-form{align-items:stretch;flex-direction:column}.requirement-import-form input{width:100%;min-width:0}.requirement-bulk-toolbar{align-items:stretch;flex-direction:column}.requirement-bulk-toolbar select,.requirement-bulk-toolbar input,.requirement-bulk-toolbar button{width:100%}}
    .requirements-table.with-selection th:nth-child(1){width:36px!important}.requirements-table.with-selection th:nth-child(2){width:27%!important}.requirements-table.with-selection th:nth-child(3){width:13%!important}.requirements-table.with-selection th:nth-child(4){width:68px!important}.requirements-table.with-selection th:nth-child(5){width:14%!important}.requirements-table.with-selection th:nth-child(6){width:14%!important}.requirements-table.with-selection th:nth-child(7){width:116px!important}.requirements-table.with-selection th:nth-child(8){width:155px!important}.requirements-table.with-selection th:nth-child(9){width:66px!important}.requirements-table:not(.with-selection) th:nth-child(1){width:29%!important}.requirements-table:not(.with-selection) th:nth-child(2){width:14%!important}.requirements-table:not(.with-selection) th:nth-child(3){width:68px!important}.requirements-table:not(.with-selection) th:nth-child(4){width:15%!important}.requirements-table:not(.with-selection) th:nth-child(5){width:15%!important}.requirements-table:not(.with-selection) th:nth-child(6){width:116px!important}.requirements-table:not(.with-selection) th:nth-child(7){width:155px!important}.requirements-table:not(.with-selection) th:nth-child(8){width:24px!important}.requirements-table td:last-child{min-width:66px;padding-left:4px;padding-right:4px}.requirements-table .requirement-actions{min-width:58px;justify-content:flex-end}.requirements-table .requirement-status{width:112px;max-width:112px}.requirements-table .requirement-qty{white-space:nowrap}
    .requirement-export-statuses{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px 12px;padding:10px;border:1px solid #d8d3c8;border-radius:8px;background:#fafaf7}.requirement-export-status{display:flex!important;flex-direction:row!important;align-items:center;gap:8px;font-size:12px!important;font-weight:600!important}.requirement-export-status input{width:16px;height:16px;accent-color:var(--green)}.requirement-export-note{padding:10px 12px;border-radius:8px;background:#f3f7f4;color:#536158;font-size:11px;line-height:1.5}@media(max-width:700px){.requirement-export-statuses{grid-template-columns:1fr}}
    .requirement-summary-kpi{cursor:pointer;transition:border-color .15s,box-shadow .15s,transform .15s}.requirement-summary-kpi:hover,.requirement-summary-kpi:focus{outline:0;border-color:var(--green);box-shadow:0 4px 14px rgba(26,77,58,.14);transform:translateY(-1px)}.requirement-summary-kpi.active{background:#edf5ef;border-color:var(--green);box-shadow:0 0 0 2px rgba(26,77,58,.12)}.requirement-filter-state{display:flex;align-items:center;gap:8px;margin:-3px 0 10px;padding:8px 10px;border-radius:8px;background:#edf5ef;color:#285740;font-size:11px;font-weight:700}.requirement-filter-state button{margin-left:auto;border:0;background:none;color:#285740;font:inherit;text-decoration:underline;cursor:pointer}
</style>

<div class="p-head"><div><div class="p-kicker">{{ $project->number }}</div><h1>{{ $project->name }}</h1><div class="p-meta"><span><i class="ti ti-building"></i> {{ $project->company?->name ?? 'Projekt wewnętrzny' }}</span><span><i class="ti ti-user-star"></i> {{ $project->manager?->name }}</span><span><i class="ti ti-calendar"></i> {{ $project->start_date?->format('d.m.Y') ?? '—' }} – {{ $project->end_date?->format('d.m.Y') ?? '—' }}</span></div></div><div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end">@if($canEdit)<button class="btn btn-soft" type="button" onclick="document.getElementById('project-edit-modal').classList.add('open')"><i class="ti ti-edit"></i> Edytuj projekt</button>@endif<span class="badge">{{ $statusLabels[$project->status] ?? $project->status }}</span></div></div>

@if(session('success'))<div style="padding:12px 15px;background:#ecfdf5;color:#166534;border-radius:8px;margin-bottom:15px;">{{ session('success') }}</div>@endif
@if($errors->any())<div style="padding:12px 15px;background:#fef2f2;color:#991b1b;border-radius:8px;margin-bottom:15px;">{{ $errors->first() }}</div>@endif

<div class="summary">
    @if($canViewFinances)
        <div class="sum"><small>Wartość kontraktu</small><strong>{{ number_format((float)$project->contract_value,2,',',' ') }} zł</strong></div>
        <div class="sum"><small>Wystawione faktury</small><strong>{{ number_format($project->totalInvoiced(),2,',',' ') }} zł</strong></div>
        <div class="sum"><small>Koszty</small><strong>{{ number_format($project->totalCosts(),2,',',' ') }} zł</strong></div>
        <div class="sum"><small>Wynik</small><strong style="color:{{ $project->result()>=0?'#15803d':'#b91c1c' }}">{{ number_format($project->result(),2,',',' ') }} zł</strong></div>
    @else
        <div class="sum"><small>Status projektu</small><strong>{{ $statusLabels[$project->status] ?? $project->status }}</strong></div>
        <div class="sum"><small>Zespół projektu</small><strong>{{ $projectTeam->count() }}</strong></div>
    @endif
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
                @if($canViewFinances)<div class="field"><label>Wartość kontraktu netto</label><input type="number" step="0.01" min="0" name="contract_value" value="{{ old('contract_value',$project->contract_value) }}" required></div>@endif
                <div class="field full"><label>Kto może widzieć projekt</label><div class="member-checks">@foreach($users as $user)<label class="member-check"><input type="checkbox" name="member_ids[]" value="{{ $user->id }}" {{ collect(old('member_ids',$project->members->pluck('id')->all()))->contains(fn($id)=>(int)$id===$user->id) ? 'checked' : '' }}><span>{{ $user->name }}</span></label>@endforeach</div><small style="color:#718078">Administratorzy widzą wszystkie projekty. Pozostali użytkownicy zobaczą ten projekt tylko po zaznaczeniu ich na tej liście. Kierownik projektu otrzymuje dostęp automatycznie.</small></div>
                <div class="field full"><label>Opis</label><textarea name="description" rows="4">{{ old('description',$project->description) }}</textarea></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px"><button type="button" class="btn btn-soft" onclick="document.getElementById('project-edit-modal').classList.remove('open')">Anuluj</button><button class="btn">Zapisz zmiany</button></div>
        </form>
        @if($canDeleteProject)
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #fee2e2;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
                <div><strong style="display:block;color:#991b1b;font-size:13px">Usunięcie projektu</strong><small style="color:#7f1d1d">Projekt zniknie z aktywnej listy. Tej operacji nie wykonają zwykli użytkownicy.</small></div>
                <form method="POST" action="{{route('projects.destroy',$project)}}" onsubmit="return confirm('Czy na pewno usunąć ten projekt? Projekt zniknie z aktywnej listy.')">@csrf @method('DELETE')<button class="btn btn-red" type="submit"><i class="ti ti-trash"></i> Usuń projekt</button></form>
            </div>
        @endif
    </div>
</div>
@if($errors->projectEdit->any())<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('project-edit-modal')?.classList.add('open'));</script>@endif
@endif

<div class="tabs">
    @foreach(collect(['overview'=>'Przegląd','gantt'=>'Harmonogram i zadania','finances'=>'Finanse','requirements'=>'Materiały i usługi','documents'=>'Dokumenty'])->filter(fn($label,$id) => match($id) {'gantt'=>$canViewSchedule,'finances'=>$canViewFinances,'requirements'=>$canViewRequirements,'documents'=>$canViewDocuments,default=>true}) as $id=>$label)
    <button class="tab {{ $loop->first?'active':'' }}" onclick="openProjectTab('{{ $id }}',this)">{{ $label }}</button>
    @endforeach
</div>

<section id="pane-overview" class="pane active">
    <div class="grid2">
        <div class="card"><h2>Zespół projektu</h2><div class="team"><span class="person"><strong>Kierownik:</strong> {{ $project->manager?->name ?? '—' }}</span>@foreach($project->members as $member)<span class="person">{{ $member->name }}</span>@endforeach</div></div>
        <div class="card"><h2>Opis</h2><div style="font-size:13px;line-height:1.6;color:#55625a;white-space:pre-line;">{{ $project->description ?: 'Brak opisu.' }}</div></div>
    </div>
</section>

@if($canViewSchedule)
<section id="pane-gantt" class="pane">
    @if(session('gantt_import_report'))
        @php($ganttReport = session('gantt_import_report'))
        <div class="import-report" style="margin-bottom:16px"><strong>Import harmonogramu zakończony</strong><div class="report-grid" style="margin-top:10px"><div class="report-value"><small>Dodano</small><strong>{{$ganttReport['inserted']}}</strong></div><div class="report-value"><small>Duplikaty</small><strong>{{$ganttReport['duplicates']}}</strong></div><div class="report-value"><small>Błędne wiersze</small><strong>{{$ganttReport['invalid']}}</strong></div><div class="report-value"><small>Bez przypisanej osoby</small><strong>{{$ganttReport['unassigned']}}</strong></div></div></div>
    @endif
    <div class="card"><h2>Interaktywny wykres Gantta</h2>
        <div class="gantt-toolbar">
            @if($canManageSchedule)<button type="button" class="tool-btn primary" id="gantt-add-task"><i class="ti ti-plus"></i> Dodaj zadanie</button><button type="button" class="tool-btn" id="gantt-add-milestone"><i class="ti ti-diamond"></i> Dodaj kamień milowy</button>@endif
            <a class="tool-btn" style="text-decoration:none" href="{{route('projects.gantt.export',$project)}}"><i class="ti ti-file-spreadsheet"></i> Eksport Excel</a>
            @if($canManageSchedule)<button type="button" class="tool-btn" onclick="document.getElementById('gantt-import-modal').classList.add('open')"><i class="ti ti-file-upload"></i> Import Excel</button>@endif
            @if($canManageSchedule)<button type="button" class="tool-btn" id="gantt-share"><i class="ti ti-link"></i> Link dla klienta</button>@endif
            <span class="tool-label">Widok:</span>
            @foreach(['Day'=>'Dzień','Week'=>'Tydzień','Month'=>'Miesiąc'] as $mode=>$label)<button type="button" class="tool-btn gantt-mode {{ $mode==='Week'?'active':'' }}" data-mode="{{ $mode }}">{{ $label }}</button>@endforeach
            <button type="button" class="tool-btn" id="gantt-today"><i class="ti ti-calendar-event"></i> Dzisiaj</button>
            <span class="legend" style="margin:0 0 0 auto"><span><i class="dot" style="background:#7C3AED"></i>Zadania</span><span><i class="dot" style="background:#f59e0b;transform:rotate(45deg);border-radius:1px"></i>Kamienie milowe</span><span>Linie: dziś i koniec projektu {{ $project->end_date?->format('d.m.Y') ?? '—' }}</span></span>
        </div>
        @if($canManageSchedule)<div class="gantt-help"><strong>Obsługa:</strong> przeciągnij pasek, aby przesunąć termin; przeciągnij jego krawędź, aby zmienić czas trwania; przeciągnij uchwyt postępu, aby zapisać procent wykonania.</div>@endif
        <div id="project-frappe-gantt" class="frappe-gantt-wrap"></div>
    </div>
    <div class="card"><h2>Lista zadań i kamieni milowych <small style="font-weight:500;color:#78827b">(kolejność jak na Gantcie)</small></h2><div id="gantt-task-list"></div></div>
</section>
@endif

@if($canViewFinances)
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
                <div class="finance-kpi"><small>Faktury wystawione / opłacone</small><strong id="finance-kpi-invoiced" style="color:#15803d">{{number_format($project->totalInvoiced(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Faktury planowane</small><strong id="finance-kpi-planned-invoiced" style="color:#7c3aed">{{number_format($project->plannedInvoiced(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Koszty</small><strong id="finance-kpi-costs" style="color:#b91c1c">{{number_format($project->totalCosts(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Koszty planowane</small><strong id="finance-kpi-planned-costs" style="color:#d97706">{{number_format($project->plannedCosts(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Zamówienia</small><strong id="finance-kpi-committed" style="color:#b45309">{{number_format($committedRequirements,2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Wynik na dziś</small><strong id="finance-kpi-result" style="color:{{$project->result()>=0?'#15803d':'#b91c1c'}}">{{number_format($project->result(),2,',',' ')}} zł</strong></div>
            </div>
            <div class="empty" id="project-cashflow-empty" {{$financeChartData->isEmpty()?'':'hidden'}}>Dodaj koszt, fakturę albo planowany materiał, aby zobaczyć interaktywny wykres.</div>
            <div id="project-cashflow-content" {{$financeChartData->isEmpty()?'hidden':''}}>
                <div class="chart-toolbar">
                    <span class="tool-label">Grupowanie:</span>
                    @foreach(['day'=>'Dzień','week'=>'Tydzień','month'=>'Miesiąc','year'=>'Rok'] as $mode=>$label)<button type="button" class="tool-btn cashflow-mode {{$mode==='month'?'active':''}}" data-mode="{{$mode}}">{{$label}}</button>@endforeach
                    <button type="button" class="tool-btn" id="cashflow-prev">‹ Wcześniej</button><span class="chart-range" id="cashflow-range"></span><button type="button" class="tool-btn" id="cashflow-next">Dalej ›</button>
                    <button type="button" class="tool-btn active" id="cashflow-cumulative">Narastająco</button><button type="button" class="tool-btn" id="cashflow-reset">Resetuj</button>
                </div>
                <div class="chart-shell"><canvas id="project-cashflow-chart"></canvas></div>
                <div class="chart-overview-shell"><canvas id="project-cashflow-overview"></canvas></div>
            </div>
        </div>
    </details>

    @if($canEdit)
    <details class="finance-section" {{session('finance_import_report') || $errors->has('file') ? 'open' : ''}} data-finance-section="import">
        <summary>Import z Excela <small>xlsx, xls lub csv · ochrona przed duplikatami</small></summary>
        <div class="finance-section-body">
            <div class="finance-import-note">Rozpoznawane nagłówki: <strong>Data</strong>, <strong>Kwota netto / Netto / Kwota</strong>, a opcjonalnie: Podmiot/Dostawca, Dokument/Nr faktury, Opis, Status i Termin płatności. Ten sam dokument i kwota nie zostaną zaimportowane ponownie.</div>
            <form method="POST" enctype="multipart/form-data" action="{{route('projects.finances.import',$project)}}" class="finance-entry-form">@csrf
                <div class="grid2">
                    <div class="field"><label>Rodzaj importowanych danych</label><select name="type" class="finance-entry-type" required><option value="cost">Koszty</option><option value="invoice">Faktury dla klienta</option></select></div>
                    <div class="field" data-finance-cost-group><label>Istniejąca grupa</label><select name="finance_group_id"><option value="">Bez grupy</option>@foreach($project->financeGroups as $group)<option value="{{$group->id}}">{{$group->name}}</option>@endforeach</select></div>
                    <div class="field" data-finance-cost-group><label>Lub utwórz nową grupę</label><input name="new_group_name" placeholder="np. Koszty sierpień 2026"></div>
                    <div class="field finance-invoice-note" data-finance-invoice-note hidden>Faktury dla klienta trafią automatycznie do grupy „Wystawione”.</div>
                    <div class="field"><label>Plik Excel / CSV</label><input type="file" name="file" accept=".xlsx,.xls,.csv" required></div>
                </div>
                <button class="btn" style="margin-top:12px"><i class="ti ti-file-spreadsheet"></i> Wczytaj i sprawdź duplikaty</button>
            </form>
        </div>
    </details>

    <details class="finance-section" data-finance-section="manual">
        <summary>Dodaj pozycję ręcznie <small>Koszt lub faktura</small></summary>
        <div class="finance-section-body"><form method="POST" action="{{route('projects.finances.store',$project)}}" class="finance-entry-form">@csrf
            <div class="grid2">
                <div class="field"><label>Rodzaj</label><select name="type" class="finance-entry-type"><option value="cost">Koszt</option><option value="invoice">Faktura dla klienta</option></select></div>
                <div class="field"><label>Nazwa</label><input name="name" required></div>
                <div class="field"><label>Numer dokumentu</label><input name="document_number"></div>
                <div class="field" data-finance-supplier-field><label>Dostawca z bazy</label><select name="supplier_company_id"><option value="">Niepowiązany</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}">{{$supplier->name}}</option>@endforeach</select></div>
                <div class="field" data-finance-supplier-field><label>Dostawca spoza bazy</label><input name="supplier"></div>
                <div class="field"><label>Data dokumentu</label><input type="date" name="entry_date" value="{{now()->format('Y-m-d')}}" required></div>
                <div class="field"><label>Termin płatności</label><input type="date" name="payment_date"></div>
                <div class="field"><label>Kwota netto</label><input type="number" step="0.01" min="0" name="amount" required></div>
                <div class="field"><label>Status</label><select name="status"><option value="planned">Planowana</option><option value="issued">Wystawiona / zaksięgowana</option><option value="paid">Opłacona</option></select></div>
                <div class="field" data-finance-cost-group><label>Grupa</label><select name="finance_group_id"><option value="">Bez grupy</option>@foreach($project->financeGroups as $group)<option value="{{$group->id}}">{{$group->name}}</option>@endforeach</select></div>
                <div class="field finance-invoice-note" data-finance-invoice-note hidden>To faktura wystawiana klientowi. Dane dostawcy nie są potrzebne, a dokument trafi do grupy „Wystawione”.</div>
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
                <label class="requirement-search" for="finance-live-search">
                    <i class="ti ti-search"></i>
                    <input type="search" id="finance-live-search" autocomplete="off" placeholder="Szukaj po kliencie, nazwie, dokumencie, statusie, kwocie, dostawcy…">
                    <span class="requirement-search-count" id="finance-search-count">{{$project->financialEntries->count()}} poz.</span>
                </label>
                @if($canEdit)<form id="finance-bulk-form" method="POST" action="{{route('projects.finances.bulk',$project)}}" onsubmit="return this.elements.action.value !== 'delete' || confirm('Usunąć zaznaczone pozycje?')">@csrf<div style="display:flex;gap:7px;align-items:center;margin-bottom:10px"><select class="status-select" name="action" required><option value="">Operacja grupowa…</option><option value="planned">Oznacz jako planowane</option><option value="issued">Oznacz jako wystawione / zaksięgowane</option><option value="paid">Oznacz jako opłacone</option><option value="delete">Usuń zaznaczone</option></select><button class="btn btn-soft">Wykonaj</button></div></form>@endif
                <div style="overflow-x:auto"><table class="finance-table" id="finance-register-table"><thead><tr>@if($canEdit)<th><input type="checkbox" id="finance-select-all" title="Zaznacz wszystko"></th>@endif<th>Data / płatność</th><th>Rodzaj / grupa</th><th class="finance-name-column">Nazwa / dokument</th><th>Dostawca</th><th>Status</th><th class="finance-amount-column">Kwota</th><th>Źródło</th><th></th></tr></thead><tbody>
                @foreach($project->financialEntries->sortByDesc('entry_date') as $entry)
                <tr data-finance-type="{{$entry->type}}" data-finance-entry-id="{{$entry->id}}" data-finance-search="{{collect([
                        $project->company?->name, $project->name, $project->number,
                        $entry->name, $entry->document_number, $entry->notes,
                        $entry->type, $entry->type === 'invoice' ? 'Faktura dla klienta' : 'Koszt',
                        $entry->financeGroup?->name, $entry->supplierCompany?->name, $entry->supplier,
                        $entry->amount, number_format((float) $entry->amount, 2, ',', ' '),
                        $entry->entry_date->format('d.m.Y'), $entry->entry_date->format('Y-m-d'),
                        $entry->payment_date?->format('d.m.Y'), $entry->payment_date?->format('Y-m-d'),
                        $entry->source, match($entry->source) {'excel_import'=>'Excel','requirement'=>'Materiały',default=>'Ręcznie'},
                    ])->filter()->implode(' ')}}" data-finance-status-search="{{$entry->status}} {{$financeStatusLabels[$entry->status] ?? $entry->status}}">
                    @if($canEdit)<td><input type="checkbox" name="entry_ids[]" value="{{$entry->id}}" form="finance-bulk-form" class="finance-entry-check"></td>@endif
                    <td>{{$entry->entry_date->format('d.m.Y')}}<br><small>{{$entry->payment_date?->format('d.m.Y') ?: '—'}}</small></td>
                    <td>{{$entry->type === 'invoice' ? 'Faktura' : 'Koszt'}}<br><small>{{$entry->financeGroup?->name ?: 'Bez grupy'}}</small></td>
                    <td class="finance-name-column"><strong>{{$entry->name}}</strong><br><small>{{$entry->document_number ?: '—'}}</small></td>
                    <td>@if($entry->type === 'invoice')—@elseif($entry->supplierCompany)<a href="{{route('suppliers.show',$entry->supplierCompany)}}" style="color:var(--green);font-weight:700">{{$entry->supplierCompany->name}}</a>@else{{$entry->supplier ?: '—'}}@endif</td>
                    <td>@if($canEdit)<select class="status-select project-async-status" data-kind="finance" data-id="{{$entry->id}}" data-current="{{$entry->status}}" data-url="{{route('projects.finances.status',[$project,$entry])}}" aria-label="Status pozycji finansowej {{$entry->name}}">@foreach($financeStatusLabels as $value=>$label)<option value="{{$value}}" {{$entry->status === $value ? 'selected' : ''}}>{{$label}}</option>@endforeach</select>@else{{$financeStatusLabels[$entry->status] ?? $entry->status}}@endif</td>
                    <td class="finance-amount-column" style="font-weight:800;color:{{$entry->type === 'invoice' ? '#15803d' : '#b91c1c'}}">{{number_format((float) $entry->amount, 2, ',', ' ')}} zł</td>
                    <td><span class="source-badge">{{match($entry->source){'excel_import'=>'Excel','requirement'=>'Materiały',default=>'Ręcznie'} }}</span></td>
                    <td>@if($canEdit)<div class="mini-actions"><details class="finance-edit"><summary class="mini-btn edit" title="Edytuj">✎</summary><div class="finance-edit-box"><form method="POST" action="{{route('projects.finances.update',[$project,$entry])}}" class="finance-entry-form">@csrf @method('PATCH')<div class="grid2"><div class="field"><label>Rodzaj</label><select name="type" class="finance-entry-type"><option value="cost" {{$entry->type === 'cost' ? 'selected' : ''}}>Koszt</option><option value="invoice" {{$entry->type === 'invoice' ? 'selected' : ''}}>Faktura dla klienta</option></select></div><div class="field"><label>Nazwa</label><input name="name" value="{{$entry->name}}" required></div><div class="field"><label>Dokument</label><input name="document_number" value="{{$entry->document_number}}"></div><div class="field" data-finance-supplier-field><label>Dostawca z bazy</label><select name="supplier_company_id"><option value="">Niepowiązany</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}" {{$entry->supplier_company_id === $supplier->id ? 'selected' : ''}}>{{$supplier->name}}</option>@endforeach</select></div><div class="field" data-finance-supplier-field><label>Dostawca spoza bazy</label><input name="supplier" value="{{$entry->supplier}}"></div><div class="field"><label>Data</label><input type="date" name="entry_date" value="{{$entry->entry_date->format('Y-m-d')}}" required></div><div class="field"><label>Termin płatności</label><input type="date" name="payment_date" value="{{$entry->payment_date?->format('Y-m-d')}}"></div><div class="field"><label>Kwota</label><input type="number" step="0.01" min="0" name="amount" value="{{$entry->amount}}" required></div><div class="field"><label>Status</label><select name="status">@foreach($financeStatusLabels as $value=>$label)<option value="{{$value}}" {{$entry->status === $value ? 'selected' : ''}}>{{$label}}</option>@endforeach</select></div><div class="field" data-finance-cost-group><label>Grupa</label><select name="finance_group_id"><option value="">Bez grupy</option>@foreach($project->financeGroups as $group)<option value="{{$group->id}}" {{$entry->finance_group_id === $group->id ? 'selected' : ''}}>{{$group->name}}</option>@endforeach</select></div><div class="field finance-invoice-note" data-finance-invoice-note hidden>Faktura klienta pozostaje w grupie „Wystawione” i nie ma dostawcy.</div><div class="field full"><label>Uwagi</label><textarea name="notes">{{$entry->notes}}</textarea></div></div><button class="btn" style="margin-top:10px">Zapisz</button></form></div></details><form method="POST" action="{{route('projects.finances.destroy',[$project,$entry])}}" onsubmit="return confirm('Usunąć tę pozycję?')">@csrf @method('DELETE')<button class="mini-btn delete" title="Usuń">×</button></form></div>@endif</td>
                </tr>
                @endforeach
                </tbody></table></div>
                <div class="requirement-search-empty" id="finance-search-empty" hidden>Nie znaleziono pasujących pozycji finansowych.</div>
            @endif
        </div>
    </details>
</section>
@endif

@if(false && $canViewFinances)
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
@if($canViewRequirements)
<style>.requirements-table.with-selection th:last-child{width:96px!important}.requirements-table .requirement-actions{min-width:88px!important}.mini-btn.copy{background:#bbf7d0;color:#166534}.mini-btn.copy:hover{background:#86efac}</style>
<section id="pane-requirements" class="pane">
    @if(session('requirements_import_report'))
        @php($requirementsReport = session('requirements_import_report'))
        <div class="import-report"><strong>Import materiałów i usług zakończony</strong><div class="report-grid" style="margin-top:10px"><div class="report-value"><small>Dodano</small><strong>{{$requirementsReport['inserted']}}</strong></div><div class="report-value"><small>Duplikaty</small><strong>{{$requirementsReport['duplicates']}}</strong></div><div class="report-value"><small>Błędne wiersze</small><strong>{{$requirementsReport['invalid']}}</strong></div><div class="report-value"><small>Bez przypisanej osoby</small><strong>{{$requirementsReport['unassigned']}}</strong></div></div>@if($requirementsReport['unmatched_suppliers'])<div style="margin-top:8px;font-size:11px;color:#66736b">{{$requirementsReport['unmatched_suppliers']}} dostawców nie dopasowano do CRM — ich nazwy zostały zachowane jako dostawcy spoza bazy.</div>@endif</div>
    @endif
    <div class="card">
        <div class="requirements-head">
            <div><h2>Materiały i usługi</h2><p>Zapotrzebowania, zamówienia i dostawy dla tego projektu.</p></div>
            <div class="requirements-head-actions">
                <button type="button" class="btn btn-soft" onclick="openRequirementsExportModal()"><i class="ti ti-file-spreadsheet"></i> Generuj listę Excel</button>
                @if($canEdit)<a class="btn btn-soft" href="{{route('projects.requirements.template',$project)}}"><i class="ti ti-download"></i> Pobierz wzór Excel</a><button type="button" class="btn btn-soft" onclick="document.getElementById('requirements-import').open=true;document.getElementById('requirements-excel-input').click()"><i class="ti ti-file-spreadsheet"></i> Import z Excela</button><button type="button" class="btn" onclick="openRequirementModal()"><i class="ti ti-plus"></i> Dodaj pozycję</button>@endif
            </div>
        </div>
        @if($canEdit)
            <details id="requirements-import" class="requirement-import" @if($errors->requirementsImport->any()) open @endif>
                <summary><i class="ti ti-file-upload"></i> Importuj materiały i usługi z Excela</summary>
                <div class="requirement-import-body">
                    <strong style="display:block;margin-bottom:5px;font-size:12px">Excel / CSV</strong>
                    <p class="requirement-import-help">System sam odnajdzie nagłówki nawet po kilku wierszach tytułu i rozpozna popularne nazwy kolumn, np. „Nazwa materiału”, „Technologia”, „Towar”, „Ilość”, „J.m.”, „Cena netto”, „Termin dostawy”, „Dostawca” czy „Status”. Obsługiwane są XLSX, XLS i CSV. Wymagana jest nazwa pozycji oraz co najmniej jedna dodatkowa rozpoznawalna kolumna.</p>
                    @if($errors->requirementsImport->any())<div style="padding:9px 11px;background:#fef2f2;color:#991b1b;border-radius:7px;margin-bottom:10px;font-size:12px">{{$errors->requirementsImport->first()}}</div>@endif
                    <form class="requirement-import-form" method="POST" enctype="multipart/form-data" action="{{route('projects.requirements.import',$project)}}">@csrf<input id="requirements-excel-input" type="file" name="file" accept=".xlsx,.xls,.csv" required><button class="btn"><i class="ti ti-file-import"></i> Importuj plik</button></form>
                </div>
            </details>
        @endif
        @if($canViewMaterialPrices || $canViewServicePrices)
        @if(!$canViewMaterialPrices || !$canViewServicePrices)
            <p style="margin:0 0 8px;color:#718078;font-size:12px">Podsumowanie obejmuje wyłącznie ceny {{ $canViewMaterialPrices ? 'materiałów' : 'usług' }}, do których masz dostęp.</p>
        @endif
        <div class="finance-summary-grid" style="margin-bottom:14px">
            <div class="finance-kpi requirement-summary-kpi" role="button" tabindex="0" data-requirement-summary="all" data-filter-label="Wszystkie pozycje w podsumowaniu"><small>Łączna wartość zamówienia</small><strong id="requirements-total-value">{{number_format($requirementsTotal,2,',',' ')}} zł</strong></div>
            <div class="finance-kpi requirement-summary-kpi" role="button" tabindex="0" data-requirement-summary="planned" data-filter-label="Planowany budżet"><small>Planowany budżet</small><strong id="requirements-planned-value" style="color:#7c3aed">{{number_format($plannedRequirements,2,',',' ')}} zł</strong></div>
            @foreach($requirementsBySupplier as $supplierSummary)
                <div class="finance-kpi requirement-summary-kpi" role="button" tabindex="0" data-requirement-summary="supplier" data-supplier="{{$supplierSummary['supplier']}}" data-filter-label="Dostawca: {{$supplierSummary['supplier']}}"><small>{{$supplierSummary['supplier']}} · {{$supplierSummary['count']}} poz.</small><strong>{{number_format($supplierSummary['total'],2,',',' ')}} zł</strong></div>
            @endforeach
        </div>
        @endif
        @if($project->requirements->isEmpty())
            <div class="empty">Brak zapotrzebowań.</div>
        @else
            <label class="requirement-search" for="requirements-live-search">
                <i class="ti ti-search"></i>
                <input id="requirements-live-search" type="search" autocomplete="off" placeholder="Szukaj po nazwie, technologii, opisie, dostawcy, osobie, statusie, terminie lub cenie…">
                <span class="requirement-search-count" id="requirements-search-count">{{$project->requirements->count()}} poz.</span>
            </label>
            <div class="requirement-filter-state" id="requirement-summary-filter-state" hidden><span id="requirement-summary-filter-label"></span><button type="button" id="requirement-summary-filter-clear">Pokaż wszystkie</button></div>
            @if($canEdit)
                <form id="requirements-bulk-form" class="requirement-bulk-toolbar" method="POST" action="{{route('projects.requirements.bulk',$project)}}">
                    @csrf
                    <select name="action" id="requirements-bulk-action" required>
                        <option value="">Operacja grupowa…</option>
                        <option value="set_status">Zmień status</option>
                        <option value="set_supplier">Zmień dostawcę</option>
                        <option value="set_responsible">Zmień odpowiedzialnego</option>
                        <option value="set_needed_by">Zmień termin</option>
                        <option value="set_type">Zmień rodzaj</option>
                        <option value="set_technology">Zmień technologię</option>
                        <option value="delete">Usuń zaznaczone</option>
                    </select>
                    <span class="requirement-bulk-value" data-requirement-bulk-field="set_status" hidden><select name="status" disabled>@foreach($requirementStatusLabels as $value=>$label)<option value="{{$value}}">{{$label}}</option>@endforeach</select></span>
                    <span class="requirement-bulk-value" data-requirement-bulk-field="set_supplier" hidden><select name="supplier_company_id" disabled><option value="">Usuń przypisanie dostawcy</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}">{{$supplier->name}}</option>@endforeach</select></span>
                    <span class="requirement-bulk-value" data-requirement-bulk-field="set_responsible" hidden><select name="responsible_id" disabled><option value="">Usuń przypisanie osoby</option>@foreach($projectTeam as $member)<option value="{{$member->id}}">{{$member->name}}</option>@endforeach</select></span>
                    <span class="requirement-bulk-value" data-requirement-bulk-field="set_needed_by" hidden><input type="date" name="needed_by" disabled title="Pozostaw puste, aby usunąć termin"></span>
                    <span class="requirement-bulk-value" data-requirement-bulk-field="set_type" hidden><select name="type" disabled><option value="material">Materiał</option><option value="service">Usługa</option></select></span>
                    <span class="requirement-bulk-value" data-requirement-bulk-field="set_technology" hidden><input name="technology" disabled maxlength="255" placeholder="Np. Pomiary, Zawory; puste usuwa przypisanie"></span>
                    <button class="btn btn-soft" type="submit">Wykonaj</button>
                    <span class="requirement-selected-count" id="requirements-selected-count">Zaznaczono: 0</span>
                </form>
            @endif
            <div class="requirements-table-wrap"><table class="requirements-table {{$canEdit?'with-selection':''}}" style="min-width:1050px"><thead><tr>@if($canEdit)<th><input type="checkbox" id="requirements-select-all" title="Zaznacz wszystkie"></th>@endif<th>Pozycja</th><th style="width:13%">Technologia</th><th>Ilość</th><th>Termin / osoba</th><th>Dostawca</th><th>Status</th>@if($canViewMaterialPrices || $canViewServicePrices)<th>Cena / wartość</th>@endif<th></th></tr></thead><tbody>
            @foreach($project->requirements as $req)
                <tr class="requirement-data-row" data-requirement-status="{{$req->status}}" data-requirement-supplier="{{$req->supplierCompany?->name ?? ($req->supplier ?: 'Bez dostawcy')}}" data-price-visible="{{($req->type === 'service' ? $canViewServicePrices : $canViewMaterialPrices) ? '1' : '0'}}" data-requirement-search="{{collect([
                    $req->type === 'material' ? 'Materiał' : 'Usługa', $req->name, $req->technology, $req->description,
                    $req->formattedQuantity(), $req->displayUnit(), $req->needed_by?->format('d.m.Y'), $req->needed_by?->format('Y-m-d'),
                    $req->responsible?->name, $req->supplierCompany?->name ?? $req->supplier, $req->supplierCompany?->nip,
                    $requirementStatusLabels[$req->status] ?? $req->status, $req->status,
                    ($req->type === 'service' ? $canViewServicePrices : $canViewMaterialPrices) ? $req->unitCost() : null,
                    ($req->type === 'service' ? $canViewServicePrices : $canViewMaterialPrices) ? $req->estimated_cost : null,
                ])->filter(fn($value) => $value !== null && $value !== '')->implode(' ')}}">
                    @if($canEdit)<td><input type="checkbox" name="requirement_ids[]" value="{{$req->id}}" form="requirements-bulk-form" class="requirement-entry-check" aria-label="Zaznacz {{$req->name}}"></td>@endif
                    <td><strong class="requirement-name">{{$req->name}}</strong><div class="requirement-meta"><span class="requirement-type">{{$req->type==='material'?'Materiał':'Usługa'}}</span>@if($req->description)<span class="requirement-description" title="{{$req->description}}">{{$req->description}}</span>@endif</div></td>
                    <td><strong>{{$req->technology ?: '—'}}</strong></td>
                    <td><span class="requirement-qty">{{$req->formattedQuantity()}} {{$req->displayUnit()}}</span></td>
                    <td>{{$req->needed_by?->format('d.m.Y')??'—'}}<br><small>{{$req->responsible?->name??'Nieprzypisane'}}</small></td>
                    <td>@if($req->supplierCompany)<a href="{{route('suppliers.show',$req->supplierCompany)}}" style="color:var(--green);font-weight:700">{{$req->supplierCompany->name}}</a>@else{{$req->supplier ?: '—'}}@endif</td>
                    <td>@if($canEdit)<select class="status-select requirement-status {{$req->status}} project-async-status" data-kind="requirement" data-id="{{$req->id}}" data-current="{{$req->status}}" data-url="{{route('projects.requirements.status',[$project,$req])}}" aria-label="Status {{$req->name}}">@foreach($requirementStatusLabels as $value=>$label)<option value="{{$value}}" {{$req->status===$value?'selected':''}}>{{$label}}</option>@endforeach</select>@else<span class="requirement-status {{$req->status}}">{{$requirementStatusLabels[$req->status]??$req->status}}</span>@endif</td>
                    @if($canViewMaterialPrices || $canViewServicePrices)<td>@if($req->type === 'service' ? $canViewServicePrices : $canViewMaterialPrices) @if($req->unitCost()!==null)<span class="requirement-cost">{{number_format($req->unitCost(),2,',',' ')}} zł / {{$req->displayUnit()}}</span><br><small>Łącznie: {{number_format((float)$req->estimated_cost,2,',',' ')}} zł</small>@else<span class="requirement-cost">—</span>@endif @else<span style="color:#999">Brak dostępu</span>@endif</td>@endif
                    <td>@if($canEdit)<div class="requirement-actions"><button type="button" class="mini-btn copy" title="Kopiuj jako nową pozycję" onclick="openRequirementModal({{$req->id}},true)"><i class="ti ti-copy"></i></button><button type="button" class="mini-btn edit" title="Edytuj" onclick="openRequirementModal({{$req->id}})">✎</button><form method="POST" action="{{route('projects.requirements.destroy',[$project,$req])}}" onsubmit="return confirm('Usunąć tę pozycję?')">@csrf @method('DELETE')<button class="mini-btn delete" title="Usuń">×</button></form></div>@endif</td>
                </tr>
            @endforeach
            </tbody></table></div>
            <div class="requirement-search-empty" id="requirements-search-empty" hidden>Nie znaleziono pasujących materiałów ani usług.</div>
        @endif
    </div>
</section>

@php($requirementsExportAllStatuses = old('all_statuses', '1') === '1')
@php($requirementsExportSelectedStatuses = old('statuses', array_keys($requirementStatusLabels)))
<div id="requirements-export-modal" class="project-modal {{$errors->requirementsExport->any()?'open':''}}">
    <div class="project-modal-box" style="width:min(680px,100%)">
        <div class="modal-head"><h2>Generuj listę materiałów i usług</h2><button type="button" class="modal-close" onclick="closeRequirementsExportModal()">×</button></div>
        <form method="GET" action="{{route('projects.requirements.export',$project)}}" id="requirements-export-form">
            @if($errors->requirementsExport->any())<div style="padding:9px 11px;background:#fef2f2;color:#991b1b;border-radius:7px;margin-bottom:12px;font-size:12px">{{$errors->requirementsExport->first()}}</div>@endif
            <div class="grid2">
                <div class="field"><label>Rodzaj listy *</label><select name="document_type" id="requirements-export-document-type" required><option value="inquiry" {{old('document_type','inquiry')==='inquiry'?'selected':''}}>Zapytanie ofertowe</option><option value="order" {{old('document_type')==='order'?'selected':''}}>Zamówienie</option></select></div>
                <div class="field"><label>Dostawca</label><select name="supplier_filter"><option value="">Wszyscy dostawcy</option>@foreach($requirementExportSuppliers as $exportSupplier)<option value="{{$exportSupplier['value']}}" {{old('supplier_filter')===$exportSupplier['value']?'selected':''}}>{{$exportSupplier['label']}}</option>@endforeach</select></div>
                <div class="field full">
                    <label class="requirement-export-status"><input type="checkbox" name="all_statuses" value="1" id="requirements-export-all-statuses" {{$requirementsExportAllStatuses?'checked':''}}> Wszystkie statusy</label>
                    <div class="requirement-export-statuses" style="margin-top:7px">
                        @foreach($requirementStatusLabels as $value=>$label)<label class="requirement-export-status"><input type="checkbox" name="statuses[]" value="{{$value}}" class="requirements-export-status-checkbox" {{in_array($value,$requirementsExportSelectedStatuses,true)?'checked':''}}> {{$label}}</label>@endforeach
                    </div>
                </div>
                @if($canViewMaterialPrices || $canViewServicePrices)<div class="field full"><label class="requirement-export-status"><input type="checkbox" name="include_prices" value="1" id="requirements-export-include-prices" {{old('include_prices')==='1'?'checked':''}}> Dołącz dostępne ceny zapisane w programie</label></div>@endif
                <div class="field full"><label class="requirement-export-status"><input type="checkbox" name="include_project_context" value="1" {{old('include_project_context')==='1'?'checked':''}}> Dołącz nazwę i numer projektu oraz nazwę klienta</label><small style="color:#66736b">Domyślnie dane te są ukryte, aby dostawca nie otrzymał informacji o kliencie ani projekcie.</small></div>
                <div class="requirement-export-note full">Zamówienie zawsze zawiera zapisane ceny jednostkowe i wartości pozycji. W zapytaniu ofertowym ceny są opcjonalne — po ich wyłączeniu dostawca otrzyma puste kolumny do wpisania ceny, a Excel automatycznie obliczy wartości i sumę.</div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px"><button type="button" class="btn btn-soft" onclick="closeRequirementsExportModal()">Anuluj</button><button class="btn"><i class="ti ti-download"></i> Pobierz Excel</button></div>
        </form>
    </div>
</div>

@if($canEdit)
<div id="requirement-modal" class="project-modal" onclick="if(event.target===this)closeRequirementModal()">
    <div class="project-modal-box requirement-modal-box">
        <div class="modal-head"><h2 id="requirement-modal-title">Dodaj materiał lub usługę</h2><button type="button" class="modal-close" onclick="closeRequirementModal()">×</button></div>
        <form id="requirement-form" method="POST" action="{{route('projects.requirements.store',$project)}}">@csrf<input type="hidden" name="_method" id="requirement-method" value="POST">
            <div class="grid2">
                <div class="field"><label>Rodzaj *</label><select name="type" id="requirement-type"><option value="material">Materiał</option><option value="service">Usługa</option></select></div>
                <div class="field"><label>Nazwa *</label><input name="name" id="requirement-name" required></div>
                <div class="field full"><label>Technologia</label><input name="technology" id="requirement-technology" maxlength="255" placeholder="Np. Pomiary, Zawory, Obieg kotłowy lub oznaczenie ze schematu"><small style="color:#718078">Pozwala sprawdzić, czy wszystkie urządzenia technologiczne mają przypisane materiały.</small></div>
                <div class="field"><label>Ilość *</label><input type="number" step="1" min="1" name="quantity" id="requirement-quantity" value="1" required></div>
                <div class="field"><label>Jednostka</label><input name="unit" id="requirement-unit" placeholder="np. szt., kg, m, usł."><small style="color:#718078">Gdy pole pozostanie puste, użyjemy „szt.” lub „usł.”.</small></div>
                @if($canViewMaterialPrices || $canViewServicePrices)<div class="field" id="requirement-unit-cost-field"><label>Cena za jednostkę</label><input type="number" step="0.01" min="0" name="unit_cost" id="requirement-unit-cost"><small style="color:#718078">Wartość łączna zostanie obliczona jako ilość × cena za jednostkę.</small></div>@endif
                <div class="field"><label>Potrzebne do</label><input type="date" name="needed_by" id="requirement-needed-by"></div>
                <div class="field"><label>Odpowiedzialny</label><select name="responsible_id" id="requirement-responsible"><option value="">Nieprzypisane</option>@foreach($projectTeam as $member)<option value="{{$member->id}}">{{$member->name}}</option>@endforeach</select></div>
                <div class="field"><label>Status</label><select name="status" id="requirement-status">@foreach($requirementStatusLabels as $value=>$label)<option value="{{$value}}">{{$label}}</option>@endforeach</select></div>
                <div class="field"><label>Dostawca z bazy</label><select name="supplier_company_id" id="requirement-supplier-company"><option value="">Nie wybrano</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}">{{$supplier->name}}</option>@endforeach</select></div>
                <div class="field"><label>Dostawca spoza bazy</label><input name="supplier" id="requirement-supplier"></div>
                <div class="field full"><label>Opis</label><textarea name="description" id="requirement-description" rows="3"></textarea></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px"><button type="button" class="btn btn-soft" onclick="closeRequirementModal()">Anuluj</button><button class="btn">Zapisz pozycję</button></div>
        </form>
    </div>
</div>
@endif
@endif

@if($canViewDocuments)
<section id="pane-documents" class="pane">
    @if($canEdit)<div class="card"><h2>Dodaj dokument projektu</h2><form method="POST" enctype="multipart/form-data" action="{{route('projects.documents.store',$project)}}">@csrf<div style="display:flex;gap:10px;align-items:center"><input type="file" name="file" required><button class="btn">Wgraj dokument</button></div><small>PDF, Word, Excel, obrazy lub ZIP, maks. 20 MB.</small></form></div>@endif
    <div class="card"><h2>Dokumenty projektu</h2>@if($project->documents->isEmpty())<div class="empty">Brak dokumentów.</div>@else<table><thead><tr><th>Plik</th><th>Rozmiar</th><th>Dodał</th><th>Data</th><th></th></tr></thead><tbody>@foreach($project->documents as $document)<tr><td><a href="{{route('projects.documents.download',[$project,$document])}}"><strong>{{$document->original_filename}}</strong></a></td><td>{{$document->formattedSize()}}</td><td>{{$document->uploader?->name??'System'}}</td><td>{{$document->created_at->format('d.m.Y H:i')}}</td><td>@if($canEdit)<form method="POST" action="{{route('projects.documents.destroy',[$project,$document])}}">@csrf @method('DELETE')<button class="btn btn-red">Usuń</button></form>@endif</td></tr>@endforeach</tbody></table>@endif</div>
</section>
@endif

@if($canManageSchedule)
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
                <div class="field"><label>Osoba odpowiedzialna</label><select id="gantt-task-assignee"><option value="">Nieprzypisane</option>@foreach($project->members as $member)<option value="{{$member->id}}">{{$member->name}}</option>@endforeach</select></div>
                <div class="field"><label>Priorytet</label><select id="gantt-task-priority"><option value="low">Niski</option><option value="medium" selected>Średni</option><option value="high">Wysoki</option></select></div>
                <div class="field full"><label>Opis</label><textarea id="gantt-task-description" rows="3"></textarea></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px"><button type="button" class="btn btn-soft" onclick="closeGanttTaskModal()">Anuluj</button><button class="btn" id="gantt-task-save">Zapisz zadanie</button></div>
        </form>
    </div>
</div>
@endif

@if($canManageSchedule)
<div id="gantt-import-modal" class="project-modal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="project-modal-box" style="width:min(620px,100%)">
        <div class="modal-head"><div><h2>Import harmonogramu z Excela</h2><small style="color:#718078">Zadania, kolejność i zależności zostaną przeniesione do tego projektu.</small></div><button type="button" class="modal-close" onclick="document.getElementById('gantt-import-modal').classList.remove('open')">×</button></div>
        @if($errors->ganttImport->any())<div style="padding:11px 13px;background:#fef2f2;color:#991b1b;border-radius:8px;margin-bottom:14px;">{{$errors->ganttImport->first()}}</div>@endif
        <form method="POST" enctype="multipart/form-data" action="{{route('projects.gantt.import',$project)}}">@csrf
            <div class="grid2">
                <div class="field full"><label>Plik harmonogramu *</label><input type="file" name="file" accept=".xlsx,.xls,.csv" required><small style="color:#718078">Obsługiwany jest nowy eksport oraz wcześniejszy format eksportu Gantta.</small></div>
                <div class="field full"><label>Nowa data rozpoczęcia harmonogramu</label><input type="date" name="new_start_date" value="{{old('new_start_date',$project->start_date?->format('Y-m-d'))}}"><small style="color:#718078">Wszystkie zadania przesuną się o tę samą liczbę dni. Wyczyść pole, aby zachować daty z pliku.</small></div>
            </div>
            <div style="padding:10px 12px;background:#f6f8f5;border-radius:8px;margin-top:14px;font-size:12px;color:#66736b">Istniejące identyczne zadania nie zostaną dodane ponownie. Osoby z zespołu projektu są dopasowywane po adresie e-mail, a potem po unikalnej nazwie.</div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px"><button type="button" class="btn btn-soft" onclick="document.getElementById('gantt-import-modal').classList.remove('open')">Anuluj</button><button class="btn"><i class="ti ti-file-upload"></i> Importuj harmonogram</button></div>
        </form>
    </div>
</div>
@if($errors->ganttImport->any())<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('gantt-import-modal')?.classList.add('open'));</script>@endif
@endif

<script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.0/dist/frappe-gantt.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const projectTimelineItems = @json($timelineItems);
const projectFinanceItems = @json($financeChartData);
const projectContractValue = @json($canViewFinances ? (float) $project->contract_value : 0);
const requestedProjectTab = @json(request('tab') ?: ($errors->requirementsExport->any() ? 'requirements' : ($errors->ganttImport->any() ? 'gantt' : null)));
const projectCanEdit = @json($canManageSchedule);
const projectStartDate = @json($project->start_date?->format('Y-m-d'));
const projectEndDate = @json($project->end_date?->format('Y-m-d'));
const projectCsrfToken = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
const ganttBulkDeleteUrl = @json(route('projects.tasks.bulk-destroy', $project));
const requirementStoreUrl = @json(route('projects.requirements.store', $project));
const projectRequirementItems = @json($requirementItems);
const canViewMaterialPrices = @json($canViewMaterialPrices);
const canViewServicePrices = @json($canViewServicePrices);
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

function openRequirementModal(requirementId = null, copy = false) {
    const modal=document.getElementById('requirement-modal'),form=document.getElementById('requirement-form');if(!modal||!form)return;
    form.reset();form.action=requirementStoreUrl;document.getElementById('requirement-method').value='POST';
    const requirement=requirementId?projectRequirementItems.find(item=>item.id===Number(requirementId)):null;
    document.getElementById('requirement-modal-title').textContent=copy?'Kopiuj materiał lub usługę':(requirement?'Edytuj materiał lub usługę':'Dodaj materiał lub usługę');
    document.getElementById('requirement-type').value=requirement?.type||'material';
    document.getElementById('requirement-name').value=requirement?.name||'';
    document.getElementById('requirement-technology').value=requirement?.technology||'';
    document.getElementById('requirement-quantity').value=requirement?.quantity??1;
    document.getElementById('requirement-unit').value=requirement?.unit||'';
    if(document.getElementById('requirement-unit-cost')) document.getElementById('requirement-unit-cost').value=requirement?.unit_cost??'';
    document.getElementById('requirement-needed-by').value=requirement?.needed_by||'';
    document.getElementById('requirement-responsible').value=requirement?.responsible_id||'';
    document.getElementById('requirement-supplier-company').value=requirement?.supplier_company_id||'';
    document.getElementById('requirement-supplier').value=requirement?.supplier||'';
    document.getElementById('requirement-status').value=requirement?.status||'requested';
    document.getElementById('requirement-description').value=requirement?.description||'';
    syncRequirementQuantityIncrement();
    syncRequirementPriceVisibility();
    if(requirement&&!copy){form.action=requirement.update_url;document.getElementById('requirement-method').value='PATCH';}
    modal.classList.add('open');
}
function closeRequirementModal(){document.getElementById('requirement-modal')?.classList.remove('open');}
function syncRequirementPriceVisibility(){
    const type=document.getElementById('requirement-type')?.value||'material';
    const allowed=type==='service'?canViewServicePrices:canViewMaterialPrices;
    const field=document.getElementById('requirement-unit-cost-field');
    if(field) field.hidden=!allowed;
    const input=document.getElementById('requirement-unit-cost');
    if(input) input.disabled=!allowed;
}
function openRequirementsExportModal(){document.getElementById('requirements-export-modal')?.classList.add('open');}
function closeRequirementsExportModal(){document.getElementById('requirements-export-modal')?.classList.remove('open');}

const requirementsExportAllStatuses = document.getElementById('requirements-export-all-statuses');
const requirementsExportStatusCheckboxes = document.querySelectorAll('.requirements-export-status-checkbox');
const requirementsExportDocumentType = document.getElementById('requirements-export-document-type');
const requirementsExportIncludePrices = document.getElementById('requirements-export-include-prices');
function syncRequirementsExportStatuses() {
    if (!requirementsExportAllStatuses) return;
    requirementsExportStatusCheckboxes.forEach(checkbox => {
        checkbox.disabled = requirementsExportAllStatuses.checked;
        if (requirementsExportAllStatuses.checked) checkbox.checked = true;
    });
}
requirementsExportAllStatuses?.addEventListener('change', syncRequirementsExportStatuses);
syncRequirementsExportStatuses();
function syncRequirementsExportPrices() {
    if (!requirementsExportDocumentType || !requirementsExportIncludePrices) return;
    const isOrder = requirementsExportDocumentType.value === 'order';
    requirementsExportIncludePrices.disabled = isOrder;
    if (isOrder) requirementsExportIncludePrices.checked = true;
}
requirementsExportDocumentType?.addEventListener('change', syncRequirementsExportPrices);
syncRequirementsExportPrices();
function syncRequirementQuantityIncrement() {
    const quantity = document.getElementById('requirement-quantity');
    const unit = document.getElementById('requirement-unit')?.value.toLowerCase().replace(/[.\s]/g, '') || '';
    const type = document.getElementById('requirement-type')?.value || 'material';
    const requirementQuantityUsesWholePieces = unit.startsWith('szt') || (unit === '' && type === 'material');
    quantity.step = requirementQuantityUsesWholePieces ? '1' : '0.01';
    quantity.min = requirementQuantityUsesWholePieces ? '1' : '0.01';
}
document.getElementById('requirement-unit')?.addEventListener('input', syncRequirementQuantityIncrement);
document.getElementById('requirement-type')?.addEventListener('change', () => { syncRequirementQuantityIncrement(); syncRequirementPriceVisibility(); });

function projectMoney(value) {
    return Number(value || 0).toLocaleString('pl-PL', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' zł';
}
function updateFinanceKpis(summary) {
    const values = {
        'finance-kpi-invoiced': summary.invoiced,
        'finance-kpi-planned-invoiced': summary.planned_invoiced,
        'finance-kpi-costs': summary.costs,
        'finance-kpi-planned-costs': summary.planned_costs,
        'finance-kpi-result': summary.result,
    };
    Object.entries(values).forEach(([id, value]) => {const element=document.getElementById(id);if(element)element.textContent=projectMoney(value);});
    const result = document.getElementById('finance-kpi-result');
    if (result) result.style.color = Number(summary.result) >= 0 ? '#15803d' : '#b91c1c';
}
async function saveProjectStatus(select) {
    const previous = select.dataset.current;
    select.disabled = true;
    try {
        const response = await fetch(select.dataset.url, {
            method: 'PATCH',
            headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':projectCsrfToken},
            body: JSON.stringify({status: select.value}),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Nie udało się zapisać statusu.');
        select.dataset.current = data.status;
        if (select.dataset.kind === 'finance') {
            const entry = projectFinanceItems.find(item => Number(item.id) === Number(select.dataset.id));
            if (entry) entry.status = data.status;
            const row = select.closest('[data-finance-entry-id]');
            if (row) row.dataset.financeStatusSearch = data.status + ' ' + select.options[select.selectedIndex].text;
            updateFinanceKpis(data.summary);
            renderProjectCashflow();
            applyFinanceFilters();
        } else {
            const requirement = projectRequirementItems.find(item => Number(item.id) === Number(select.dataset.id));
            if (requirement) requirement.status = data.status;
            select.classList.remove('planned','requested','ordered','in_progress','purchased','cancelled');
            select.classList.add(data.status);
            const committed = document.getElementById('finance-kpi-committed');
            if (committed) committed.textContent = projectMoney(data.committed_requirements);
            const plannedBudget = document.getElementById('requirements-planned-value');
            if (plannedBudget) plannedBudget.textContent = projectMoney(data.planned_requirements);
            const plannedEntryId = 'requirement-' + select.dataset.id;
            const plannedEntryIndex = projectFinanceItems.findIndex(item => String(item.id) === plannedEntryId);
            if (plannedEntryIndex >= 0) projectFinanceItems.splice(plannedEntryIndex, 1);
            if (data.planned_requirement_entry) projectFinanceItems.push(data.planned_requirement_entry);
            if (data.financial_entry) {
                const existingEntry = projectFinanceItems.find(item => Number(item.id) === Number(data.financial_entry.id));
                if (existingEntry) Object.assign(existingEntry, data.financial_entry);
                else projectFinanceItems.push(data.financial_entry);
            }
            if (data.removed_financial_entry_id) {
                const removedIndex = projectFinanceItems.findIndex(item => Number(item.id) === Number(data.removed_financial_entry_id));
                if (removedIndex >= 0) projectFinanceItems.splice(removedIndex, 1);
                document.querySelector(`[data-finance-entry-id="${Number(data.removed_financial_entry_id)}"]`)?.remove();
            }
            if (data.summary) {
                updateFinanceKpis(data.summary);
                renderProjectCashflow();
            }
        }
    } catch (error) {
        select.value = previous;
        alert(error.message);
    } finally {
        select.disabled = false;
    }
}
document.querySelectorAll('.project-async-status').forEach(select => select.addEventListener('change', () => saveProjectStatus(select)));

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
    if (typeof Chart === 'undefined') return;
    const emptyState = document.getElementById('project-cashflow-empty');
    const chartContent = document.getElementById('project-cashflow-content');
    if (!projectFinanceItems.length) {
        projectCashflowChart?.destroy(); projectCashflowChart = null;
        projectCashflowOverview?.destroy(); projectCashflowOverview = null;
        if (emptyState) emptyState.hidden = false;
        if (chartContent) chartContent.hidden = true;
        return;
    }
    if (emptyState) emptyState.hidden = true;
    if (chartContent) chartContent.hidden = false;
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
    const range = document.getElementById('cashflow-range');
    if (!context || !range) return;
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
function syncFinanceEntryForm(form) {
    const isInvoice = form.querySelector('.finance-entry-type')?.value === 'invoice';
    form.querySelectorAll('[data-finance-supplier-field],[data-finance-cost-group]').forEach(field => {
        field.hidden = isInvoice;
        field.querySelectorAll('input,select').forEach(control => control.disabled = isInvoice);
    });
    form.querySelectorAll('[data-finance-invoice-note]').forEach(note => note.hidden = !isInvoice);
}
document.querySelectorAll('.finance-entry-form').forEach(form => {
    syncFinanceEntryForm(form);
    form.querySelector('.finance-entry-type')?.addEventListener('change', () => syncFinanceEntryForm(form));
});
const financeSearch = document.getElementById('finance-live-search');
const financeRows = [...document.querySelectorAll('#finance-register-table [data-finance-type]')];
let activeFinanceFilter = 'all';
function normalizeFinanceSearch(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}
function applyFinanceFilters() {
    const query = normalizeFinanceSearch(financeSearch?.value);
    let visible = 0;
    financeRows.forEach(row => {
        const matchesType = activeFinanceFilter === 'all' || row.dataset.financeType === activeFinanceFilter;
        const searchableText = normalizeFinanceSearch((row.dataset.financeSearch || '') + ' ' + (row.dataset.financeStatusSearch || ''));
        const matchesSearch = !query || searchableText.includes(query);
        row.classList.toggle('finance-row-hidden', !matchesType || !matchesSearch);
        if (matchesType && matchesSearch) visible++;
    });
    const counter = document.getElementById('finance-search-count');
    const empty = document.getElementById('finance-search-empty');
    if (counter) counter.textContent = visible + ' z ' + financeRows.length + ' poz.';
    if (empty) empty.hidden = visible !== 0;
}
document.querySelectorAll('.register-tab').forEach(button => button.addEventListener('click', () => {
    activeFinanceFilter = button.dataset.financeFilter;
    document.querySelectorAll('.register-tab').forEach(item => item.classList.toggle('active', item === button));
    applyFinanceFilters();
}));
financeSearch?.addEventListener('input', applyFinanceFilters);
document.getElementById('finance-select-all')?.addEventListener('change', event => {
    financeRows.filter(row => ! row.classList.contains('finance-row-hidden')).forEach(row => {
        const checkbox = row.querySelector('.finance-entry-check');
        if (checkbox) checkbox.checked = event.currentTarget.checked;
    });
});
const requirementBulkAction = document.getElementById('requirements-bulk-action');
const requirementBulkForm = document.getElementById('requirements-bulk-form');
const requirementSelectAll = document.getElementById('requirements-select-all');
const requirementChecks = [...document.querySelectorAll('.requirement-entry-check')];
const requirementRows = [...document.querySelectorAll('.requirement-data-row')];
const requirementSearch = document.getElementById('requirements-live-search');
const requirementSummaryTiles = [...document.querySelectorAll('.requirement-summary-kpi')];
let activeRequirementSummaryTile = null;
function normalizeRequirementSearch(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}
function visibleRequirementChecks() {
    return requirementChecks.filter(checkbox => !checkbox.closest('.requirement-data-row')?.hidden);
}
function syncRequirementBulkFields() {
    document.querySelectorAll('[data-requirement-bulk-field]').forEach(field => {
        const active = field.dataset.requirementBulkField === requirementBulkAction?.value;
        field.hidden = !active;
        field.querySelectorAll('select,input').forEach(control => control.disabled = !active);
    });
}
function syncRequirementSelection() {
    const selected = requirementChecks.filter(checkbox => checkbox.checked).length;
    const visibleChecks = visibleRequirementChecks();
    const selectedVisible = visibleChecks.filter(checkbox => checkbox.checked).length;
    const counter = document.getElementById('requirements-selected-count');
    if (counter) counter.textContent = 'Zaznaczono: ' + selected;
    if (requirementSelectAll) {
        requirementSelectAll.checked = visibleChecks.length > 0 && selectedVisible === visibleChecks.length;
        requirementSelectAll.indeterminate = selectedVisible > 0 && selectedVisible < visibleChecks.length;
    }
}
requirementBulkAction?.addEventListener('change', syncRequirementBulkFields);
requirementSelectAll?.addEventListener('change', event => {
    visibleRequirementChecks().forEach(checkbox => checkbox.checked = event.currentTarget.checked);
    syncRequirementSelection();
});
requirementChecks.forEach(checkbox => checkbox.addEventListener('change', syncRequirementSelection));
function applyRequirementFilters() {
    const terms = normalizeRequirementSearch(requirementSearch?.value).split(/\s+/).filter(Boolean);
    let visible = 0;
    requirementRows.forEach(row => {
        const haystack = normalizeRequirementSearch(row.dataset.requirementSearch);
        const matchesSearch = terms.every(term => haystack.includes(term));
        let matchesSummary = true;
        if (activeRequirementSummaryTile) {
            const summary = activeRequirementSummaryTile.dataset.requirementSummary;
            const hasVisiblePrice = row.dataset.priceVisible === '1';
            const isActive = row.dataset.requirementStatus !== 'cancelled';
            if (summary === 'all') matchesSummary = hasVisiblePrice && isActive;
            if (summary === 'planned') matchesSummary = hasVisiblePrice && row.dataset.requirementStatus === 'planned';
            if (summary === 'supplier') matchesSummary = hasVisiblePrice && isActive && row.dataset.requirementSupplier === activeRequirementSummaryTile.dataset.supplier;
        }
        const matches = matchesSearch && matchesSummary;
        row.hidden = !matches;
        if (matches) visible++;
        const checkbox = row.querySelector('.requirement-entry-check');
        if (!matches && checkbox) checkbox.checked = false;
    });
    const resultCount = document.getElementById('requirements-search-count');
    if (resultCount) resultCount.textContent = visible + ' poz.';
    const empty = document.getElementById('requirements-search-empty');
    if (empty) empty.hidden = visible !== 0;
    syncRequirementSelection();
}
function setRequirementSummaryFilter(tile) {
    activeRequirementSummaryTile = tile;
    requirementSummaryTiles.forEach(item => item.classList.toggle('active', item === tile));
    const state = document.getElementById('requirement-summary-filter-state');
    const label = document.getElementById('requirement-summary-filter-label');
    if (state) state.hidden = !tile;
    if (label) label.textContent = tile ? 'Aktywny filtr: ' + tile.dataset.filterLabel : '';
    applyRequirementFilters();
    document.querySelector('.requirements-table-wrap')?.scrollIntoView({behavior:'smooth', block:'start'});
}
requirementSearch?.addEventListener('input', applyRequirementFilters);
requirementSummaryTiles.forEach(tile => {
    tile.addEventListener('click', () => setRequirementSummaryFilter(activeRequirementSummaryTile === tile ? null : tile));
    tile.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            setRequirementSummaryFilter(activeRequirementSummaryTile === tile ? null : tile);
        }
    });
});
document.getElementById('requirement-summary-filter-clear')?.addEventListener('click', () => {
    if (requirementSearch) requirementSearch.value = '';
    setRequirementSummaryFilter(null);
});
requirementBulkForm?.addEventListener('submit', event => {
    const selected = requirementChecks.filter(checkbox => checkbox.checked).length;
    if (!selected) {
        event.preventDefault();
        alert('Zaznacz co najmniej jedną pozycję.');
        return;
    }
    if (requirementBulkAction?.value === 'delete' && !confirm('Usunąć zaznaczone materiały i usługi?')) event.preventDefault();
});
syncRequirementBulkFields();
syncRequirementSelection();
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
