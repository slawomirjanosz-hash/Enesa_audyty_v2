@extends('layouts.app')
@section('page-title','Magazyn — pozycja')
@section('content')
@include('warehouse._layout')
<div class="wh-card"><h2>{{$item->exists?'Edytuj pozycję':'Nowa pozycja magazynowa'}}</h2>
<form method="POST" action="{{$item->exists?route('warehouse.items.update',$item):route('warehouse.items.store')}}">@csrf @if($item->exists)@method('PUT')<input type="hidden" name="revision" value="{{old('revision',$item->revision)}}">@endif
<div class="wh-grid">
@foreach(['sku'=>'Kod / indeks *','name'=>'Nazwa *','category'=>'Kategoria','location'=>'Lokalizacja (regał / półka)'] as $key=>$label)<div class="wh-field"><label for="wh-{{$key}}">{{$label}}</label><input id="wh-{{$key}}" name="{{$key}}" value="{{old($key,$item->$key)}}" maxlength="{{['sku'=>80,'name'=>200,'category'=>100,'location'=>100][$key]}}" @required(in_array($key,['sku','name'])) aria-invalid="{{$errors->has($key)?'true':'false'}}"></div>@endforeach
<div class="wh-field"><label for="wh-unit">Jednostka *</label><select name="unit" id="wh-unit" aria-invalid="{{$errors->has('unit')?'true':'false'}}">@foreach(['szt.','kpl.','m','m²','m³','kg','l','opak.'] as $unit)<option @selected(old('unit',$item->unit)===$unit)>{{$unit}}</option>@endforeach</select></div>
<div class="wh-field"><label for="wh-min">Stan minimalny *</label><input id="wh-min" type="number" name="minimum_stock" min="0" max="1000000" step="0.001" required value="{{old('minimum_stock',$item->minimum_stock ?? 0)}}" aria-invalid="{{$errors->has('minimum_stock')?'true':'false'}}"></div>
<div class="wh-field wh-wide"><label for="wh-description">Opis</label><textarea id="wh-description" name="description" rows="3" maxlength="5000">{{old('description',$item->description)}}</textarea></div></div>
<div class="wh-info">Stan początkowy wynosi zero. Towar wprowadź dokumentem „Przyjęcie”. Zmiany nazw nie zmieniają wcześniej zapisanych dokumentów.</div><div class="wh-actions"><button class="wh-btn primary">Zapisz pozycję</button><a class="wh-btn" href="{{route('warehouse.index')}}">Anuluj</a></div></form></div>
@endsection
