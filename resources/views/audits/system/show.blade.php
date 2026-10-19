<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{$title}}</title><link rel="stylesheet" href="{{asset('css/iso-plant-profile.css')}}"><style>:root{--brand:{{$appBrand?->primaryColor()?:'#1a4d3a'}}}.system-changed{background:#eaf2ff;border-left:4px solid #4575bb;padding:14px}.system-row{border:1px solid #dce4df;border-radius:9px;padding:16px;margin:16px 0}.system-status{padding:12px;background:#edf3ef;border-radius:8px}</style></head><body>
<header class="plant-header"><div><h1>{{$title}}</h1><span>{{$audit->company->name}} · {{$profile->answers['site.name']['value']??'Zakład'}} · {{$client?'Strefa klienta':'Panel audytora'}}</span></div><a href="{{route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>$section])}}">← Wróć do audytu</a></header>
<main class="plant-main">
@include('audits.partials.iso-html-editor', ['htmlSection'=>$section])
<div hidden data-iso-html-native>
@if(\App\Services\IsoExecutionQuestionnaire::handles($section))<style>.system-row .plant-help{overflow-wrap:anywhere}.system-row{min-width:0}.system-row input[readonly]{background:#f2f5f3;color:#52665c}@media(max-width:600px){.system-row{padding:10px;margin:10px 0}.system-row .system-row{padding:8px}}</style>@endif
@if(session('success'))<div class="plant-notice success">{{session('success')}}</div>@endif
@if($errors->any())<div class="plant-notice error" role="alert"><strong>Sprawdź oznaczone pola:</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@if($stale)<div class="plant-notice">Dane źródłowe zmieniły się. Sprawdź ankietę i zatwierdź ją ponownie. Dotychczasowe zatwierdzenia nie są aktualne.</div>@endif
@if(!$sources['ready'])<div class="plant-notice">Możesz zapisywać odpowiedzi. Przed zatwierdzeniem {{preg_match('/^(?:[5-9]|10)-/',$section)?'zatwierdź aktualne punkty źródłowe wskazane poniżej.':($section==='4-4'?'zatwierdź i wygeneruj aktualne dokumenty 4.1, 4.2 i 4.3.':'zatwierdź profil zakładu oraz punkty 4.1 i 4.2.')}}</div>@endif
<div class="plant-summary"><strong data-system-progress>Wypełnienie: {{$progress['percent']}}% · {{$progress['answered']}} / {{$progress['total']}}</strong><span>{{\App\Models\IsoPlantProfile::STATUSES[$stale?'editing':$review->status]}}</span></div>
@include('audits.partials.questionnaire-status', ['statusRecord'=>$review, 'statusStale'=>$stale])
@if(($client && in_array($review->status,['returned','auditor_corrected'])) || (!$client && $review->status==='submitted'))<div class="plant-notice">Do wykonania: {{$client?'sprawdź poprawki audytora i zatwierdź ankietę.':'sprawdź odpowiedzi klienta i zatwierdź ankietę.'}}</div>@endif
@if($review->review_note)<div class="plant-notice">Uwagi audytora: {{$review->review_note}}</div>@endif
<nav class="plant-nav" aria-label="Działy ankiety">@foreach($sections as $id=>$group)<a href="#{{$id}}">{{$group['title']}}</a>@endforeach @if($section==='4-4')<a href="#checklist">7. Kompletność</a>@endif<a href="#approvals">{{$section==='4-3'?'7':'8'}}. Zatwierdzenia</a></nav>
<section class="plant-card"><h2>Dane powiązane</h2><a href="{{route($client?'client.audits.plant-profile.show':'audits.plant-profile.show',[$audit,$profile])}}">Profil zakładu</a>
@foreach($sources['approved'] as $key=>$approved)<p>{{$key}}: {{$approved?'zatwierdzony':'wymaga zatwierdzenia'}} · {{$sources['published'][$key]?'dokument wygenerowany':'bez aktualnego dokumentu'}}</p>@endforeach
@if($section==='4-4')<p><strong>Zakres z 4.3:</strong> {{data_get($sources,'scope.ZAKRES.opis')?:'Jeszcze nie określono'}}</p>@endif
@if(\App\Services\IsoExecutionQuestionnaire::handles($section)) @include('audits.system.execution-sources') @endif
@if(str_starts_with($section,'5-'))
<p><strong>Zakres z 4.3:</strong> {{data_get($sources,'scope.ZAKRES.opis')?:'Jeszcze nie określono'}}</p>
@if($section==='5-3')<p><strong>Powołanie zespołu z 5.1:</strong> {{data_get($sources,'upstream.5-1.ZESP.dokument')?:'Nie podano'}} · {{data_get($sources,'upstream.5-1.ZESP.data')}}</p>
@foreach(data_get($sources,'upstream.5-1.CZLON',[]) as $member)<p>{{$member['osoba']??''}} · {{$member['rola']??''}} · {{$member['obszar']??''}}</p>@endforeach @endif
@endif
@foreach($service->warnings($section,$answers,$sources) as $warning)<div class="plant-notice">{{$warning}}</div>@endforeach</section>
@if(str_starts_with($section,'6-')) @include('audits.system.planning-summary') @endif
@if(in_array($section,['7-1','7-2','7-3'])) @include('audits.system.support-summary') @endif
@if(\App\Services\IsoExecutionQuestionnaire::handles($section)) @include('audits.system.execution-summary') @endif
@php($editable=$canWrite && (!$client || $stale || in_array($review->status,['editing','returned','auditor_corrected'])))
@include('audits.partials.iso-release-example', ['exampleAllowed'=>!$client && $editable])
@include('audits.partials.questionnaire-excel', ['excelParameters'=>[$audit,$profile,$section]])
<form @if($editable) data-autosave data-draft-exit="{{route($client?'client.audits.show':'audits.show',[$audit,'tab'=>'surveys'])}}" @endif id="system-form" data-questionnaire-form method="post" action="{{route($prefix.'update',[$audit,$profile,$section])}}">@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="source_hash" value="{{$sources['hash']}}">
@include('audits.system.fields')<input type="hidden" name="complete_form" value="1">
@if($editable)<div class="plant-actions"><button class="primary" name="operation" value="save">Zapisz</button>@if($canClientApprove)<button name="operation" value="submit">Zatwierdź jako klient</button>@endif</div>@endif
</form>
@if(str_starts_with($section,'5-'))<section class="plant-card">@include('audits.system.chapter-five-checklist')</section>@endif
@if($section==='5-2')<section class="plant-card"><h2>Podgląd zapisanej polityki</h2><p class="plant-help">Podgląd odświeża się po zapisaniu odpowiedzi.</p>@include('audits.system.policy')</section>@endif
@if(!$client && preg_match('/^(?:[5-9]|10)-/',$section))
<section class="plant-card" id="consultant"><h2>Analiza konsultanta — wewnętrzna</h2><p>Wnioski nie są udostępniane klientowi ani dołączane do jego PDF-u.</p>
@if($canWrite && !$stale && $review->status==='submitted')
<form method="post" data-review-action action="{{route($prefix.'update',[$audit,$profile,$section])}}">@csrf
<input type="hidden" name="operation" value="consultant"><input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="source_hash" value="{{$sources['hash']}}">
<div data-conclusions>@foreach(old('consultant.WNIOSKI',$exampleConsultant['WNIOSKI']??$review->consultant['WNIOSKI']??[[]]) as $i=>$conclusion)<div class="system-row">
@foreach(['znaczenie'=>'Co z tego wynika?', 'decyzja'=>'Co wybieramy?', 'dzialanie'=>'Co konkretnie robimy?', 'sprawdzenie'=>'Po czym poznamy, że zadziałało?'] as $key=>$label)<label>{{$label}}<textarea name="consultant[WNIOSKI][{{$i}}][{{$key}}]" maxlength="3000" required>{{$conclusion[$key]??''}}</textarea></label>@endforeach
<button type="button" data-remove-conclusion>Usuń wniosek</button></div>@endforeach</div><button type="button" data-add-conclusion>+ Dodaj wniosek</button><button class="primary">Zapisz analizę konsultanta</button></form>
@else @foreach($review->consultant['WNIOSKI']??[] as $conclusion)@foreach($conclusion as $text)<p>{{$text}}</p>@endforeach @endforeach<p>Analizę można zapisać po zatwierdzeniu ankiety przez klienta.</p>@endif
</section>@endif
<section id="approvals" class="plant-card"><h2>Zatwierdzenia i dokument</h2>@include('audits.partials.questionnaire-status', ['statusRecord'=>$review, 'statusStale'=>$stale])
@if($canWrite && !$stale && $review->status==='submitted')<form method="post" data-review-action action="{{route($prefix.'update',[$audit,$profile,$section])}}">@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="source_hash" value="{{$sources['hash']}}">@if(!$client)<button class="primary" name="operation" value="approve">Zatwierdź jako audytor</button><label>Powód zwrotu<textarea name="note"></textarea></label><button name="operation" value="return">Zwróć do uzupełnienia</button>@elseif($canClientApprove)<button name="operation" value="withdraw">Wycofaj zatwierdzenie</button>@endif</form>@endif
@if(!$stale && $sources['ready'] && $review->status==='approved')<div class="plant-inline">@foreach($canWrite?[true,false]:[true] as $preview)<form method="post" data-review-action action="{{route($prefix.'pdf',[$audit,$profile,$section])}}" @if($preview) target="_blank" @endif>@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="preview" value="{{$preview?1:0}}"><button>{{$preview?'Podgląd PDF':'Generuj i zapisz PDF'}}</button></form>@endforeach</div>@endif
<p><a href="{{route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>$section])}}#iso-documents-{{$section}}">Dokumentacja punktu — pliki, kopiowanie i usuwanie zgodnie z uprawnieniami →</a></p>
<h3>Historia zmian</h3><div class="plant-scroll"><table><thead><tr><th>Data</th><th>Osoba</th><th>Działanie</th></tr></thead><tbody>@foreach($events as $event)<tr><td data-sort-value="{{$event->created_at}}">{{\Carbon\Carbon::parse($event->created_at)->format('d.m.Y H:i')}}</td><td>{{$event->user_name}}</td><td>{{['save'=>'Zapisano','submit'=>'Klient zatwierdził','approve'=>'Audytor zatwierdził','return'=>'Zwrócono do poprawy','withdraw'=>'Wycofano zatwierdzenie'][$event->action]??$event->action}}</td></tr>@endforeach</tbody></table></div></section>
@include('partials.field-validation') @include('partials.questionnaire-navigation')
@if(str_starts_with($section,'7-'))<script src="{{asset('js/iso-support.js')}}" defer></script>@endif
@if(\App\Services\IsoExecutionQuestionnaire::handles($section))<script src="{{asset('js/iso-execution.js')}}" defer></script>@endif
</div></main><script type="module" src="{{asset('js/table-sort.js')}}"></script><script src="{{asset('js/iso-system.js')}}" defer></script>@include('partials.form-drafts')
</body></html>
