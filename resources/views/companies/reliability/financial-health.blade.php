@php($tones = ['clear'=>'#e8f5ec','warning'=>'#fff4d6','risk'=>'#fde7e7','unknown'=>'#eef0f2'])
<div style="padding:12px;background:{{$tones[$health['state']]}};border-radius:8px;margin:12px 0">
<strong>Ocena finansowa{{$health['year'] ? ' za '.$health['year'].' r.' : ''}}: {{$health['label']}}</strong>
<p style="margin:5px 0">Dotyczy sprawozdania, nie wyniku KRZ ani wszystkich długów. OK oznacza brak sygnałów według poniższych reguł, a nie gwarancję wypłacalności.</p>
</div>
@foreach($health['periods'] as $period)
<div style="padding:10px;margin:8px 0;background:{{$tones[$period['state']]}};page-break-inside:avoid">
<strong>{{$period['year']}} — {{$period['label']}}</strong>
<ul style="margin:5px 0;padding-left:18px">@foreach($period['findings'] as $finding)<li>{{$finding['message']}}</li>@endforeach</ul>
</div>
@endforeach
<p class="rel-muted muted" style="font-size:0.9em">{{$health['rules']}}</p>
