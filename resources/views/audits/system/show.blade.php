<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{$title}}</title><link rel="stylesheet" href="{{asset('css/iso-plant-profile.css')}}"><style>:root{--brand:{{$appBrand?->primaryColor()?:'#1a4d3a'}}}.system-changed{background:#eaf2ff;border-left:4px solid #4575bb;padding:14px}.system-row{border:1px solid #dce4df;border-radius:9px;padding:16px;margin:16px 0}.system-status{padding:12px;background:#edf3ef;border-radius:8px}</style></head><body>
<header class="plant-header"><div><h1>{{$title}}</h1><span>{{$audit->company->name}} · {{$profile->answers['site.name']['value']??'Zakład'}} · {{$client?'Strefa klienta':'Panel audytora'}}</span></div><a href="{{route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>$section])}}">← Wróć do audytu</a></header>
<main class="plant-main">
@if(session('success'))<div class="plant-notice success">{{session('success')}}</div>@endif
@if($errors->any())<div class="plant-notice error" role="alert"><strong>Sprawdź oznaczone pola:</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@if($stale)<div class="plant-notice">Dane źródłowe zmieniły się. Sprawdź ankietę i zatwierdź ją ponownie. Dotychczasowe zatwierdzenia nie są aktualne.</div>@endif
@if(!$sources['ready'])<div class="plant-notice">Możesz zapisywać odpowiedzi. Przed zatwierdzeniem {{$section==='4-4'?'zatwierdź i wygeneruj aktualne dokumenty 4.1, 4.2 i 4.3.':'zatwierdź profil zakładu oraz punkty 4.1 i 4.2.'}}</div>@endif
<div class="plant-summary"><strong data-system-progress>Wypełnienie: {{$progress['percent']}}% · {{$progress['answered']}} / {{$progress['total']}}</strong><span>{{\App\Models\IsoPlantProfile::STATUSES[$stale?'editing':$review->status]}}</span></div>
@if(($client && in_array($review->status,['returned','auditor_corrected'])) || (!$client && $review->status==='submitted'))<div class="plant-notice">Do wykonania: {{$client?'sprawdź poprawki audytora i zatwierdź ankietę.':'sprawdź odpowiedzi klienta i zatwierdź ankietę.'}}</div>@endif
@if($review->review_note)<div class="plant-notice">Uwagi audytora: {{$review->review_note}}</div>@endif
<nav class="plant-nav" aria-label="Działy ankiety">@foreach($sections as $id=>$group)<a href="#{{$id}}">{{$group['title']}}</a>@endforeach @if($section==='4-4')<a href="#checklist">7. Kompletność</a>@endif<a href="#approvals">{{$section==='4-3'?'7':'8'}}. Zatwierdzenia</a></nav>
<section class="plant-card"><h2>Dane powiązane</h2><a href="{{route($client?'client.audits.plant-profile.show':'audits.plant-profile.show',[$audit,$profile])}}">Profil zakładu</a>
@foreach($sources['approved'] as $key=>$approved)<p>{{$key}}: {{$approved?'zatwierdzony':'wymaga zatwierdzenia'}} · {{$sources['published'][$key]?'dokument wygenerowany':'bez aktualnego dokumentu'}}</p>@endforeach
@if($section==='4-4')<p><strong>Zakres z 4.3:</strong> {{data_get($sources,'scope.ZAKRES.opis')?:'Jeszcze nie określono'}}</p>@endif
@foreach($service->warnings($section,$answers,$sources) as $warning)<div class="plant-notice">{{$warning}}</div>@endforeach</section>
@php($editable=$canWrite && (!$client || $stale || in_array($review->status,['editing','returned','auditor_corrected'])))
<form @if($editable) data-autosave data-draft-exit="{{route($client?'client.audits.show':'audits.show',[$audit,'tab'=>'surveys'])}}" @endif id="system-form" data-questionnaire-form method="post" action="{{route($prefix.'update',[$audit,$profile,$section])}}">@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="source_hash" value="{{$sources['hash']}}">
<fieldset @disabled(!$editable)>
@if(!$client && $editable)<button type="button" id="system-example">Wypełnij przykładowo puste odpowiedzi klienta</button><small class="plant-help">Dane testowe pojawią się w formularzu. Nie są zapisywane ani zatwierdzane automatycznie.</small>@endif
@foreach($sections as $id=>$group)
<section id="{{$id}}" class="plant-card"><h2>{{$group['title']}}</h2>
@if(isset($group['repeater']))
@php($root=$group['repeater'])
<div data-repeater="{{$root}}">@foreach(($answers[$root]??($root==='POZA'?[]:[[]])) as $i=>$row)<div class="system-row">@foreach($service->rowFields($root,(string)$i) as $field)@include('audits.system.field')@endforeach<button type="button" data-remove-row>Usuń pozycję</button></div>@endforeach</div>
<template id="template-{{$root}}"><div class="system-row">@foreach($service->rowFields($root,'__INDEX__') as $field)@include('audits.system.field')@endforeach<button type="button" data-remove-row>Usuń pozycję</button></div></template><button type="button" data-add-row="{{$root}}">+ Dodaj pozycję</button>
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
</fieldset><input type="hidden" name="complete_form" value="1">
@if($editable)<div class="plant-actions"><button class="primary" name="operation" value="save">Zapisz</button>@if($canClientApprove)<button name="operation" value="submit">Zatwierdź jako klient</button>@endif</div>@endif
</form>
<section id="approvals" class="plant-card"><h2>Zatwierdzenia i dokument</h2><p>{{$review->client_approval&&!$stale?'✓':'○'}} Zaakceptowane przez klienta · {{$review->auditor_approval&&!$stale?'✓':'○'}} Zaakceptowane przez audytora · {{$review->document_id&&!$stale?'✓':'○'}} Wygenerowano dokument</p>
@if($canWrite && !$stale && $review->status==='submitted')<form method="post" data-review-action action="{{route($prefix.'update',[$audit,$profile,$section])}}">@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="source_hash" value="{{$sources['hash']}}">@if(!$client)<button class="primary" name="operation" value="approve">Zatwierdź jako audytor</button><label>Powód zwrotu<textarea name="note"></textarea></label><button name="operation" value="return">Zwróć do uzupełnienia</button>@elseif($canClientApprove)<button name="operation" value="withdraw">Wycofaj zatwierdzenie</button>@endif</form>@endif
@if(!$stale && $sources['ready'] && $review->status==='approved')<div class="plant-inline">@foreach($canWrite?[true,false]:[true] as $preview)<form method="post" data-review-action action="{{route($prefix.'pdf',[$audit,$profile,$section])}}" @if($preview) target="_blank" @endif>@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="preview" value="{{$preview?1:0}}"><button>{{$preview?'Podgląd PDF':'Generuj i zapisz PDF'}}</button></form>@endforeach</div>@endif
<p><a href="{{route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>$section])}}#iso-documents-{{$section}}">Dokumentacja punktu — pliki, kopiowanie i usuwanie zgodnie z uprawnieniami →</a></p>
<h3>Historia zmian</h3><div class="plant-scroll"><table><thead><tr><th>Data</th><th>Osoba</th><th>Działanie</th></tr></thead><tbody>@foreach($events as $event)<tr><td data-sort-value="{{$event->created_at}}">{{\Carbon\Carbon::parse($event->created_at)->format('d.m.Y H:i')}}</td><td>{{$event->user_name}}</td><td>{{['save'=>'Zapisano','submit'=>'Klient zatwierdził','approve'=>'Audytor zatwierdził','return'=>'Zwrócono do poprawy','withdraw'=>'Wycofano zatwierdzenie'][$event->action]??$event->action}}</td></tr>@endforeach</tbody></table></div></section>
@include('partials.field-validation') @include('partials.questionnaire-navigation')
</main><script type="module" src="{{asset('js/table-sort.js')}}"></script><script src="{{asset('js/iso-system.js')}}" defer></script>@include('partials.form-drafts')
</body></html>
