@extends('layouts.app')
@section('page-title','HR — lokalizacje samochodu')
@section('content')
<style>
.hr-head,.hr-tabs,.hr-actions{display:flex;align-items:center;gap:10px}.hr-head{justify-content:space-between;margin-bottom:18px;flex-wrap:wrap}.hr-tabs{border-bottom:2px solid #e5e1d8;margin-bottom:18px;flex-wrap:wrap}.hr-tab{padding:10px 16px;margin-bottom:-2px;text-decoration:none;color:#778078;font-size:13px;font-weight:700;border-bottom:2px solid transparent}.hr-tab.active{color:var(--green);border-color:var(--green)}.hr-card{background:#fff;border:1px solid #e5e1d8;border-radius:12px;overflow:hidden}.hr-card-head{padding:14px 18px;background:#fafaf6;border-bottom:1px solid #eee;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.hr-table{width:100%;border-collapse:collapse;font-size:13px}.hr-table th,.hr-table td{padding:11px 13px;text-align:left;border-bottom:1px solid #f1eee8;vertical-align:top}.hr-table th{font-size:10px;text-transform:uppercase;color:#7b827d;background:#fafaf6}.hr-btn{border:0;border-radius:7px;padding:9px 13px;background:var(--green);color:#fff;font-weight:700;cursor:pointer;text-decoration:none}.hr-btn.danger{background:#fee2e2;color:#991b1b;padding:6px 9px}.hr-filter,.hr-input{padding:9px 10px;border:1px solid #cec8bc;border-radius:7px;background:#fff}.hr-modal{display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:18px}.hr-modal.open{display:flex}.hr-modal-card{background:#fff;border-radius:14px;width:min(820px,100%);max-height:92vh;overflow:auto;padding:24px}.hr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.hr-field label{display:block;font-size:11px;font-weight:700;margin-bottom:4px}.hr-field input,.hr-field select,.hr-field textarea{width:100%;box-sizing:border-box;padding:9px 10px;border:1px solid #cec8bc;border-radius:7px}.hr-full{grid-column:1/-1}.hr-empty{text-align:center;padding:42px;color:#89918c}.hr-badge{display:inline-block;padding:3px 8px;border-radius:99px;background:#eef6f1;color:var(--green);font-size:11px;font-weight:700}@media(max-width:700px){.hr-grid{grid-template-columns:1fr}.hr-full{grid-column:auto}.hr-table{min-width:850px}}
</style>
@php
    $today = now('Europe/Warsaw')->toDateString();
    $all = $vehicle->locationPeriods->sortBy('starts_on');
    $history = $all->filter(fn($p) => $p->ends_on && $p->ends_on->toDateString() < $today);
    $planned = $all->filter(fn($p) => !$p->ends_on || $p->ends_on->toDateString() >= $today)->map(fn($p) => ['id'=>$p->id,'location'=>$p->location,'starts_on'=>$p->starts_on->toDateString(),'ends_on'=>$p->ends_on?->toDateString()])->values()->all();
    if (!(int)$vehicle->location_revision) $planned = [['location'=>$vehicle->usage_location ?? '', 'starts_on'=>now('Europe/Warsaw')->startOfMonth()->toDateString(), 'ends_on'=>now('Europe/Warsaw')->endOfMonth()->toDateString()]];
    $planned = old('periods', $planned);
@endphp
<style>.location-plan-fields{grid-template-columns:2fr 1fr 1fr}.location-plan-fields label{display:grid;gap:6px;font-size:12px}.location-plan-fields input{width:100%}@media(max-width:700px){.location-plan-fields{grid-template-columns:1fr}}</style>
<div class="hr-card">
<div class="hr-card-head"><h2>Lokalizacje: {{$vehicle->name}} · {{$vehicle->registration_number}}</h2><a class="hr-btn" href="{{route('hr.index',['tab'=>'vehicles'])}}">Wróć do samochodów</a></div>
<div style="padding:20px">
@if(session('success'))<p role="status">{{session('success')}}</p>@endif
@if($errors->any())<p role="alert" style="color:#b91c1c">{{$errors->first()}}</p>@endif
<p>Obecna lokalizacja: <strong>{{$vehicle->usage_location ?: 'Nieokreślona'}}</strong></p>
@if(!(int)$vehicle->location_revision && $vehicle->usage_location)<p>Dotychczasowa lokalizacja obowiązuje bezterminowo do zapisania planu. Data jej rozpoczęcia nie była rejestrowana.</p>@endif
@if($canEditLocation)
<p>Domyślny okres obejmuje bieżący miesiąc. Daty są włączne. Przy zmianie aktualnego przypisania dotychczasowa lokalizacja zostaje zachowana do wczoraj, a nowa obowiązuje od dzisiaj. Nie można nadpisywać zakończonej historii.</p>
<form method="POST" action="{{route('hr.vehicles.location-plan.save',$vehicle)}}" id="location-plan" data-form-draft="false">
@csrf @method('PUT')
<input type="hidden" name="revision" value="{{old('revision',$vehicle->location_revision)}}">
<div id="location-periods"></div>
<div class="hr-actions"><button class="hr-btn" type="button" id="add-location-period">+ Dodaj okres</button><button class="hr-btn" type="submit">Zapisz przypisania</button></div>
</form>
<datalist id="vehicle-location-choices">@foreach(\App\Models\HrVehicleLocation::orderBy('name')->pluck('name') as $name)<option value="{{$name}}"></option>@endforeach<option value="Nie dotyczy"></option></datalist>
<template id="location-period-template">
<fieldset style="border:1px solid #cad6d0;border-radius:8px;padding:14px;margin:12px 0">
<div class="hr-grid location-plan-fields">
<label>Lokalizacja <input class="hr-input" data-location-field="location" list="vehicle-location-choices" maxlength="255" required></label>
<label>Od <input class="hr-input" data-location-field="starts_on" type="date" required></label>
<label>Do <input class="hr-input" data-location-field="ends_on" type="date" required></label>
</div>
<input data-location-field="id" type="hidden">
<label><input type="checkbox" data-permanent> Na stałe — bez daty końcowej, od dzisiaj</label>
<button class="hr-btn danger" type="button" data-remove-period>Usuń okres</button>
</fieldset>
</template>
@else
<h3>Obecne i zaplanowane przypisania</h3>
@foreach($planned as $p)<p>{{$p['location']}}: {{$p['starts_on']}} — {{$p['ends_on'] ?: 'Na stałe'}}</p>@endforeach
@endif
<h3 style="margin-top:24px">Historia zakończonych przypisań</h3>
<table class="hr-table"><thead><tr><th>Lokalizacja</th><th>Od</th><th>Do</th></tr></thead><tbody>
@forelse($history as $period)<tr><td>{{$period->location}}</td><td data-sort-value="{{$period->starts_on->toDateString()}}">{{$period->starts_on->format('d.m.Y')}}</td><td data-sort-value="{{$period->ends_on->toDateString()}}">{{$period->ends_on->format('d.m.Y')}}</td></tr>
@empty<tr data-sort-fixed><td colspan="3">Brak zakończonych przypisań.</td></tr>@endforelse
</tbody></table>
</div></div>
@if($canEditLocation)
<script>
(() => {
const form=document.getElementById('location-plan'), rows=document.getElementById('location-periods');
const renumber=()=>rows.querySelectorAll('fieldset').forEach((row,i)=>row.querySelectorAll('[data-location-field]').forEach(input=>input.name='periods['+i+']['+input.dataset.locationField+']'));
const add=(data={})=>{
const row=document.getElementById('location-period-template').content.firstElementChild.cloneNode(true);
row.querySelectorAll('[data-location-field]').forEach(input=>input.value=data[input.dataset.locationField]??'');
if(!data.starts_on){row.querySelector('[data-location-field="starts_on"]').value=@js(now('Europe/Warsaw')->startOfMonth()->toDateString());row.querySelector('[data-location-field="ends_on"]').value=@js(now('Europe/Warsaw')->endOfMonth()->toDateString());}
const permanent=row.querySelector('[data-permanent]'), end=row.querySelector('[data-location-field="ends_on"]'),start=row.querySelector('[data-location-field="starts_on"]');
const sync=()=>{end.disabled=permanent.checked;end.required=!permanent.checked;end.closest('label').hidden=permanent.checked;start.closest('label').hidden=permanent.checked;};
permanent.checked=!!data.starts_on && !data.ends_on;sync();
permanent.addEventListener('change',()=>{if(permanent.checked){start.value=@js($today);end.value='';}sync();});
row.querySelector('[data-remove-period]').addEventListener('click',()=>{row.remove();renumber();});
rows.append(row);renumber();
};
@js($planned).forEach(add);
document.getElementById('add-location-period').addEventListener('click',()=>add());
})();
</script>
@endif
@endsection
