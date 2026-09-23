<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Czynniki 4.1 · ISO 50001</title><link rel="stylesheet" href="{{asset('css/iso-plant-profile.css')}}"><style>
.factor-empty{background:#fff8dc;border-left:4px solid #e6b948}.factor-change{padding:12px;background:#edf6ff;border-left:4px solid #2877b5;margin:12px 0;white-space:pre-wrap;overflow-wrap:anywhere}.factor-auto{background:#f1f6f3;padding:14px;border-radius:8px}.factor-source{font-size:13px;color:#526b61}textarea{min-height:85px}.factor-hidden{display:none!important}.factor-status{display:flex;gap:12px;flex-wrap:wrap}.factor-status span{padding:8px;border-radius:8px;background:#edf3ef}
</style></head><body>
<header class="plant-header"><div><small>ISO 50001 · Punkt 4</small><h1>4.1 Czynniki kontekstowe</h1><span>{{$audit->company->name}} · {{$profile->answers['site.name']['value']??'Zakład'}}</span></div><a href="{{route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>'4-1'])}}">← Wróć do audytu</a></header>
<main class="plant-main">
@php
 $editable=$ready && $canWrite && (!$client || $stale || in_array($review->status,['editing','returned','auditor_corrected']));
 $display=$questionnaire->formAnswers(old('answers',$answers));
 $factors=$questionnaire->factors($facts,$display);
 $progress=$questionnaire->progress($facts,$answers);
 $operations=['save'=>'Zapisano odpowiedzi','submit'=>'Zatwierdzono jako klient','approve'=>'Zatwierdzono jako audytor','return'=>'Zwrócono do uzupełnienia','withdraw'=>'Wycofano zatwierdzenie'];
@endphp
@if(session('success'))<div class="plant-notice success" role="status">{{session('success')}}</div>@endif
@if($errors->any())<div class="plant-notice error" role="alert"><strong>Sprawdź zaznaczone pola:</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@if(!$ready)<div class="plant-notice">Ankieta korzysta z zatwierdzonego profilu zakładu. Najpierw zatwierdź profil jako klient i audytor. <a href="{{route($client?'client.audits.plant-profile.show':'audits.plant-profile.show',[$audit,$profile])}}">Otwórz profil zakładu →</a></div>@endif
@if($stale)<div class="plant-notice">Profil zakładu zmienił się. Zachowano niezależne decyzje, a czynniki zależne od zmienionych danych wymagają ponownego potwierdzenia. Dotychczasowe zatwierdzenia ankiety nie są już aktualne.</div>@endif
<div class="plant-summary"><strong id="factor-progress" role="status">Wypełnienie: {{$progress['percent']}}% · {{$progress['answered']}} / {{$progress['total']}}</strong><span>{{\App\Models\IsoPlantProfile::STATUSES[$stale?'editing':$review->status]}}</span></div>
@if($review->review_note)<div class="plant-notice"><strong>Uwagi audytora</strong><p>{{$review->review_note}}</p></div>@endif
@if($review->status==='auditor_corrected')<div class="plant-notice">Sprawdź oznaczone na niebiesko korekty audytora i ponownie zatwierdź ankietę.</div>@elseif(!$client && $review->status==='submitted')<div class="plant-notice">Ankieta oczekuje na Twój przegląd. Zapisanie zmian wymaga ponownego zatwierdzenia przez klienta.</div>@endif
<nav class="plant-nav" aria-label="Działy ankiety"><a href="#climate">Ocena klimatu</a>@foreach($questionnaire->schema()['sekcje'] as $section)<a href="#section-{{$loop->index}}">{{$section['nazwa']}}</a>@endforeach<a href="#custom">Własne czynniki</a><a href="#approvals">Zatwierdzenia</a></nav>
<form id="factor-form" method="post" action="{{route($prefix.'update',[$audit,$profile])}}">@csrf
<input type="hidden" name="lock_version" value="{{old('lock_version',$review->lock_version)}}"><input type="hidden" name="source_hash" value="{{old('source_hash',$questionnaire->hash($profile))}}">
<fieldset @disabled(!$editable)>
<section id="climate" class="plant-card"><h2>Istotność zmiany klimatu</h2>
@include('audits.factors.changes',['changeKey'=>'FAKT_KLIMAT_ISTOTNY'])
<label for="climate-choice">Czy zmiana klimatu jest istotna dla zdolności systemu zarządzania energią do osiągania zamierzonych wyników?</label><small class="plant-help">FAKT_KLIMAT_ISTOTNY · ocena w punkcie 4.1</small>
<select id="climate-choice" name="answers[FAKT_KLIMAT_ISTOTNY]"><option value="">Wybierz</option><option value="tak" @selected(($display['FAKT_KLIMAT_ISTOTNY']??'')==='tak')>Tak</option><option value="nie" @selected(($display['FAKT_KLIMAT_ISTOTNY']??'')==='nie')>Nie</option></select><label>Uzasadnienie oceny<textarea name="answers[climate_reason]" maxlength="3000">{{$display['climate_reason']??''}}</textarea></label><p class="plant-help">Zmiana oceny aktualizuje czynniki klimatyczne po zapisaniu.</p></section>
@foreach($questionnaire->schema()['sekcje'] as $section)
<section class="plant-card" id="section-{{$loop->index}}"><h2>{{$section['opis']}}</h2><p class="plant-help">{{$section['nazwa']}}</p>
@foreach($factors as $factor)@if($factor['wymiar']===$section['id'] && $factor['visible'])
@php($row=is_array($display['factors'][$factor['kod']]??null)?$display['factors'][$factor['kod']]:[])
<div class="plant-question @if($review->lock_version && $factor['rodzaj']!=='AUTO' && empty($row['decyzja'])) factor-empty @endif" data-factor="{{$factor['kod']}}">
<h3>{{$factor['kod']}} · {{$factor['rodzaj']==='AUTO'?'Wynik oceny':($factor['rodzaj']==='OCENA'?'Do oceny':'Propozycja')}}</h3><p>{{$factor['tresc']}}</p><p class="plant-help">Wpływ: {{$factor['wplyw']}} · {{$factor['skutek']}}</p>
@include('audits.factors.changes',['changeKey'=>$factor['kod']])
@if($factor['rodzaj']==='AUTO')<div class="factor-auto">Wynika z zapisanej oceny klimatu. Treść uwzględniana w dokumencie.</div>
@else
@if(isset($factor['verification']))<p class="plant-notice">{{$factor['verification']}}</p>@endif
<label>Decyzja<select name="answers[factors][{{$factor['kod']}}][decyzja]" data-decision><option value="">Wybierz decyzję</option><option value="potwierdzony" @selected(($row['decyzja']??'')==='potwierdzony')>Potwierdzam — uwzględnij</option><option value="odrzucony" @selected(($row['decyzja']??'')==='odrzucony')>Odrzucam — nie dotyczy</option></select></label>
<label data-reason>Powód odrzucenia<textarea name="answers[factors][{{$factor['kod']}}][powod]" maxlength="3000">{{$row['powod']??''}}</textarea></label>
<details><summary>Treść do dokumentu — uzupełnij lub zmień</summary><textarea name="answers[factors][{{$factor['kod']}}][tresc]" maxlength="5000">{{$row['tresc']??$factor['tresc']}}</textarea></details>
@endif
@if($factor['dependencies'])<details class="factor-source"><summary>Źródło — odpowiedzi z profilu zakładu</summary>@foreach($factor['dependencies'] as $key=>$value)<p><strong>{{$key}}</strong>: {{is_array($value)?implode(', ',$value):($value??'Brak danych')}}</p>@endforeach</details>@endif
</div>@endif @endforeach
</section>@endforeach
<section id="custom" class="plant-card"><h2>Własne czynniki</h2>@include('audits.factors.changes',['changeKey'=>'custom'])<div id="custom-rows">@foreach($display['custom']??[] as $index=>$row)@include('audits.factors.custom-row')@endforeach</div><template id="custom-template">@include('audits.factors.custom-row',['index'=>'__INDEX__','row'=>[]])</template><button type="button" id="add-custom">+ Dodaj własny czynnik</button></section>
</fieldset>
<details class="plant-card"><summary>Czynniki niewynikające z aktualnych odpowiedzi</summary>@foreach($factors as $factor)@if(!$factor['visible'])<p><strong>{{$factor['kod']}}</strong> · {{$factor['tresc']}}<br><small>Warunek: {{$factor['pokaz_gdy']}}@if(in_array(null,$factor['dependencies'],true)) · Brak danych do oceny warunku — nie oznacza „nie dotyczy”.@endif</small></p>@endif @endforeach</details>
@if($editable)<div class="plant-actions"><span id="factor-save-state">Zapisz odpowiedzi przed wyjściem.</span><button name="operation" value="save" class="primary">Zapisz</button>@if($canClientApprove)<button name="operation" value="submit">Zatwierdź jako klient</button>@endif</div>@endif
<input type="hidden" name="complete_form" value="1"></form>
<section id="approvals" class="plant-card"><h2>Zatwierdzenia i dokument</h2><div class="factor-status"><span>W trakcie opracowania</span><span>{{$review->client_approval&&!$stale?'✓':'○'}} Zaakceptowane przez klienta</span><span>{{$review->auditor_approval&&!$stale?'✓':'○'}} Zaakceptowane przez audytora</span><span>{{$review->document_id&&!$stale?'✓':'○'}} Wygenerowano dokument</span></div>
@foreach(['client_approval'=>'Klient','auditor_approval'=>'Audytor'] as $field=>$label)@if($review->$field)<p>{{$label}}: {{$review->$field['name']}} · {{\Carbon\Carbon::parse($review->$field['at'])->format('d.m.Y H:i')}} @if($stale) (zatwierdzenie nieaktualne) @endif</p>@endif @endforeach
@if($ready && !$stale && $canWrite && $review->status==='submitted')<form method="post" data-review-action action="{{route($prefix.'update',[$audit,$profile])}}">@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="source_hash" value="{{$questionnaire->hash($profile)}}">@if(!$client)<label>Wynik przeglądu / uwagi<textarea name="note" required maxlength="3000"></textarea></label><button class="primary" name="operation" value="approve">Zatwierdź jako audytor</button><button name="operation" value="return">Zwróć do uzupełnienia</button>@elseif($canClientApprove)<button name="operation" value="withdraw">Wycofaj zatwierdzenie</button>@endif</form>@endif
@if($ready && !$stale && $review->status==='approved')<div class="plant-inline">@foreach([true,false] as $preview)<form data-review-action method="post" action="{{route($prefix.'pdf',[$audit,$profile])}}" @if($preview) target="_blank" @endif>@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="preview" value="{{$preview?1:0}}"><button>{{$preview?'Podgląd PDF':'Generuj i zapisz PDF'}}</button></form>@endforeach</div>@endif
@if($review->document_id)<p><a href="{{route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>'4-1'])}}#iso-documents-4-1">Otwórz PDF w Dokumentacji punktu 4.1 →</a></p>@endif
<h3>Historia</h3><div class="plant-scroll"><table><thead><tr><th>Data</th><th>Osoba</th><th>Działanie</th></tr></thead><tbody>@foreach($events as $event)<tr><td data-sort-value="{{$event->created_at}}">{{\Carbon\Carbon::parse($event->created_at)->format('d.m.Y H:i')}}</td><td>{{$event->user_name}}</td><td>{{$operations[$event->action]??$event->action}}</td></tr>@endforeach</tbody></table></div></section>
@include('partials.field-validation')
@include('partials.questionnaire-navigation')
</main><script type="module" src="{{asset('js/table-sort.js')}}"></script><script src="{{asset('js/iso-factors.js')}}" defer></script></body></html>
