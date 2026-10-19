<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Czynniki 4.1 · ISO 50001</title><link rel="stylesheet" href="{{asset('css/iso-plant-profile.css')}}"><style>
.factor-empty{background:#fff8dc;border-left:4px solid #e6b948}.factor-change{padding:12px;background:#edf6ff;border-left:4px solid #2877b5;margin:12px 0;white-space:pre-wrap;overflow-wrap:anywhere}.factor-auto{background:#f1f6f3;padding:14px;border-radius:8px}.factor-source{font-size:13px;color:#526b61}textarea{min-height:85px}.factor-hidden{display:none!important}.factor-status{display:flex;gap:12px;flex-wrap:wrap}.factor-status span{padding:8px;border-radius:8px;background:#edf3ef}
</style></head><body>
<header class="plant-header"><div><small>ISO 50001 · Punkt 4</small><h1>4.1 Czynniki kontekstowe</h1><span>{{$audit->company->name}} · {{$profile->answers['site.name']['value']??'Zakład'}}</span></div><a href="{{route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>'4-1'])}}">← Wróć do audytu</a></header>
<main class="plant-main">
@include('audits.partials.iso-html-editor', ['htmlSection'=>'4-1'])
<div hidden data-iso-html-native>
@php
 $editable=$ready && $canWrite && (!$client || $stale || in_array($review->status,['editing','returned','auditor_corrected']));
 $display=$questionnaire->formAnswers(old('answers',$answers));
 $factors=$questionnaire->factors($facts,$display);
 $progress=$questionnaire->progress($facts,$answers);
 $operations=['save_swot'=>'Zapisano analizę SWOT','save'=>'Zapisano odpowiedzi','submit'=>'Zatwierdzono jako klient','approve'=>'Zatwierdzono jako audytor','return'=>'Zwrócono do uzupełnienia','withdraw'=>'Wycofano zatwierdzenie'];
@endphp
@include('audits.partials.questionnaire-excel',['excelReady'=>$ready])
@include('audits.partials.questionnaire-example', ['exampleAllowed'=>!$client && $editable, 'exampleForm'=>'factor-form'])
@include('audits.partials.iso-release-example', ['exampleAllowed'=>!$client && $editable])
@if(session('success'))<div class="plant-notice success" role="status">{{session('success')}}</div>@endif
@if($errors->any())<div class="plant-notice error" role="alert"><strong>Sprawdź zaznaczone pola:</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@if(!$ready)<div class="plant-notice">Ankieta korzysta z zatwierdzonego profilu zakładu. Najpierw zatwierdź profil jako klient i audytor. <a href="{{route($client?'client.audits.plant-profile.show':'audits.plant-profile.show',[$audit,$profile])}}">Otwórz profil zakładu →</a></div>@endif
@if($stale)<div class="plant-notice">Profil zakładu zmienił się. Zachowano niezależne decyzje, a czynniki zależne od zmienionych danych wymagają ponownego potwierdzenia. Dotychczasowe zatwierdzenia ankiety nie są już aktualne.</div>@endif
<div class="plant-summary"><strong id="factor-progress" role="status">Wypełnienie: {{$progress['percent']}}% · {{$progress['answered']}} / {{$progress['total']}}</strong><span>{{\App\Models\IsoPlantProfile::STATUSES[$stale?'editing':$review->status]}}</span></div>
@include('audits.partials.questionnaire-status', ['statusRecord'=>$review, 'statusStale'=>$stale])
@if($review->review_note)<div class="plant-notice"><strong>Uwagi audytora</strong><p>{{$review->review_note}}</p></div>@endif
@if($review->status==='auditor_corrected')<div class="plant-notice">Sprawdź oznaczone na niebiesko korekty audytora i ponownie zatwierdź ankietę.</div>@elseif(!$client && $review->status==='submitted')<div class="plant-notice">Ankieta oczekuje na Twój przegląd. Zapisanie zmian wymaga ponownego zatwierdzenia przez klienta.</div>@endif
<nav class="plant-nav" aria-label="Działy ankiety"><a href="#climate">Ocena klimatu</a>@foreach($questionnaire->schema()['sekcje'] as $section)<a href="#section-{{$loop->index}}">{{$section['nazwa']}}</a>@endforeach<a href="#custom">Własne czynniki</a>@unless($client)<a href="#swot">Analiza SWOT</a>@endunless<a href="#approvals">Zatwierdzenia</a></nav>
<form @if($editable) data-autosave data-draft-exit="{{route($client?'client.audits.show':'audits.show',[$audit,'tab'=>'surveys'])}}" @endif id="factor-form" method="post" action="{{route($prefix.'update',[$audit,$profile])}}">@csrf
<input type="hidden" name="lock_version" value="{{old('lock_version',$review->lock_version)}}"><input type="hidden" name="source_hash" value="{{old('source_hash',$questionnaire->hash($profile))}}">
@include('audits.factors.fields')
<details class="plant-card"><summary>Czynniki niewynikające z aktualnych odpowiedzi</summary>@foreach($factors as $factor)@if(!$factor['visible'])<p><strong>{{$factor['kod']}}</strong> · {{$factor['tresc']}}<br><small>Warunek: {{$factor['pokaz_gdy']}}@if(in_array(null,$factor['dependencies'],true)) · Brak danych do oceny warunku — nie oznacza „nie dotyczy”.@endif</small></p>@endif @endforeach</details>
@if($editable)<div class="plant-actions"><span id="factor-save-state">Zapisz odpowiedzi przed wyjściem.</span><button name="operation" value="save" class="primary">Zapisz</button>@if($canClientApprove)<button name="operation" value="submit">Zatwierdź jako klient</button>@endif</div>@endif
<input type="hidden" name="complete_form" value="1"></form>
@unless($client) @include('audits.factors.swot') @endunless
<section id="approvals" class="plant-card"><h2>Zatwierdzenia i dokument</h2>@include('audits.partials.questionnaire-status', ['statusRecord'=>$review, 'statusStale'=>$stale])
@foreach(['client_approval'=>'Klient','auditor_approval'=>'Audytor'] as $field=>$label)@if($review->$field)<p>{{$label}}: {{$review->$field['name']}} · {{\Carbon\Carbon::parse($review->$field['at'])->format('d.m.Y H:i')}} @if($stale) (zatwierdzenie nieaktualne) @endif</p>@endif @endforeach
@if($ready && !$stale && $canWrite && $review->status==='submitted')<form method="post" data-review-action action="{{route($prefix.'update',[$audit,$profile])}}">@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="source_hash" value="{{$questionnaire->hash($profile)}}">@if(!$client)<label>Wynik przeglądu / uwagi<textarea name="note" required maxlength="3000"></textarea></label><button name="operation" value="return">Zwróć do uzupełnienia</button>@elseif($canClientApprove)<button name="operation" value="withdraw">Wycofaj zatwierdzenie</button>@endif</form>@endif
@if($ready && !$stale && $review->status==='approved')<div class="plant-inline">@foreach([true,false] as $preview)<form data-review-action method="post" action="{{route($prefix.'pdf',[$audit,$profile])}}" @if($preview) target="_blank" @endif>@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="preview" value="{{$preview?1:0}}"><button>{{$preview?'Podgląd PDF':'Generuj i zapisz PDF'}}</button></form>@endforeach</div>@endif
@if($review->document_id)<p><a href="{{route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>'4-1'])}}#iso-documents-4-1">Otwórz PDF w Dokumentacji punktu 4.1 →</a></p>@endif
<h3>Historia</h3><div class="plant-scroll"><table><thead><tr><th>Data</th><th>Osoba</th><th>Działanie</th></tr></thead><tbody>@foreach($events as $event)<tr><td data-sort-value="{{$event->created_at}}">{{\Carbon\Carbon::parse($event->created_at)->format('d.m.Y H:i')}}</td><td>{{$event->user_name}}</td><td>{{$operations[$event->action]??$event->action}}</td></tr>@endforeach</tbody></table></div></section>
@include('partials.field-validation')
@include('partials.questionnaire-navigation')
</div></main><script type="module" src="{{asset('js/table-sort.js')}}"></script><script src="{{asset('js/iso-factors.js')}}?v=guidelines-2" defer></script>@include('partials.form-drafts')
</body></html>
