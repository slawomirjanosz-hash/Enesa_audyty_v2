@extends('layouts.app')
@section('page-title', $order->number)
@include('offers.partials.editor-styles')
@section('content')
<div style="max-width:1250px;margin:auto">
<div id="editor-topbar">
    <div class="etb-left"><a class="btn-secondary" href="{{route('projects.show',[$project,'tab'=>'work-orders'])}}"><i class="ti ti-arrow-left"></i> Zlecenia projektu</a><strong>{{$order->number}}</strong></div>
    <div class="etb-right" style="flex-wrap:wrap">
        <a class="btn-secondary" target="_blank" rel="noopener" href="{{route('projects.work-orders.document',[$project,$order])}}">Podgląd PDF</a>
        <a class="btn-secondary" href="{{route('projects.work-orders.document',[$project,$order,'download'=>1])}}">Pobierz PDF</a>
        <a class="btn-secondary" href="{{route('projects.work-orders.document',[$project,$order,'format'=>'docx'])}}">Word</a>
        @if($canManage)
            @if($order->status==='draft')<a class="btn-primary" href="{{route('projects.work-orders.edit',[$project,$order])}}">Edytuj</a>@endif
            <a class="btn-secondary" href="{{route('projects.work-orders.create',[$project,'source_id'=>$order->id])}}">Kopiuj</a>
        @endif
    </div>
</div>
@if(session('success'))<p class="ed-card ed-card-body" role="status">{{session('success')}}</p>@endif
@if($errors->any())<div class="ed-card ed-card-body" role="alert" style="color:#991b1b">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
<div class="ed-card">
<div class="doc-header-bar"><strong>{{$order->is_template?'WZÓR ZLECENIA':\App\Models\ProjectWorkOrder::STATUSES[$order->status]}}</strong><span>Data: {{$order->issued_on?->format('d.m.Y') ?? '—'}}</span></div>
<div class="doc-title-wrap"><h1>{{$order->title}}</h1></div>
<div class="doc-parties">
    <div class="doc-party"><div class="field-label">Zamawiający</div><strong>{{$order->issuer_snapshot['name']}}</strong><p>{{$order->our_contact}}</p></div>
    <div class="doc-party"><div class="field-label">Wykonawca</div><strong>{{$order->supplier_snapshot['name'] ?? 'Nie wybrano'}}</strong><p>{{$order->supplier_contact}}</p></div>
</div>
<div class="ed-card-body">
    <p><strong>Projekt:</strong> {{$order->project_snapshot['number']}} · {{$order->project_snapshot['name']}}</p>
    <p><strong>Oferta wykonawcy:</strong> {{$order->offer_reference ?: '—'}} {{$order->offer_date?->format('d.m.Y')}}</p>
    @if($order->stage_ids)<p><strong>Etapy harmonogramu:</strong> {{$project->tasks()->whereIn('id',$order->stage_ids)->pluck('title')->implode(', ')}}</p>@endif
    @if($order->confirmation_reference)<p><strong>Potwierdzenie wykonawcy:</strong> {{$order->confirmation_reference}} ({{$order->confirmed_at?->format('d.m.Y H:i')}})</p>@endif
    <p><strong>Netto:</strong> {{number_format($order->net_cents/100,2,',',' ')}} zł · <strong>VAT {{$order->vat_rate}}%:</strong> {{number_format($order->vat_cents/100,2,',',' ')}} zł · <strong>Brutto:</strong> {{number_format(($order->net_cents+$order->vat_cents)/100,2,',',' ')}} zł</p>
    <p><strong>Zaliczka:</strong> {{number_format($order->advance_cents/100,2,',',' ')}} zł</p>
    @if($order->attachment_snapshot)<p><strong>Załączniki:</strong> {{collect($order->attachment_snapshot)->pluck('name')->implode(', ')}}</p>@endif
    @if($order->notes)<p><strong>Notatki wewnętrzne:</strong> {{$order->notes}}</p>@endif
</div>
</div>
@if($canManage && !$order->is_template)
<div class="ed-card ed-card-body">
    <h2>Status realizacji</h2>
    <form method="POST" action="{{route('projects.work-orders.transition',[$project,$order])}}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">@csrf @method('PATCH')
        <input type="hidden" name="revision" value="{{$order->revision}}">
        @if(\App\Models\ProjectWorkOrder::TRANSITIONS[$order->status])
        <div><label class="field-label" for="wo-status">Nowy status</label><select id="wo-status" name="status" class="field-input">@foreach(\App\Models\ProjectWorkOrder::TRANSITIONS[$order->status] as $status)<option value="{{$status}}">{{\App\Models\ProjectWorkOrder::STATUSES[$status]}}</option>@endforeach</select></div>
        <div><label class="field-label" for="wo-confirmation">Źródło potwierdzenia wykonawcy (przy potwierdzaniu)</label><input id="wo-confirmation" class="field-input" name="confirmation_reference" maxlength="2000" placeholder="Np. e-mail wykonawcy z dnia…"></div>
        <button class="btn-primary">Zapisz status</button>
        @else<p>{{\App\Models\ProjectWorkOrder::STATUSES[$order->status]}}</p>@endif
    </form>
</div>
@endif
@if($canManage && $order->status==='draft')
<form method="POST" action="{{route('projects.work-orders.destroy',[$project,$order])}}" onsubmit="return confirm('Usunąć robocze zlecenie?')">@csrf @method('DELETE')<button class="btn-secondary">Usuń robocze zlecenie</button></form>
@endif
@if(!$order->is_template && in_array($order->status,['issued','confirmed','in_progress','received']) && (app(\App\Services\AuditorAccessService::class)->hasFullAccess(auth()->user()) || auth()->user()->can('projects.protocols.manage')))
<p style="margin:16px 0"><a class="btn-primary" href="{{route('projects.protocols.create',[$project,'work_order_id'=>$order->id])}}">Utwórz protokół odbioru od wykonawcy</a></p>
@endif
<details class="ed-card ed-card-body"><summary>Historia zlecenia</summary><ul>@foreach($order->history as $entry)<li>{{$entry['at']}} · {{$entry['user']}} · {{\App\Models\ProjectWorkOrder::STATUSES[$entry['status']]}} @if($entry['action']==='save')— zapis treści @endif</li>@endforeach</ul></details>
@foreach($order->text_sections as $section)
    @if(trim(strip_tags($section['content']))!=='')<div class="ed-card"><div class="ed-card-header"><h2>{{$section['name']}}</h2></div><div class="ed-card-body ql-editor">{!! app(\App\Services\OfferContentService::class)->cleanHtml($section['content']) !!}</div></div>@endif
@endforeach
@foreach($order->price_sections as $section)
<div class="ed-card"><div class="ed-card-header"><h2>{{$section['name']}}</h2></div><div class="ed-card-body" style="overflow:auto"><table style="width:100%"><thead><tr><th>Opis</th><th>Ilość</th><th>Jednostka</th><th>Cena netto</th><th>Wartość netto</th></tr></thead><tbody>
@foreach($section['rows'] as $row)<tr><td>{{$row['opis']}}</td><td data-sort-value="{{$row['ilosc']}}">{{$row['ilosc']}}</td><td>{{$row['jedn']}}</td><td data-sort-value="{{$row['cena_jedn']}}">{{number_format($row['cena_jedn'],2,',',' ')}} zł</td><td data-sort-value="{{$row['net_cents']}}">{{number_format($row['net_cents']/100,2,',',' ')}} zł</td></tr>@endforeach
</tbody><tfoot><tr><th colspan="4">Suma sekcji</th><td>{{number_format(collect($section['rows'])->sum('net_cents')/100,2,',',' ')}} zł</td></tr></tfoot></table></div></div>
@endforeach
</div>
@endsection
