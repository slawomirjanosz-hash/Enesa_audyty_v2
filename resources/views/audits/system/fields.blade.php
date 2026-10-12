<fieldset @disabled(!$editable)>
@if(!$client && $editable)<button type="button" id="system-example">Wypełnij przykładowo puste odpowiedzi klienta</button><small class="plant-help">Dane testowe pojawią się w formularzu. Nie są zapisywane ani zatwierdzane automatycznie.</small>@endif
@foreach($sections as $id=>$group)
<section id="{{$id}}" class="plant-card"><h2>{{$group['title']}}</h2>
@if(isset($group['tree']))
@include('audits.system.execution-tree', ['node'=>$group['tree'], 'path'=>$group['root'], 'depth'=>0])
@elseif(isset($group['repeater']))
@php($root=$group['repeater'])
@if($section==='5-3' && $root==='ROLE')<h3>Podgląd zapisanej matrycy</h3>@include('audits.system.matrix')<p class="plant-help">Poniżej edytuj role i ich udział w zadaniach. Podgląd tabeli odświeża się po zapisie.</p>@endif
@error('answers.'.$root)<p class="plant-notice error">{{$message}}</p>@enderror
@if($section==='5-3' && $root==='ROLE')<p class="plant-help">Z — zatwierdza · O — odpowiada · W — współpracuje · I — informowany. Każde zadanie wymaga O; przy kilku O wyjaśnij podział w kolejnej sekcji.</p>@endif
<div data-repeater="{{$root}}">@foreach(($answers[$root]??(in_array($root,['GRANICE','UPRAW'])?[[]]:[])) as $i=>$row)<div class="system-row" @if($section==='7-3' && $root==='SPRAWDZENIA' && isset($review->answers['SPRAWDZENIA'][$i])) data-history-row @endif>@foreach($service->rowFields($root,(string)$i,$section,$answers,$sources) as $field)@include('audits.system.field')@endforeach<button type="button" @disabled(($section==='6-5' && $root==='KARTY') || ($section==='7-3' && $root==='SPRAWDZENIA' && isset($review->answers['SPRAWDZENIA'][$i]))) data-remove-row>Usuń pozycję</button></div>@endforeach</div>
<template id="template-{{$root}}"><div class="system-row">@foreach($service->rowFields($root,'__INDEX__',$section,$answers,$sources) as $field)@include('audits.system.field')@endforeach<button type="button" @disabled($section==='6-5' && $root==='KARTY') data-remove-row>Usuń pozycję</button></div></template><button type="button" @disabled($section==='6-5' && $root==='KARTY') data-add-row="{{$root}}">+ Dodaj pozycję</button>
@if($root==='POZA')<p class="plant-help">Brak pozycji oznacza, że nie wskazano działalności poza granicami. Udział procentowy nie zastępuje uzasadnienia.</p>@endif
@else
@if($id==='energia')<div data-energy-locked class="plant-notice" hidden>Najpierw opisz przynajmniej jedną lokalizację, adres i granicę fizyczną w sekcji 3.</div><div data-energy-fields>@endif
@foreach($group['fields'] as $field)@include('audits.system.field')@endforeach
@if($id==='energia')</div>@endif
@endif
</section>@endforeach
@if($section==='4-4')<section id="checklist" class="plant-card"><h2>7. Kompletność rozdziału 4</h2><p>Wypełniona ankieta nie jest dowodem spełnienia normy. N — wymaganie normy według materiału; M — metodyka. Zatwierdzony dokument wskazuje stan pracy, nie zastępuje dowodów.</p>
@foreach($service->schema($section)['lista_kontrolna'] as $i=>$item)<article class="plant-question"><strong>{{$item['punkt']}} · {{$item['podstawa']}} · {{$item['tekst']}}</strong>
@if($item['auto']==='klauzula')<p class="system-status">{{($sources['published'][str_replace('.','-',$item['klauzula'])]??false)?'Aktualny dokument zatwierdzony — sprawdź dowody w punkcie.':'Brak aktualnego dokumentu źródłowego.'}}</p>
@elseif($item['auto']!==null)<p class="plant-help">Źródło oceny: {{$item['auto']}}. {{ $service->checklistState($item['auto'],$answers) }}</p>
@elseif(!$client)<label>Ocena konsultanta<select name="answers[CHECK][{{$i}}][stan]"><option value="">Wybierz</option>@foreach(['tak'=>'Potwierdzono','nie'=>'Nie spełniono','nie_dotyczy'=>'Nie dotyczy'] as $val=>$label)<option value="{{$val}}" @selected(data_get($answers,"CHECK.$i.stan")===$val)>{{$label}}</option>@endforeach</select></label><label>Dowód / uzasadnienie<textarea name="answers[CHECK][{{$i}}][uwagi]" maxlength="3000">{{data_get($answers,"CHECK.$i.uwagi")}}</textarea></label>
@else<p>{{['tak'=>'Potwierdzono','nie'=>'Nie spełniono','nie_dotyczy'=>'Nie dotyczy'][data_get($answers,"CHECK.$i.stan")]??'Oczekuje na ocenę konsultanta'}} · {{data_get($answers,"CHECK.$i.uwagi")}}</p>@endif</article>@endforeach</section>@endif
</fieldset>
