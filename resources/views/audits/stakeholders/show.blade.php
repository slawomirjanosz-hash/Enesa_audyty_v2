<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>4.2 Strony zainteresowane</title><link rel="stylesheet" href="{{asset('css/iso-plant-profile.css')}}"><style>.stakeholder-change{background:#edf6ff;border-left:4px solid #2877b5;padding:15px}.stakeholder-empty{background:#fff8dc;border-left:4px solid #e6b948}textarea{min-height:90px}.stakeholder-read{white-space:pre-wrap;overflow-wrap:anywhere}.stakeholder-tag{display:inline-block;background:#f8ebd0;padding:4px 10px;border-radius:12px;font-size:12px}.plant-question{padding:18px;margin:16px 0;border:1px solid #dce5df;border-radius:10px}.plant-scroll{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #dce5df}button{cursor:pointer}</style></head><body>
<header class="plant-header"><div><small>ISO 50001 · Punkt 4.2</small><h1>Strony zainteresowane</h1><span>{{$audit->company->name}} · {{$profile->answers['site.name']['value']??'Zakład'}}</span></div><a href="{{route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>'4-2'])}}">← Wróć do audytu</a></header>
<main class="plant-main">
@php
 $editable=$ready && $canWrite && (!$client || $stale || in_array($review->status,['editing','returned','auditor_corrected']));
 $consultantEditable=$ready && !$stale && !$client && $canWrite && $review->exists;
 $input=old('answers',$answers);
 $input=is_array($input)?$input:[];
 $value=fn($path,$fallback='')=>is_scalar($v=data_get($input,$path,$fallback))?(string)$v:'';
 $safeOld=fn($path,$fallback='')=>is_scalar($v=old($path,$fallback))?(string)$v:'';
 $progress=$questionnaire->progress($answers,$parties,$review->consultant??[]);
 $changes=$client?($review->auditor_changes??[]):($review->client_changes??[]);
 $changed=fn($path)=>isset($changes['answers']) && data_get($changes['answers']['before'],$path)!==data_get($changes['answers']['after'],$path);
 $operations=['save'=>'Zapisano odpowiedzi','submit'=>'Zatwierdzono jako klient','consultant'=>'Zapisano ocenę konsultanta','approve'=>'Zatwierdzono jako audytor','return'=>'Zwrócono do uzupełnienia','withdraw'=>'Wycofano zatwierdzenie'];
@endphp
@include('audits.partials.questionnaire-excel')
@include('audits.partials.questionnaire-example', ['exampleAllowed'=>!$client && $editable, 'exampleForm'=>'stakeholder-form'])
@include('audits.partials.iso-release-example', ['exampleAllowed'=>!$client && $editable])
@if(session('success'))<div class="plant-notice success" role="status">{{session('success')}}</div>@endif
@if($errors->any())<div class="plant-notice error" role="alert"><strong>Sprawdź zaznaczone pola:</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@if(!$ready)<div class="plant-notice">Najpierw zatwierdź profil zakładu przez klienta i audytora oraz ankietę 4.1 jako klient. <a href="{{route($client?'client.audits.factors.show':'audits.factors.show',[$audit,$profile])}}">Otwórz punkt 4.1 →</a></div>@endif
@if($stale)<div class="plant-notice">Profil zakładu lub punkt 4.1 zmienił się. Sprawdź zależne decyzje i zapisz ponownie ankietę. Dotychczasowe zatwierdzenia są nieaktualne.</div>@endif
<div class="plant-summary"><strong>Wypełnienie: {{$progress['percent']}}% · {{$progress['answered']}} / {{$progress['total']}}</strong><span>{{\App\Models\IsoPlantProfile::STATUSES[$stale?'editing':$review->status]}}</span></div>
@include('audits.partials.questionnaire-status', ['statusRecord'=>$review, 'statusStale'=>$stale])
@if($changes)<div class="plant-notice">{{$client?'Audytor zmienił dane — sprawdź oznaczone pola i ponownie zatwierdź rejestr.':'Klient zmienił dane — sprawdź oznaczone pola przed zatwierdzeniem.'}}</div>@endif
@if(!$client && $review->status==='submitted')<div class="plant-notice">Do wykonania: przegląd i zatwierdzenie rejestru przez audytora.</div>@endif
@if($review->review_note)<div class="plant-notice">Uwagi audytora: {{$review->review_note}}</div>@endif
<nav class="plant-nav" aria-label="Działy ankiety"><a href="#climate">Wymagania klimatyczne</a>@foreach($questionnaire->schema()['grupy'] as $group)<a href="#group-{{$group['id']}}">{{$group['krotka']}}</a>@endforeach<a href="#custom">Własne strony</a><a href="#consultant">Ocena konsultanta</a><a href="#approvals">Zatwierdzenia i dokument</a></nav>
<form @if($editable) data-autosave data-draft-exit="{{route($client?'client.audits.show':'audits.show',[$audit,'tab'=>'surveys'])}}" @endif id="stakeholder-form" data-questionnaire-form method="post" action="{{route($prefix.'update',[$audit,$profile])}}">@csrf
<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="source_hash" value="{{$hash}}"><input type="hidden" name="complete_form" value="1">
@include('audits.stakeholders.fields')
<details class="plant-card"><summary>Strony niewynikające z aktualnych danych</summary>@foreach($parties as $party)@if(!$party['visible'])<p>{{$party['kod']}} · {{$party['nazwa']}}<br>{{$party['warunek']}}@if(in_array(null,$party['dependencies'],true)) · Brak danych do oceny warunku.@endif</p>@endif @endforeach</details>
@if($editable)<div class="plant-actions"><span>Zapisz odpowiedzi przed oceną konsultanta.</span><button class="primary" name="operation" value="save">Zapisz</button>@if($canClientApprove)<button name="operation" value="submit">Zatwierdź jako klient</button>@endif</div>@endif
</form>
@include('audits.stakeholders.consultant')
<section id="approvals" class="plant-card"><h2>Zatwierdzenia i dokument</h2>
@include('audits.partials.questionnaire-status', ['statusRecord'=>$review, 'statusStale'=>$stale])
@foreach(['client_approval'=>'Klient','auditor_approval'=>'Audytor'] as $field=>$label)@if($review->$field)<p>{{$label}}: {{$review->$field['name']}} · {{\Carbon\Carbon::parse($review->$field['at'])->format('d.m.Y H:i')}}</p>@endif @endforeach
@if($ready && !$stale && $canWrite && $review->status==='submitted')<form data-review-action method="post" action="{{route($prefix.'update',[$audit,$profile])}}">@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="source_hash" value="{{$hash}}">@if(!$client)<button name="operation" value="approve" class="primary">Zatwierdź jako audytor</button><label>Powód zwrotu<textarea name="note" maxlength="3000"></textarea></label><button name="operation" value="return">Zwróć do uzupełnienia</button>@elseif($canClientApprove)<button name="operation" value="withdraw">Wycofaj zatwierdzenie</button>@endif</form>@endif
@if($ready && !$stale && $review->status==='approved')
@foreach([false=>'Cały rejestr D-EnMS-STR-01',true=>'Wyciąg wymagań zgodności'] as $extract=>$label)<h3>{{$label}}</h3><div class="plant-inline">@foreach([true,false] as $preview)<form data-review-action method="post" action="{{route($prefix.'pdf',[$audit,$profile])}}" @if($preview) target="_blank" @endif>@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="preview" value="{{$preview?1:0}}"><input type="hidden" name="extract" value="{{$extract}}"><button>{{$preview?'Podgląd PDF':'Generuj i zapisz PDF'}}</button></form>@endforeach</div>@endforeach
@endif
@if($review->document_id||$review->compliance_document_id)<p><a href="{{route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>'4-2'])}}#iso-documents-4-2">Dokumentacja punktu 4.2 →</a></p>@endif
<h3>Historia zmian</h3><div class="plant-scroll"><table><thead><tr><th>Data</th><th>Osoba</th><th>Działanie</th></tr></thead><tbody>@foreach($events as $event)<tr><td data-sort-value="{{$event->created_at}}">{{\Carbon\Carbon::parse($event->created_at)->format('d.m.Y H:i')}}</td><td>{{$event->user_name}}</td><td>{{$operations[$event->action]??$event->action}}</td></tr>@endforeach</tbody></table></div>
</section>
@include('partials.field-validation') @include('partials.questionnaire-navigation')
</main><script type="module" src="{{asset('js/table-sort.js')}}"></script><script src="{{asset('js/iso-stakeholders.js')}}" defer></script>@include('partials.form-drafts')
</body></html>
