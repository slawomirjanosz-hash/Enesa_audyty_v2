@php
    $vehicleColumns = \App\Models\HrVehicle::COLUMNS;
    $selectedColumns = auth()->user()->hr_vehicle_columns ?? array_keys($vehicleColumns);
    $visibleColumns = array_intersect_key($vehicleColumns, array_flip(array_merge(['name','registration_number'],$selectedColumns)));
@endphp
<style>
.vehicles-card{container-type:inline-size}.vehicles-tools{display:flex;gap:10px;align-items:center}.vehicle-columns{position:relative}.vehicle-columns summary{cursor:pointer;padding:9px;border:1px solid #cad6d0;border-radius:7px;font-size:12px}.vehicle-columns form{position:absolute;right:0;top:100%;z-index:50;background:#fff;border:1px solid #cad6d0;border-radius:9px;padding:16px;width:300px;max-height:65vh;overflow:auto;box-shadow:0 8px 24px #0002}.vehicle-columns label{display:flex;gap:8px;align-items:center;font-size:12px;margin-bottom:9px}.vehicle-columns input{width:15px;height:15px;accent-color:var(--green)}.vehicle-legend{font-size:12px;padding:12px 18px;color:#64746d}.vehicle-table-wrap{overflow-x:auto}.hr-table.vehicle-table{table-layout:fixed;width:100%;min-width:calc(var(--columns)*60px);font-size:clamp(10px,.85cqi,13px)}.vehicle-table th,.vehicle-table td{padding:9px 5px;overflow-wrap:anywhere;vertical-align:middle}.vehicle-table th{font-size:clamp(9px,.72cqi,11px);text-transform:none}.vehicle-table th:first-child{width:30px}.vehicle-table th:last-child{width:115px}.vehicle-table td a{color:var(--green);font-weight:700;text-decoration:none}.vehicle-table tr[data-vehicle-edit]{cursor:pointer}.vehicle-table tr[data-vehicle-edit]:hover{box-shadow:inset 0 0 0 2px #9caeaa}.vehicle-table tr:focus-visible{outline:2px solid var(--green);outline-offset:-2px}.vehicle-table tr.vehicle-overdue>td{background:#fee2e2;color:#991b1b}.vehicle-table .deadline-soon{background:#fef3c7;color:#854d0e;font-weight:700}.vehicle-table .deadline-urgent,.vehicle-table .deadline-overdue{background:#fecaca;color:#991b1b;font-weight:700}.vehicle-table time{white-space:nowrap}.vehicle-table .app-table-sort{max-width:100%;gap:2px}.vehicle-actions{display:flex;gap:4px;align-items:center}.vehicle-error{color:#b91c1c}
.hr-table.vehicle-table{min-width:calc(var(--columns)*58px)}
.vehicle-table th:last-child,.vehicle-table td:last-child{position:sticky;right:0;z-index:2;background:#fff;box-shadow:-2px 0 4px #0001}.vehicle-table th:last-child{background:#fafaf6}.vehicle-table .vehicle-actions{justify-content:center}.vehicle-table td a.vehicle-name-link{text-decoration:underline;text-underline-offset:3px}
</style>
<div class="hr-card vehicles-card" style="overflow:visible">
<div class="hr-card-head"><strong>Auta — służbowe i prywatne</strong><div class="vehicles-tools">
<details class="vehicle-columns"><summary>Moje kolumny</summary><form method="POST" action="{{route('hr.vehicles.columns')}}">@csrf @method('PUT')
<p style="font-size:12px;margin-bottom:12px">Wybór zapisywany tylko dla Twojego konta.</p>
@foreach($vehicleColumns as $key=>$label)
<label><input type="checkbox" name="columns[]" value="{{$key}}" @checked(isset($visibleColumns[$key])) @disabled(in_array($key,['name','registration_number']))>{{$label}}</label>
@if(in_array($key,['name','registration_number']))<input type="hidden" name="columns[]" value="{{$key}}">@endif
@endforeach
<button class="hr-btn" type="submit">Zapisz widok</button></form></details>
<button class="hr-btn" type="button" onclick="openHrModal('vehicle-modal')"><i class="ti ti-plus"></i> Dodaj auto</button></div></div>
<div class="vehicle-legend">Terminy ubezpieczenia i przeglądu: żółty — do 14 dni, czerwony — do 7 dni (także dzisiaj), czerwony wiersz — po terminie. Prywatne auta są widoczne tylko dla autora.</div>
<div class="vehicle-table-wrap"><table class="hr-table vehicle-table" style="--columns:{{count($visibleColumns)+2}}"><thead><tr><th>Lp.</th>
@foreach($visibleColumns as $key=>$label)<th title="{{$label}}" data-sort-label="{{$label}}" @if(in_array($key,['registration_number','fuel_card_number'])) data-sort-type="text" @endif>{{\App\Models\HrVehicle::SHORT_COLUMNS[$key] ?? $label}}</th>@endforeach<th data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($vehicles as $vehicle)
@php
    $insuranceState = $vehicle->deadlineState('insurance_expires_at');
    $inspectionState = $vehicle->deadlineState('inspection_due_at');
    $overdue = in_array('overdue',[$insuranceState,$inspectionState]);
    $editable = $vehicle->canEdit(auth()->user());
@endphp
<tr class="{{$overdue?'vehicle-overdue':''}}" @if($editable) data-vehicle-edit="{{route('hr.vehicles.edit',$vehicle)}}" tabindex="0" aria-label="Edytuj auto {{$vehicle->name}}" title="Kliknij, aby edytować samochód" @endif>
<td>{{$loop->iteration}}</td>
@foreach($visibleColumns as $key=>$label)
@php
    $value = $vehicle->$key;
    $deadline = match($key){'insurance_expires_at'=>$insuranceState,'inspection_due_at'=>$inspectionState,default=>''};
    $dateField = in_array($key,['insurance_expires_at','inspection_due_at','logbook_entry_at','service_book_entry_at']);
    $boolField = in_array($key,['vat_26','gps']);
    $display = match(true){
        $key==='registration_document' && $vehicle->registration_document_present!==null=>$vehicle->registration_document_present?'Tak':'Nie',
        $key==='type'=>$value==='private'?'Prywatny':'Służbowy',
        $key==='service_book_entry_at' && $vehicle->service_book_status==='missing'=>'Brak książki',
        $dateField=>$value?->format('d.m.Y') ?? '—',
        $boolField=>$value===null?'—':($value?'Tak':'Nie'),
        in_array($key,['vat_percent','cit_percent'])=>$value===null?'—':rtrim(rtrim(number_format((float)$value,2,',',' '),'0'),',').'%',
        default=>$value ?: '—',
    };
    $sortValue = $dateField ? $value?->format('Y-m-d') : ($boolField ? ($value===null?'':(int)$value) : $value);
    if($key==='registration_document') $sortValue = $display;
@endphp
<td class="{{$deadline?'deadline-'.$deadline:''}}" data-sort-value="{{$key==='type'?$display:$sortValue}}" @if($deadline) title="{{$label}}: {{ match($deadline) { 'overdue'=>'termin minął', 'urgent'=>'do 7 dni', default=>'do 14 dni' } }}" @endif>
@if($key==='usage_location' && $editable)
<form method="POST" action="{{route('hr.vehicles.location',$vehicle)}}">@csrf @method('PATCH')
<select name="usage_location" aria-label="Lokalizacja {{$vehicle->name}}" onchange="this.form.submit()" style="width:100%;font:inherit"><option value="">Nie podano</option>
@foreach($vehicleLocations as $location)<option value="{{$location->name}}" @selected($value===$location->name)>{{$location->name}}</option>@endforeach
@if($value && !$vehicleLocations->contains('name',$value))<option value="{{$value}}" selected>{{$value}} (dotychczasowa)</option>@endif
</select><noscript><button type="submit">Zapisz</button></noscript></form>
@elseif($key==='name' && $editable)<a class="vehicle-name-link" href="{{route('hr.vehicles.edit',$vehicle)}}" title="Edytuj samochód">{{$display}}</a>@elseif($dateField && $value && $display!=='Brak książki')<time datetime="{{$value->format('Y-m-d')}}">{{$display}}</time>@else{{$display}}@endif
@if($key==='registration_document' && $vehicle->registration_document_present!==null && $value)<small style="display:block" title="{{$value}}">{{$value}}</small>@endif
</td>
@endforeach
<td><div class="vehicle-actions"><a class="hr-btn" href="{{route('hr.vehicles.service.index',$vehicle)}}" title="Książka serwisowa auta" aria-label="Książka serwisowa auta"><i class="ti ti-book"></i></a>@if($editable)<a class="hr-btn" href="{{route('hr.vehicles.edit',$vehicle)}}" title="Edytuj auto" aria-label="Edytuj auto"><i class="ti ti-pencil"></i></a><form method="POST" action="{{route('hr.vehicles.destroy',$vehicle)}}" onsubmit="return confirm('Usunąć samochód z aktywnej listy?')">@csrf @method('DELETE')<button class="hr-btn danger" title="Usuń auto" aria-label="Usuń auto"><i class="ti ti-trash"></i></button></form>@endif</div></td>
</tr>
@empty<tr data-sort-fixed><td colspan="{{count($visibleColumns)+2}}" class="hr-empty">Brak samochodów.</td></tr>@endforelse
</tbody></table></div></div>
<script>
if(!window.hrVehicleNavigationReady){
    window.hrVehicleNavigationReady=true;
    document.addEventListener('click',event=>{
        if(event.target.closest('a,button,input,select,form'))return;
        const row=event.target.closest('.vehicle-table [data-vehicle-edit]');
        if(row)location.assign(row.dataset.vehicleEdit);
    });
    document.addEventListener('keydown',event=>{
        if(event.key==='Enter'&&event.target.matches('.vehicle-table [data-vehicle-edit]'))location.assign(event.target.dataset.vehicleEdit);
    });
}
</script>
