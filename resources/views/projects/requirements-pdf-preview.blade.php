@extends('layouts.app')

@section('page-title', 'Podgląd importu PDF — '.$project->name)

@section('content')
<style>
.pdf-preview-head{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;margin-bottom:18px}.pdf-preview-head h1{margin:0 0 5px;font-size:23px}.pdf-preview-head p{margin:0;color:#6a766f;font-size:13px}.pdf-card{background:#fff;border:1px solid #e5e1d8;border-radius:11px;padding:18px;margin-bottom:15px}.pdf-notice{border:1px solid #fde68a;background:#fffbeb;color:#854d0e;border-radius:8px;padding:11px 13px;font-size:12px;margin-bottom:14px}.pdf-notice ul{margin:7px 0 0 18px;padding:0}.pdf-table-wrap{overflow:auto;border:1px solid #e8e5de;border-radius:9px}.pdf-table{width:100%;min-width:1420px;border-collapse:collapse}.pdf-table th,.pdf-table td{padding:8px;border-bottom:1px solid #eee;text-align:left;vertical-align:top;font-size:11px}.pdf-table th{position:sticky;top:0;z-index:2;background:#f7f8f5;color:#59645d;text-transform:uppercase;font-size:9px}.pdf-table input,.pdf-table select,.pdf-table textarea{box-sizing:border-box;width:100%;border:1px solid #d8d3c8;border-radius:6px;padding:7px;font:inherit;background:#fff}.pdf-table textarea{resize:vertical;min-height:34px}.pdf-include{width:17px!important;height:17px;accent-color:var(--green)}.pdf-source{display:block;color:#7b857f;font-size:9px;margin-top:5px}.pdf-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:15px}.pdf-btn{border:0;border-radius:7px;padding:10px 14px;background:var(--green);color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.pdf-btn.soft{background:#edf4ef;color:var(--green)}.pdf-summary{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.pdf-chip{background:#eef4ef;color:#285740;border-radius:999px;padding:5px 9px;font-size:10px;font-weight:800}@media(max-width:700px){.pdf-preview-head{flex-direction:column}.pdf-actions{flex-direction:column}.pdf-btn{text-align:center}}
</style>

<div class="pdf-preview-head">
    <div><h1>Sprawdź dane odczytane z PDF</h1><p>{{ $project->number }} · {{ $project->name }}</p><div class="pdf-summary"><span class="pdf-chip">{{$pages}} stron</span><span class="pdf-chip">{{count($rows)}} rozpoznanych pozycji</span></div></div>
    <a class="pdf-btn soft" href="{{route('projects.show',['project'=>$project,'tab'=>'requirements'])}}"><i class="ti ti-arrow-left"></i> Wróć bez importowania</a>
</div>

<div class="pdf-notice"><strong>Sprawdź szczególnie ilości, ceny i jednostki.</strong> Układ PDF nie przechowuje tabel tak precyzyjnie jak Excel, dlatego wszystkie pola pozostają edytowalne przed zapisem.@if($warnings)<ul>@foreach($warnings as $warning)<li>{{$warning}}</li>@endforeach</ul>@endif</div>

<form id="pdf-confirm-form" method="POST" action="{{route('projects.requirements.pdf.confirm',$project)}}">
    @csrf
    <input type="hidden" name="rows_json" id="pdf-rows-json">
    <div class="pdf-card">
        <div class="pdf-table-wrap">
            <table class="pdf-table">
                <thead><tr><th style="width:42px">Dodaj</th><th style="width:105px">Rodzaj</th><th style="width:230px">Nazwa i opis</th><th style="width:150px">Ilość / jednostka</th><th style="width:120px">Koszt łącznie</th><th style="width:145px">Termin / status</th><th style="width:260px">Dostawca</th><th style="width:180px">Odpowiedzialny</th></tr></thead>
                <tbody>
                @foreach($rows as $index => $row)
                    <tr class="pdf-import-row">
                        <td><input class="pdf-include" type="checkbox" checked><span class="pdf-source">{{$row['source']}}</span></td>
                        <td><select class="pdf-type"><option value="material" @selected($row['type']==='material')>Materiał</option><option value="service" @selected($row['type']==='service')>Usługa</option></select></td>
                        <td><input class="pdf-name" value="{{$row['name']}}" required><textarea class="pdf-description" placeholder="Opis / uwagi">{{$row['description']}}</textarea></td>
                        <td><div style="display:grid;grid-template-columns:1fr 1fr;gap:5px"><input class="pdf-quantity" type="number" min="0.01" step="0.01" value="{{$row['quantity']}}" required><input class="pdf-unit" value="{{$row['unit']}}" placeholder="szt."></div></td>
                        <td><input class="pdf-cost" type="number" min="0" step="0.01" value="{{$row['estimated_cost']}}" placeholder="—"></td>
                        <td><input class="pdf-needed" type="date" value="{{$row['needed_by']}}"><select class="pdf-status" style="margin-top:5px">@foreach($statusLabels as $value=>$label)<option value="{{$value}}" @selected($row['status']===$value)>{{$label}}</option>@endforeach</select></td>
                        <td><select class="pdf-supplier-company"><option value="">Dostawca spoza bazy / brak</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}" @selected((int)$row['supplier_company_id']===$supplier->id)>{{$supplier->name}}</option>@endforeach</select><input class="pdf-supplier" style="margin-top:5px" value="{{$row['supplier']}}" placeholder="Nazwa spoza CRM"></td>
                        <td><select class="pdf-responsible"><option value="">Nieprzypisane</option>@foreach($team as $person)<option value="{{$person->id}}" @selected((int)$row['responsible_id']===$person->id)>{{$person->name}}</option>@endforeach</select></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pdf-actions"><a class="pdf-btn soft" href="{{route('projects.show',['project'=>$project,'tab'=>'requirements'])}}">Anuluj</a><button class="pdf-btn" type="submit"><i class="ti ti-check"></i> Zatwierdź i dodaj wybrane pozycje</button></div>
    </div>
</form>

<script>
document.querySelectorAll('.pdf-import-row').forEach(row=>{
    const checkbox=row.querySelector('.pdf-include');
    checkbox.addEventListener('change',()=>row.querySelectorAll('input:not(.pdf-include),select,textarea').forEach(field=>field.disabled=!checkbox.checked));
});
document.getElementById('pdf-confirm-form').addEventListener('submit',function(event){
    const rows=[...document.querySelectorAll('.pdf-import-row')].filter(row=>row.querySelector('.pdf-include').checked).map(row=>{
        const nullableValue=selector=>{const value=row.querySelector(selector).value.trim();return value===''?null:value};
        return {type:row.querySelector('.pdf-type').value,name:row.querySelector('.pdf-name').value.trim(),description:nullableValue('.pdf-description'),quantity:Number(row.querySelector('.pdf-quantity').value),unit:nullableValue('.pdf-unit'),estimated_cost:nullableValue('.pdf-cost')===null?null:Number(row.querySelector('.pdf-cost').value),needed_by:nullableValue('.pdf-needed'),status:row.querySelector('.pdf-status').value,supplier:nullableValue('.pdf-supplier'),supplier_company_id:nullableValue('.pdf-supplier-company'),responsible_id:nullableValue('.pdf-responsible')};
    });
    if(rows.length===0){event.preventDefault();alert('Wybierz co najmniej jedną pozycję do importu.');return}
    document.getElementById('pdf-rows-json').value=JSON.stringify(rows);
});
</script>
@endsection
