@extends('layouts.app')
@section('page-title', $order->exists ? 'Edytuj zlecenie' : 'Nowe zlecenie')
@include('offers.partials.editor-styles')
@push('styles')
<style>
.wo-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.wo-grid>div{min-width:0}.wo-grid .field-input{box-sizing:border-box;width:100%}.wo-wide{grid-column:1/-1}.wo-layout{max-width:1250px;margin:auto}.wo-price{min-width:680px}.wo-price input{width:100%;box-sizing:border-box}.wo-section-actions{display:flex;gap:6px;flex-wrap:wrap}.wo-section-actions button{padding:5px 8px}.wo-text-editor{min-height:130px}.wo-error{padding:14px;background:#fef2f2;color:#991b1b;margin:14px 0;border-radius:8px}.wo-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.wo-checks{max-height:180px;overflow:auto;display:grid;gap:6px}.wo-checks label{display:flex;gap:7px;align-items:center}
@media(max-width:700px){.wo-grid{grid-template-columns:1fr}.wo-wide{grid-column:auto}.etb-left,.etb-right{flex-wrap:wrap}#editor-topbar{position:static;flex-wrap:wrap}.wo-layout .ed-card-header{flex-wrap:wrap}}
</style>
@endpush
@section('content')
<div class="wo-layout">
<div id="editor-topbar">
    <div class="etb-left"><a class="btn-secondary" href="{{route('projects.show',[$project,'tab'=>'work-orders'])}}"><i class="ti ti-arrow-left"></i> Zlecenia projektu</a><strong>{{$order->exists ? $order->number : 'Nowe zlecenie'}}</strong><span class="badge">Robocze</span></div>
    <div class="etb-right"><button class="btn-primary" type="submit" form="work-order-form"><i class="ti ti-device-floppy"></i> Zapisz zlecenie</button></div>
</div>
@if($errors->any())<div class="wo-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@if(!$order->exists && $templates->isNotEmpty())
<form method="GET" class="ed-card ed-card-body wo-actions" action="{{route('projects.work-orders.create',$project)}}">
    <label for="wo-template">Utwórz ze wzoru</label><select id="wo-template" class="field-input" name="source_id">@foreach($templates as $template)<option value="{{$template->id}}">{{$template->title}}</option>@endforeach</select><button class="btn-secondary">Wczytaj wzór</button>
</form>
@endif
<form id="work-order-form" method="POST" action="{{$order->exists ? route('projects.work-orders.update',[$project,$order]) : route('projects.work-orders.store',$project)}}">
@csrf @if($order->exists)@method('PUT')@endif
<input type="hidden" name="revision" value="{{old('revision',$order->revision ?? 0)}}">
<input type="hidden" name="text_sections" id="wo-text-json">
<input type="hidden" name="price_sections" id="wo-price-json">
<div class="ed-card">
    <div class="doc-header-bar"><span class="offer-num">ZLECENIE REALIZACJI PRAC</span><span>{{$project->number}} · {{$project->name}}</span></div>
    <div class="doc-parties">
        <div class="doc-party"><div class="field-label">Zamawiający</div><strong>{{$companySettings?->name ?? config('app.name')}}</strong><div>{{$companySettings?->address}} · {{$companySettings?->city}}</div></div>
        <div class="doc-party"><label class="field-label" for="wo-supplier">Wykonawca</label><select id="wo-supplier" name="supplier_company_id" class="field-input"><option value="">Wybierz dostawcę</option>@foreach($suppliers as $company)<option value="{{$company->id}}" @selected(old('supplier_company_id',$order->supplier_company_id)==$company->id)>{{$company->name}} · {{$company->nip}}</option>@endforeach</select></div>
    </div>
    <div class="doc-title-wrap"><label class="field-label" for="wo-title">Tytuł zlecenia *</label><input id="wo-title" class="doc-title-input" name="title" maxlength="255" required value="{{old('title',$order->title)}}"></div>
    <div class="ed-card-body wo-grid">
        @foreach(['issued_on'=>['Data zlecenia','date'],'place'=>['Miejsce realizacji / wystawienia','text'],'offer_reference'=>['Numer oferty wykonawcy','text'],'offer_date'=>['Data oferty wykonawcy','date'],'our_contact'=>['Kontakt zamawiającego','text'],'supplier_contact'=>['Kontakt wykonawcy (osoba, e-mail, telefon)','text']] as $field=>[$label,$type])
        <div><label class="field-label" for="wo-{{$field}}">{{$label}}</label><input id="wo-{{$field}}" class="field-input" name="{{$field}}" type="{{$type}}" @if($type==='text') maxlength="{{$field==='supplier_contact'?1000:255}}" @endif value="{{old($field,$type==='date'?$order->$field?->format('Y-m-d'):$order->$field)}}"></div>
        @endforeach
        <div class="wo-wide"><label class="field-label">Powiązane etapy harmonogramu</label><div class="wo-checks">@forelse($stages as $stage)<label><input type="checkbox" name="stage_ids[]" value="{{$stage->id}}" @checked(in_array($stage->id,(array)old('stage_ids',$order->stage_ids ?? [])))> {{$stage->title}}</label>@empty<span>Brak etapów harmonogramu.</span>@endforelse</div></div>
        @if($documents->isNotEmpty())
        <div class="wo-wide"><label class="field-label">Załączniki z dokumentów projektu</label><div class="wo-checks">@foreach($documents as $document)<label><input type="checkbox" name="document_ids[]" value="{{$document->id}}" @checked(in_array($document->id,(array)old('document_ids',$order->document_ids ?? [])))> {{$document->original_filename}}</label>@endforeach</div></div>
        @endif
        <div class="wo-wide"><label><input type="checkbox" name="is_template" value="1" @checked(old('is_template',$order->is_template))> Zapisz jako wzór zlecenia dla tego projektu</label></div>
    </div>
</div>
<div id="wo-text-sections"></div>
<p style="margin-bottom:16px"><button class="btn-add-section" type="button" data-add-text><i class="ti ti-file-text"></i> Dodaj sekcję opisową</button></p>
<div id="wo-price-sections"></div>
<p style="margin-bottom:16px"><button class="btn-add-section" type="button" data-add-price><i class="ti ti-calculator"></i> Dodaj sekcję wynagrodzenia</button></p>
<div class="ed-card">
    <div class="ed-card-header"><i class="ti ti-report-money"></i><strong>Wynagrodzenie i płatności</strong></div>
    <div class="ed-card-body wo-grid">
        <div><label for="wo-vat" class="field-label">VAT</label><select name="vat_rate" id="wo-vat" class="field-input">@foreach([0,5,8,23] as $rate)<option value="{{$rate}}" @selected((int)old('vat_rate',$order->vat_rate)===$rate)>{{$rate}}%</option>@endforeach</select></div>
        <div><label for="wo-advance" class="field-label">Zaliczka w PLN</label><input class="field-input" id="wo-advance" name="advance" type="number" min="0" max="999999999.99" step="0.01" value="{{old('advance',($order->advance_cents ?? 0)/100)}}"></div>
        <p class="wo-wide" id="wo-totals" aria-live="polite"></p>
        <div class="wo-wide"><label class="field-label" for="wo-notes">Notatki wewnętrzne (niewidoczne na dokumencie)</label><textarea id="wo-notes" class="field-input" name="notes" rows="3" maxlength="10000">{{old('notes',$order->notes)}}</textarea></div>
    </div>
</div>
<div class="wo-actions"><button class="btn-primary">Zapisz zlecenie</button><a class="btn-secondary" href="{{route('projects.show',[$project,'tab'=>'work-orders'])}}">Anuluj</a></div>
</form>
</div>
@php($textData = old('text_sections', $order->text_sections ?? []))
@php($priceData = old('price_sections', $order->price_sections ?? []))
@php($editorData = ['text'=>is_string($textData)?json_decode($textData,true):$textData,'price'=>is_string($priceData)?json_decode($priceData,true):$priceData])
<script type="application/json" id="wo-editor-data">@json($editorData)</script>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script src="{{asset('js/offer-section-collapse.js')}}"></script>
<script type="module" src="{{asset('js/project-work-order-editor.js')}}?v={{filemtime(public_path('js/project-work-order-editor.js'))}}"></script>
@endpush
