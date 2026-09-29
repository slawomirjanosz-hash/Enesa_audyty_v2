<link rel="stylesheet" href="{{asset('css/warehouse.css')}}">
@php($warehouseFull = auth()->user()->hasRole('superadmin') || auth()->user()->can('system.full_access'))
<div class="wh-head"><div><span class="wh-eyebrow">EWIDENCJA MATERIAŁÓW</span><h1>Magazyn</h1><p>Katalog, przyjęcia, wydania i pełna historia stanów.</p></div><nav class="wh-actions" aria-label="Magazyn">
<a class="wh-btn" href="{{route('warehouse.index')}}">Katalog i stany</a>
<a class="wh-btn" href="{{route('warehouse.documents.index')}}">Dokumenty i historia</a>
@foreach(['receipt'=>'receive','issue'=>'issue','adjustment'=>'adjust'] as $whType=>$ability)
@if($warehouseFull || auth()->user()->can('warehouse.'.$ability))<a class="wh-btn {{$whType==='receipt'?'primary':''}}" href="{{route('warehouse.documents.create',$whType)}}">{{['receipt'=>'+ Przyjęcie','issue'=>'− Wydanie','adjustment'=>'Inwentaryzacja'][$whType]}}</a>@endif
@endforeach
</nav></div>
@if(session('success'))<div class="wh-notice" role="status">{{session('success')}}</div>@endif
@if($errors->any())<div class="wh-errors" role="alert"><strong>Sprawdź dane — nic nie zostało zapisane.</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
