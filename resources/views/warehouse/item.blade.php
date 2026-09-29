@extends('layouts.app')
@section('page-title','Magazyn — '.$item->name)
@section('content')
@include('warehouse._layout')
@php($warehouseFull = auth()->user()->hasRole('superadmin') || auth()->user()->can('system.full_access'))
<div class="wh-card"><span class="wh-eyebrow">{{$item->sku}}</span><h2>{{$item->name}} @unless($item->is_active)<span class="wh-badge low">Archiwum</span>@endunless</h2>
<dl class="wh-details">@foreach(['category'=>'Kategoria','location'=>'Lokalizacja','quantity'=>'Stan','unit'=>'Jednostka','minimum_stock'=>'Minimum','unit_cost'=>'Cena ewidencyjna netto PLN'] as $key=>$label)<div><dt>{{$label}}</dt><dd>{{$item->$key ?? '—'}}</dd></div>@endforeach</dl><p class="wh-description">{{$item->description}}</p>
<div class="wh-actions">@if($warehouseFull || auth()->user()->can('warehouse.manage'))<a class="wh-btn" href="{{route('warehouse.items.edit',$item)}}">Edytuj</a>@endif<a class="wh-btn" href="{{route('warehouse.documents.index',['item'=>$item->id])}}">Historia tej pozycji</a>
@if($item->is_active)@foreach(['receipt'=>'receive','issue'=>'issue','adjustment'=>'adjust'] as $type=>$ability)@if($warehouseFull || auth()->user()->can('warehouse.'.$ability))<a class="wh-btn" href="{{route('warehouse.documents.create',['type'=>$type,'item'=>$item->id])}}">{{\App\Models\WarehouseDocument::TYPES[$type]}}</a>@endif@endforeach @endif</div>
@if($warehouseFull || auth()->user()->can('warehouse.manage'))<form method="POST" action="{{route('warehouse.items.archive',$item)}}" style="margin-top:20px" onsubmit="return confirm('Zmienić dostępność tej pozycji? Historia pozostanie zachowana.')">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{$item->is_active?0:1}}"><button class="wh-btn danger">{{$item->is_active?'Archiwizuj pustą pozycję':'Przywróć do katalogu'}}</button></form>@endif</div>
@endsection
