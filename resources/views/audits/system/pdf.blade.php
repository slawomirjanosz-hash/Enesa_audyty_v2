<!doctype html><html lang="pl"><head><meta charset="utf-8"><style>@page{margin:32px 38px 44px}body{font-family:DejaVu Sans,sans-serif;font-size:10px;line-height:1.5;color:#243a33}h1{font-size:19px;color:#1a4d3a}h2{font-size:14px;background:#edf3ef;padding:8px;page-break-after:avoid}h3{font-size:11px;page-break-after:avoid}p{white-space:pre-wrap;overflow-wrap:break-word}.header{border-bottom:3px solid #1a4d3a}.header img{max-height:50px;max-width:150px}.footer{position:fixed;bottom:-25px;font-size:8px}.page:after{content:counter(page)}.code{font-size:8px;color:#64736b}.missing{color:#b42318}.answer{margin:0 0 12px}.approval{border-top:1px solid #dce4df;padding:10px 0}</style></head><body>
<div class="footer">{{str_replace('-','.',$section)}} ISO 50001 · <span class="page"></span></div>
<div class="header">@if($review->issuer['logo']??null)<img src="{{$review->issuer['logo']}}" alt="Logo">@else<strong>{{$review->issuer['name']??''}}</strong>@endif<h1>{{$title}}</h1><p>{{$profile->answers['organization.name']['value']??''}} · {{$profile->answers['site.name']['value']??''}}<br>{{$profile->answers['site.address']['value']??''}}</p></div>
@if($section==='4-4')<h2>Zakres systemu - odwołanie do 4.3</h2><p>{{data_get($sources,'scope.ZAKRES.opis')}}</p>@endif
@foreach($sections as $group)<h2>{{$group['title']}}</h2>
@foreach($group['fields'] as $field)@if($service->visible($field,$review->answers))
@php($value=data_get($review->answers,$field['key']))
@php($display=filled($value)?(is_array($value)?implode(', ',array_map(fn($v)=>$field['options'][$v]??$v,$value)):($field['options'][$value]??$value)):($field['required']?'Do uzupełnienia':'Nie podano / nie dotyczy'))
@foreach(mb_str_split((string)$display,1000) as $chunk=>$text)<div style="page-break-inside:avoid">@if($chunk===0)<h3>{{$field['label']}}</h3><span class="code">{{$field['key']}}</span>@endif<p class="answer @if(!filled($value)&&$field['required']) missing @endif">{{$text}}</p></div>@endforeach
@endif @endforeach
@if(($group['repeater']??'')==='POZA' && empty($review->answers['POZA']))<p>Nie wskazano działalności poza granicami.</p>@endif
@endforeach
@if($section==='4-4')<h2>7. Lista kontrolna rozdziału 4</h2><p>N - wymaganie normy według materiału; M - metodyka. Zapis odpowiedzi i zatwierdzenie dokumentu nie zastępują dowodów działania systemu.</p>
@foreach($service->schema($section)['lista_kontrolna'] as $i=>$item)<h3>{{$item['punkt']}} · {{$item['podstawa']}} · {{$item['tekst']}}</h3><p>@if($item['auto']==='klauzula'){{($sources['published'][str_replace('.','-',$item['klauzula'])]??false)?'Aktualny dokument zatwierdzony - dowody w punkcie.':'Brak aktualnego dokumentu.'}}@elseif($item['auto']!==null){{$service->checklistState($item['auto'],$review->answers)}}@else{{['tak'=>'Potwierdzono','nie'=>'Nie spełniono','nie_dotyczy'=>'Nie dotyczy'][data_get($review->answers,"CHECK.$i.stan")]??'Do uzupełnienia'}}<br>{{data_get($review->answers,"CHECK.$i.uwagi")}}@endif</p>@endforeach @endif
@foreach($service->warnings($section,$review->answers,$sources) as $warning)<p>{{$warning}}</p>@endforeach
<h2>Zatwierdzenia i historia</h2>@foreach(['client_approval'=>'Klient','auditor_approval'=>'Audytor'] as $field=>$label)<p class="approval">{{$label}}: {{$review->$field['name']}} · {{\Carbon\Carbon::parse($review->$field['at'])->format('d.m.Y H:i')}}</p>@endforeach
<p>Rewizja zapisu: {{$review->lock_version}}. Zmiany i zatwierdzenia są rejestrowane w historii tej samej ankiety.</p>
</body></html>
