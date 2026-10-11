@if($node['type']==='list')
<div data-exec-list="{{$path}}" data-min="{{app(\App\Services\IsoExecutionQuestionnaire::class)->listMinimum($section,$path,$answers,$node)}}" data-token="__ROW{{$depth}}__">
@error('answers.'.$path)<p class="plant-notice error">{{$message}}</p>@enderror
@foreach(data_get($answers,$path,[]) as $index=>$entry)
<div class="system-row" data-exec-row>
<h3>{{$node['label']}} · {{$loop->iteration}}</h3>
@foreach($node['children'] as $key=>$child)
@include('audits.system.execution-tree', ['node'=>$child,'path'=>$path.'.'.$index.'.'.$key,'depth'=>$depth+1])
@endforeach
@unless($node['fixed']??false)<button type="button" data-exec-remove title="Usuń pozycję" aria-label="Usuń pozycję">&#128465;</button>@endunless
</div>
@endforeach
@unless($node['fixed']??false)
<template data-exec-template><div class="system-row" data-exec-row><h3>{{$node['label']}}</h3>
@foreach($node['children'] as $key=>$child)
@include('audits.system.execution-tree', ['node'=>$child,'path'=>$path.'.__ROW'.$depth.'__.'.$key,'depth'=>$depth+1])
@endforeach
<button type="button" data-exec-remove title="Usuń pozycję" aria-label="Usuń pozycję">&#128465;</button></div></template>
<button type="button" data-exec-add>+ Dodaj: {{$node['label']}}</button>
@else
<p class="plant-help">Pozycje pochodzą z punktów źródłowych i są aktualizowane automatycznie.</p>
@endunless
</div>
@elseif($node['type']==='object')
<div class="system-row"><h3>{{$node['label']}}</h3>
@foreach($node['children'] as $key=>$child)
@include('audits.system.execution-tree', ['node'=>$child,'path'=>$path.'.'.$key,'depth'=>$depth])
@endforeach</div>
@else
@include('audits.system.field',['field'=>app(\App\Services\IsoExecutionQuestionnaire::class)->field($section,$path,$node,$answers)])
@endif
