@extends('layouts.app')
@section('page-title','Protokół odbioru dostawcy')
@section('content')
<style>
.protocol-card{max-width:1150px;margin:0 auto;background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:26px}.protocol-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.protocol-card label{display:block;font-weight:700;margin-bottom:6px}.protocol-card input:not([type=checkbox]),.protocol-card select,.protocol-card textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cecac0;border-radius:7px;font:inherit}.protocol-card textarea{min-height:90px}.protocol-full{grid-column:1/-1}.protocol-card table{width:100%;border-collapse:collapse}.protocol-card th,.protocol-card td{padding:9px;text-align:left;border-bottom:1px solid #eee}.protocol-card button,.protocol-link{display:inline-block;border:0;border-radius:7px;padding:10px 14px;background:var(--green);color:white;text-decoration:none;cursor:pointer}.protocol-help{color:#66736b;font-size:13px;margin:8px 0 20px}.protocol-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:22px}@media(max-width:650px){.protocol-grid{grid-template-columns:1fr}.protocol-card{padding:15px}}
.protocol-card .app-table-sort{padding:0;background:transparent;color:inherit}
</style>
<div class="protocol-card"><a href="{{route('projects.show',[$project,'tab'=>'protocols'])}}">← Protokoły projektu</a><h1>{{$protocol->exists ? 'Edytuj '.$protocol->number : 'Nowy protokół odbioru'}}</h1><p>{{$project->number}} · {{$project->name}}</p>
@if($errors->any())<div role="alert" style="padding:12px;background:#fef2f2;color:#b91c1c"><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{$protocol->exists ? route('projects.protocols.update',[$project,$protocol]) : route('projects.protocols.store',$project)}}">@csrf @if($protocol->exists)@method('PUT')@endif
<input type="hidden" name="revision" value="{{old('revision',$protocol->revision ?? 0)}}">
@isset($copiedFrom)<p class="protocol-help">Kopia protokołu {{$copiedFrom}}. Nowy numer zostanie nadany po zapisaniu. Ustawiono dzisiejszą datę; podpis i termin usunięcia usterek nie zostały skopiowane. Przed zapisem sprawdź dane i ponownie wybierz decyzję o fakturowaniu (domyślnie: nie).</p>@endisset
<div class="protocol-grid">
<div class="protocol-full"><label for="protocol-supplier">Dostawca *</label><select id="protocol-supplier" name="supplier_company_id" required><option value="">Wybierz dostawcę</option>@foreach($suppliers as $supplier)<option value="{{$supplier->id}}" @selected(old('supplier_company_id',$protocol->supplier_company_id)==$supplier->id)>{{$supplier->name}} · {{$supplier->nip}}</option>@endforeach</select></div>
@foreach(['acceptance_date'=>'Data odbioru *','place'=>'Miejsce odbioru','reference'=>'Numer zamówienia / umowy'] as $key=>$label)<div><label for="protocol-{{$key}}">{{$label}}</label><input id="protocol-{{$key}}" name="{{$key}}" type="{{$key==='acceptance_date'?'date':'text'}}" value="{{old($key,$key==='acceptance_date'?($protocol->acceptance_date?->toDateString() ?? date('Y-m-d')):$protocol->$key)}}" @required($key==='acceptance_date')></div>@endforeach
@foreach(['kind'=>['Rodzaj odbioru',\App\Models\ProjectProtocol::KINDS],'outcome'=>['Wynik odbioru',\App\Models\ProjectProtocol::OUTCOMES],'invoice_decision'=>['Czy dostawca może wystawić fakturę?',\App\Models\ProjectProtocol::INVOICES]] as $key=>[$label,$options])<div><label for="protocol-{{$key}}">{{$label}} *</label><select id="protocol-{{$key}}" name="{{$key}}" required>@foreach($options as $value=>$text)<option value="{{$value}}" @selected(old($key,$protocol->$key ?? ($key==='invoice_decision'?'no':array_key_first($options)))===$value)>{{$text}}</option>@endforeach</select></div>@endforeach
<div class="protocol-full"><label for="protocol-description">Przedmiot i zakres odbioru *</label><textarea id="protocol-description" name="description" required>{{old('description',$protocol->description)}}</textarea></div>
</div>
<h2>Odbierane materiały i usługi</h2><p class="protocol-help">Wpisz ilości faktycznie odebrane. Wszystkie kwoty w PLN. Suma jest obliczana i zapisywana przez system.</p>
<div style="overflow:auto"><table id="protocol-items"><thead><tr><th>Nazwa / opis</th><th>Ilość</th><th>Jednostka</th><th>Cena netto</th><th>VAT</th><th>Akcje</th></tr></thead><tbody>
@foreach(old('items',$protocol->items ?? [['name'=>'','quantity'=>1,'unit'=>'szt.','price'=>0,'vat'=>'23']]) as $i=>$item)
@include('projects.protocols.item-row')
@endforeach
</tbody></table></div><p><button type="button" id="protocol-add-item">+ Dodaj pozycję</button></p><p id="protocol-totals" aria-live="polite" style="padding:12px;background:#f6f7f5;border-radius:7px"></p>
<div class="protocol-grid">
@foreach(['remarks'=>'Uwagi, usterki i zastrzeżenia','invoice_conditions'=>'Warunki fakturowania / uzgodnienia rozliczeniowe','attachments'=>'Wykaz załączników (np. dokumentacja, atesty, wyniki prób)'] as $key=>$label)<div class="protocol-full"><label for="protocol-{{$key}}">{{$label}}</label><textarea id="protocol-{{$key}}" name="{{$key}}">{{old($key,$protocol->$key)}}</textarea></div>@endforeach
<div><label for="protocol-remedy">Termin usunięcia usterek</label><input id="protocol-remedy" type="date" name="remedy_deadline" value="{{old('remedy_deadline',$protocol->remedy_deadline?->toDateString())}}"></div>
<div><label for="protocol-receiver">Przedstawiciel odbierającego *</label><input id="protocol-receiver" name="receiver_name" value="{{old('receiver_name',$protocol->receiver_name ?? auth()->user()->name)}}" required></div>
<div><label for="protocol-representative">Przedstawiciel dostawcy *</label><input id="protocol-representative" name="supplier_representative" value="{{old('supplier_representative',$protocol->supplier_representative)}}" required></div>
</div>
<p><label><input type="checkbox" name="use_signature" value="1" @checked(old('use_signature'))> Dołącz mój zapisany podpis do pola odbierającego</label></p><p class="protocol-help">Podpis dostawcy pozostaje do złożenia na wydruku. Obraz podpisu nie jest kwalifikowanym podpisem elektronicznym. Po edycji poprzedni podpis odbierającego jest usuwany — dołączenie własnego podpisu wymaga ponownego zaznaczenia. Decyzja o fakturowaniu nie tworzy faktury ani kosztu w rejestrze finansowym.</p>
<div class="protocol-actions"><a href="{{route('projects.show',[$project,'tab'=>'protocols'])}}">Anuluj</a><button>Zapisz protokół</button></div></form></div>
<template id="protocol-row-template">@include('projects.protocols.item-row',['i'=>'__INDEX__','item'=>['name'=>'','quantity'=>1,'unit'=>'szt.','price'=>0,'vat'=>'23']])</template>
<script>
let protocolItemIndex = Math.max(-1,...Array.from(document.querySelectorAll('#protocol-items input[name]'),el=>Number(el.name.match(/^items\[(\d+)\]/)?.[1] ?? -1)))+1;
document.getElementById('protocol-add-item').addEventListener('click',()=>{if(document.querySelectorAll('#protocol-items tbody tr').length>=100)return;document.querySelector('#protocol-items tbody').insertAdjacentHTML('beforeend',document.getElementById('protocol-row-template').innerHTML.replaceAll('__INDEX__',protocolItemIndex++));});
document.getElementById('protocol-items').addEventListener('click',event=>{if(event.target.closest('[data-remove-protocol-item]'))event.target.closest('tr').remove();});
function updateProtocolTotals(){let net=0,vat=0;document.querySelectorAll('#protocol-items tbody tr').forEach(row=>{const read=key=>Number(row.querySelector(`[name$="[${key}]"]`)?.value)||0;const amount=Math.round(Math.round(read('quantity')*100)*Math.round(read('price')*100)/100);net+=amount;vat+=Math.round(amount*read('vat')/100);});const money=value=>(value/100).toLocaleString('pl-PL',{style:'currency',currency:'PLN'});document.getElementById('protocol-totals').textContent=`Netto: ${money(net)} · VAT: ${money(vat)} · Brutto: ${money(net+vat)}`;}
document.getElementById('protocol-items').addEventListener('input',updateProtocolTotals);
document.getElementById('protocol-items').addEventListener('click',updateProtocolTotals);
document.getElementById('protocol-add-item').addEventListener('click',updateProtocolTotals);
updateProtocolTotals();
</script>
@endsection
