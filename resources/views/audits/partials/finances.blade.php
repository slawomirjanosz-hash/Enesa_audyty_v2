@php
$financeStatusLabels=['planned'=>'Planowana','issued'=>'Wystawiona / zaksięgowana','paid'=>'Opłacona'];
$financeChartData=$audit->financialEntries->sortBy('entry_date')->map(fn($entry)=>['id'=>$entry->id,'date'=>$entry->entry_date->format('Y-m-d'),'amount'=>(float)$entry->amount,'type'=>$entry->type,'status'=>$entry->status])->values();
@endphp
<section id="aw-finances" class="aw-pane">
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
                <div class="finance-kpi"><small>Wartość kontraktu</small><strong>{{number_format((float)$audit->contract_value,2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Faktury wystawione / opłacone</small><strong id="finance-kpi-invoiced" style="color:#15803d">{{number_format($audit->totalInvoiced(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Faktury planowane</small><strong id="finance-kpi-planned-invoiced" style="color:#7c3aed">{{number_format($audit->plannedInvoiced(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Koszty</small><strong id="finance-kpi-costs" style="color:#b91c1c">{{number_format($audit->totalCosts(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Koszty planowane</small><strong id="finance-kpi-planned-costs" style="color:#d97706">{{number_format($audit->plannedCosts(),2,',',' ')}} zł</strong></div>
                <div class="finance-kpi"><small>Wynik na dziś</small><strong id="finance-kpi-result" style="color:{{$audit->result()>=0?'#15803d':'#b91c1c'}}">{{number_format($audit->result(),2,',',' ')}} zł</strong></div>
            </div>
            <div class="empty" id="project-cashflow-empty" {{$financeChartData->isEmpty()?'':'hidden'}}>Dodaj koszt, fakturę aby zobaczyć interaktywny wykres.</div>
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

    @if($canManage)
    <details class="finance-section" {{session('finance_import_report') || $errors->has('file') ? 'open' : ''}} data-finance-section="import">
        <summary>Import z Excela <small>xlsx, xls lub csv · ochrona przed duplikatami</small></summary>
        <div class="finance-section-body">
            <div class="finance-import-note">Rozpoznawane nagłówki: <strong>Data</strong>, <strong>Kwota netto / Netto / Kwota</strong>, a opcjonalnie: Podmiot/Dostawca, Dokument/Nr faktury, Opis, Status i Termin płatności. Ten sam dokument i kwota nie zostaną zaimportowane ponownie.</div>
            <form method="POST" enctype="multipart/form-data" action="{{route('audits.finances.import',$audit)}}" class="finance-entry-form">@csrf
                <div class="grid2">
                    <div class="field"><label>Rodzaj importowanych danych</label><select name="type" class="finance-entry-type" required><option value="cost">Koszty</option><option value="invoice">Faktury dla klienta</option></select></div>
                    <div class="field" data-finance-cost-group><label>Istniejąca grupa</label><select name="finance_group_id"><option value="">Bez grupy</option>@foreach($audit->financeGroups as $group)<option value="{{$group->id}}">{{$group->name}}</option>@endforeach</select></div>
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
        <div class="finance-section-body"><form method="POST" action="{{route('audits.finances.store',$audit)}}" class="finance-entry-form">@csrf
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
                <div class="field" data-finance-cost-group><label>Grupa</label><select name="finance_group_id"><option value="">Bez grupy</option>@foreach($audit->financeGroups as $group)<option value="{{$group->id}}">{{$group->name}}</option>@endforeach</select></div>
                <div class="field finance-invoice-note" data-finance-invoice-note hidden>To faktura wystawiana klientowi. Dane dostawcy nie są potrzebne, a dokument trafi do grupy „Wystawione”.</div>
                <div class="field full"><label>Uwagi</label><textarea name="notes" rows="2"></textarea></div>
            </div><button class="btn" style="margin-top:12px">Dodaj pozycję</button>
        </form></div>
    </details>
    @endif

    <details class="finance-section" open data-finance-section="register">
        <summary>Rejestr finansowy <small>{{$audit->financialEntries->count()}} pozycji</small></summary>
        <div class="finance-section-body">
            @if($canManage)<form method="POST" action="{{route('audits.finance-groups.store',$audit)}}" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:10px">@csrf<div class="field"><label>Nowa grupa kosztów / faktur</label><input name="name" required></div><button class="btn btn-soft">Dodaj grupę</button></form>
            <div class="finance-groups">@foreach($audit->financeGroups as $group)<span class="finance-group-chip">{{$group->name}} ({{$group->entries->count()}})<form method="POST" action="{{route('audits.finance-groups.destroy',[$audit,$group])}}">@csrf @method('DELETE')<button title="Usuń grupę">×</button></form></span>@endforeach</div>@endif
            @if($audit->financialEntries->isEmpty())<div class="empty">Brak pozycji.</div>@else
                <div class="finance-register-tabs" style="margin-top:12px"><button type="button" class="register-tab active" data-finance-filter="all">Wszystko</button><button type="button" class="register-tab" data-finance-filter="invoice">Faktury dla klienta</button><button type="button" class="register-tab" data-finance-filter="cost">Koszty</button></div>
                <label class="requirement-search" for="finance-live-search">
                    <i class="ti ti-search"></i>
                    <input type="search" id="finance-live-search" autocomplete="off" placeholder="Szukaj po kliencie, nazwie, dokumencie, statusie, kwocie, dostawcy…">
                    <span class="requirement-search-count" id="finance-search-count">{{$audit->financialEntries->count()}} poz. · Suma: {{number_format((float) $audit->financialEntries->sum('amount'), 2, ',', ' ')}} zł</span>
                </label>
                @if($canManage)<form id="finance-bulk-form" method="POST" action="{{route('audits.finances.bulk',$audit)}}" onsubmit="return this.elements.action.value !== 'delete' || confirm('Usunąć zaznaczone pozycje?')">@csrf<div style="display:flex;gap:7px;align-items:center;margin-bottom:10px"><select class="status-select" name="action" required><option value="">Operacja grupowa…</option><option value="planned">Oznacz jako planowane</option><option value="issued">Oznacz jako wystawione / zaksięgowane</option><option value="paid">Oznacz jako opłacone</option><option value="delete">Usuń zaznaczone</option></select><button class="btn btn-soft">Wykonaj</button></div></form>@endif
                <div style="overflow-x:auto"><table class="finance-table" id="finance-register-table"><thead><tr>@if($canManage)<th><input type="checkbox" id="finance-select-all" title="Zaznacz wszystko"></th>@endif<th>Data / płatność</th><th>Rodzaj / grupa</th><th class="finance-name-column">Nazwa / dokument</th><th>Dostawca</th><th>Status</th><th class="finance-amount-column">Kwota</th><th>Źródło</th><th></th></tr></thead><tbody>
                @foreach($audit->financialEntries->sortByDesc('entry_date') as $entry)
                <tr data-finance-type="{{$entry->type}}" data-finance-entry-id="{{$entry->id}}" data-finance-search="{{collect([
                        $audit->company?->name, $audit->title, $audit->number,
                        $entry->name, $entry->document_number, $entry->notes,
                        $entry->type, $entry->type === 'invoice' ? 'Faktura dla klienta' : 'Koszt',
                        $entry->financeGroup?->name, $entry->supplierCompany?->name, $entry->supplier,
                        $entry->amount, number_format((float) $entry->amount, 2, ',', ' '),
                        $entry->entry_date->format('d.m.Y'), $entry->entry_date->format('Y-m-d'),
                        $entry->payment_date?->format('d.m.Y'), $entry->payment_date?->format('Y-m-d'),
                        $entry->source, match($entry->source) {'excel_import'=>'Excel','requirement'=>'Materiały',default=>'Ręcznie'},
                    ])->filter()->implode(' ')}}" data-finance-status-search="{{$entry->status}} {{$financeStatusLabels[$entry->status] ?? $entry->status}}" data-finance-sort-date="{{$entry->entry_date->format('Y-m-d')}}" data-finance-sort-type="{{$entry->type === 'invoice' ? 'Faktura' : 'Koszt'}} {{$entry->financeGroup?->name ?: 'Bez grupy'}}" data-finance-sort-name="{{$entry->name}} {{$entry->document_number}}" data-finance-sort-supplier="{{$entry->type === 'invoice' ? '' : ($entry->supplierCompany?->name ?: $entry->supplier)}}" data-finance-sort-status="{{$financeStatusLabels[$entry->status] ?? $entry->status}}" data-finance-sort-amount="{{(float) $entry->amount}}" data-finance-sort-source="{{match($entry->source){'excel_import'=>'Excel','requirement'=>'Materiały',default=>'Ręcznie'} }}">
                    @if($canManage)<td><input type="checkbox" name="entry_ids[]" value="{{$entry->id}}" form="finance-bulk-form" class="finance-entry-check"></td>@endif
                    <td data-sort-value="{{$entry->entry_date->format('Y-m-d')}}">{{$entry->entry_date->format('d.m.Y')}}<br><small>{{$entry->payment_date?->format('d.m.Y') ?: '—'}}</small></td>
                    <td>{{$entry->type === 'invoice' ? 'Faktura' : 'Koszt'}}<br><small>{{$entry->financeGroup?->name ?: 'Bez grupy'}}</small></td>
                    <td class="finance-name-column"><strong>{{$entry->name}}</strong><br><small>{{$entry->document_number ?: '—'}}</small></td>
                    <td>@if($entry->type === 'invoice')—@elseif($entry->supplierCompany)<a href="{{route('suppliers.show',$entry->supplierCompany)}}" style="color:var(--green);font-weight:700">{{$entry->supplierCompany->name}}</a>@else{{$entry->supplier ?: '—'}}@endif</td>
                    <td>@if($canManage)<select class="status-select project-async-status" data-kind="finance" data-id="{{$entry->id}}" data-current="{{$entry->status}}" data-url="{{route('audits.finances.status',[$audit,$entry])}}" aria-label="Status pozycji finansowej {{$entry->name}}">@foreach($financeStatusLabels as $value=>$label)<option value="{{$value}}" {{$entry->status === $value ? 'selected' : ''}}>{{$label}}</option>@endforeach</select>@else{{$financeStatusLabels[$entry->status] ?? $entry->status}}@endif</td>
                    <td data-sort-value="{{$entry->amount}}" class="finance-amount-column" style="font-weight:800;color:{{$entry->type === 'invoice' ? '#15803d' : '#b91c1c'}}">{{number_format((float) $entry->amount, 2, ',', ' ')}} zł</td>
                    <td><span class="source-badge">{{match($entry->source){'excel_import'=>'Excel','requirement'=>'Materiały',default=>'Ręcznie'} }}</span></td>
                    <td>@if($canManage)<div class="mini-actions"><details class="finance-edit"><summary class="mini-btn edit" title="Edytuj">✎</summary><div class="finance-edit-box"><form method="POST" action="{{route('audits.finances.update',[$audit,$entry])}}" class="finance-entry-form">@csrf @method('PATCH')<div class="grid2"><div class="field"><label>Rodzaj</label><select name="type" class="finance-entry-type"><option value="cost" {{$entry->type === 'cost' ? 'selected' : ''}}>Koszt</option><option value="invoice" {{$entry->type === 'invoice' ? 'selected' : ''}}>Faktura dla klienta</option></select></div><div class="field"><label>Nazwa</label><input name="name" value="{{$entry->name}}" required></div><div class="field"><label>Dokument</label><input name="document_number" value="{{$entry->document_number}}"></div><div class="field" data-finance-supplier-field><label>Dostawca z bazy</label><select name="supplier_company_id"><option value="">Niepowiązany</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}" {{$entry->supplier_company_id === $supplier->id ? 'selected' : ''}}>{{$supplier->name}}</option>@endforeach</select></div><div class="field" data-finance-supplier-field><label>Dostawca spoza bazy</label><input name="supplier" value="{{$entry->supplier}}"></div><div class="field"><label>Data</label><input type="date" name="entry_date" value="{{$entry->entry_date->format('Y-m-d')}}" required></div><div class="field"><label>Termin płatności</label><input type="date" name="payment_date" value="{{$entry->payment_date?->format('Y-m-d')}}"></div><div class="field"><label>Kwota</label><input type="number" step="0.01" min="0" name="amount" value="{{$entry->amount}}" required></div><div class="field"><label>Status</label><select name="status">@foreach($financeStatusLabels as $value=>$label)<option value="{{$value}}" {{$entry->status === $value ? 'selected' : ''}}>{{$label}}</option>@endforeach</select></div><div class="field" data-finance-cost-group><label>Grupa</label><select name="finance_group_id"><option value="">Bez grupy</option>@foreach($audit->financeGroups as $group)<option value="{{$group->id}}" {{$entry->finance_group_id === $group->id ? 'selected' : ''}}>{{$group->name}}</option>@endforeach</select></div><div class="field finance-invoice-note" data-finance-invoice-note hidden>Faktura klienta pozostaje w grupie „Wystawione” i nie ma dostawcy.</div><div class="field full"><label>Uwagi</label><textarea name="notes">{{$entry->notes}}</textarea></div></div><button class="btn" style="margin-top:10px">Zapisz</button></form></div></details><form method="POST" action="{{route('audits.finances.destroy',[$audit,$entry])}}" onsubmit="return confirm('Usunąć tę pozycję?')">@csrf @method('DELETE')<button class="mini-btn delete" title="Usuń">×</button></form></div>@endif</td>
                </tr>
                @endforeach
                </tbody></table></div>
                <div class="requirement-search-empty" id="finance-search-empty" hidden>Nie znaleziono pasujących pozycji finansowych.</div>
            @endif
        </div>
    </details>
</section>
