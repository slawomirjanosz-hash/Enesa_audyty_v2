@extends('audits.plant-profile.layout')
@section('content')
@php
    $answers = $questionnaire->formAnswers(old('answers', $exampleAnswers ?? $profile->answers), $profile->definition);
    $editable = $canWrite && ($isLatest ?? true) && (!$client || in_array($profile->status,['editing','returned','auditor_corrected']));
    $operations = ['create'=>'Utworzono profil','save'=>'Zapisano odpowiedzi','submit'=>'Zatwierdzono jako klient','approve'=>'Zatwierdzono jako audytor','return'=>'Zwrócono do uzupełnienia','withdraw'=>'Wycofano zatwierdzenie klienta','revise'=>'Utworzono nową wersję','before_correction'=>'Wersja przed korektą audytora','auditor_correction'=>'Poprawiony przez audytora — wymagane ponowne zatwierdzenie klienta'];
    $operations['schema_upgrade'] = 'Rozszerzono profil według ankiety audytorów — odpowiedzi zachowane';
    $operations['elaborate'] = 'Opracowano charakterystykę zakładu i podstawy wdrożenia EnMS';
@endphp
@php($sourceProfile=collect($questionnaire->questions($profile->definition))->contains(fn($question)=>str_starts_with($question['key'],'FAKT_')))
@if($sourceProfile) @include('audits.partials.iso-html-editor', ['htmlSection'=>'intro']) @endif
<div @if($sourceProfile) hidden data-iso-html-native @endif>
@include('audits.partials.questionnaire-excel')
@include('audits.partials.questionnaire-example', ['exampleAllowed'=>!$client && $editable, 'exampleForm'=>'plant-form'])
@include('audits.partials.iso-release-example', ['exampleAllowed'=>!$client && $editable])
@include('partials.questionnaire-progress', ['progress'=>app(\App\Services\QuestionnaireCompletion::class)->plant($profile->definition,$answers), 'progressForm'=>'#plant-form', 'progressMode'=>'plant'])
@include('audits.plant-profile.status')
<div class="plant-summary"><div><a href="{{ route($prefix.'index',$audit) }}">← Wszystkie profile</a><h2>{{ $profile->answers['site.name']['value'] ?? 'Zakład' }}</h2></div><span class="plant-badge">{{ \App\Models\IsoPlantProfile::STATUSES[$profile->status] }} · wersja {{ $profile->revision }}</span></div>
@if($profile->review_note)<div class="plant-notice"><strong>Uwagi audytora</strong><p>{{ $profile->review_note }}</p></div>@endif
@if(!($isLatest ?? true))<div class="plant-notice">To wersja historyczna. <a href="{{route($prefix.'index',$audit)}}">Otwórz najnowszą wersję profilu</a>, aby ją edytować lub zatwierdzić.</div>@elseif($profile->status==='auditor_corrected')<div class="plant-notice">Poprawiony przez audytora. Zmienione odpowiedzi oznaczono na niebiesko. Sprawdź je i ponownie zatwierdź ankietę.</div>@elseif(!$client && in_array($profile->status,['submitted','approved']))<div class="plant-notice">Zapis korekty oznaczy zmienione odpowiedzi w tej samej ankiecie i będzie wymagał ponownego zatwierdzenia przez klienta.</div>@endif
<style>.plant-correction{margin:10px 0;padding:12px 14px;border-left:4px solid #2877b5;background:#edf6ff;border-radius:6px;color:#17466c;font-size:14px}.plant-correction p{margin:6px 0;white-space:pre-wrap;overflow-wrap:anywhere}.plant-question:has(.plant-correction)>input,.plant-question:has(.plant-correction)>select,.plant-question:has(.plant-correction)>details{border:2px solid #2877b5;background:#f3f8ff}</style>
@if(isset($profile->client_changes['_as_of_date']))<div class="plant-correction"><strong>Klient zmienił datę „Stan danych na dzień”</strong><p>Przed: {{$profile->client_changes['_as_of_date']['before']}} → Po korekcie: {{$profile->client_changes['_as_of_date']['after']}}</p></div>@endif
@if(isset($profile->auditor_changes['_as_of_date']))<div class="plant-correction"><strong>Audytor zmienił datę „Stan danych na dzień”</strong><p>Przed: {{$profile->auditor_changes['_as_of_date']['before']}} → Po korekcie: {{$profile->auditor_changes['_as_of_date']['after']}}</p></div>@endif
<nav class="plant-nav" aria-label="Części profilu">@foreach($profile->definition['groups'] as $group)<a href="#group-{{ $loop->index }}">{{ $group['title'] }}</a>@endforeach<a href="#approvals">Zatwierdzenia i historia</a></nav>
<form @if($editable) data-autosave data-draft-exit="{{route($client?'client.audits.show':'audits.show',[$audit,'tab'=>'surveys'])}}" @endif id="plant-form" @if($profile->lock_version > 0) data-highlight-unanswered @endif method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@csrf<input type="hidden" name="lock_version" value="{{ old('lock_version',$profile->lock_version) }}">
<script type="application/json" id="plant-energy-factors">@json(\App\Services\IsoPlantCalculations::FACTORS)</script>
@include('audits.plant-profile.fields')
@if($editable)<div class="plant-actions"><span id="plant-save-state" role="status">Zapisz odpowiedzi przed wyjściem.</span><button name="operation" value="save" class="primary">Zapisz</button>@if($canClientApprove)<button name="operation" value="submit" data-confirm="Potwierdzasz dane tej wersji profilu i przekazujesz je do przeglądu audytora?">Zatwierdź jako klient</button>@elseif($client)<small>Zatwierdza administrator klienta.</small>@endif</div>@endif
<input type="hidden" name="complete_form" value="1"></form>
@if(!empty($profile->definition['legacy_groups']))
<details class="plant-card"><summary>Odpowiedzi zachowane z poprzedniego profilu</summary><p>Pozostają pod dotychczasowymi nazwami zmiennych. Pytania o innym znaczeniu wymagają potwierdzenia w nowej ankiecie.</p>
@foreach($profile->definition['legacy_groups'] as $oldGroup)<h3>{{$oldGroup['title']}}</h3>@foreach($oldGroup['questions'] as $oldQuestion)@if(isset($profile->answers[$oldQuestion['key']]))<p><strong>{{$oldQuestion['label']}}</strong><br><small>{{$oldQuestion['key']}}</small><br>{{ $questionnaire->display($oldQuestion,$profile->answers[$oldQuestion['key']]) }}</p>@endif @endforeach @endforeach
</details>
@endif
<section id="approvals" class="plant-card"><h2>Zatwierdzenia</h2>
@include('audits.plant-profile.status')
@foreach(['client_approval'=>'Klient','auditor_approval'=>'Audytor'] as $field=>$label)<p><strong>{{ $label }}:</strong> @if($profile->$field){{ $profile->$field['name'] }} · {{ \Carbon\Carbon::parse($profile->$field['at'])->format('d.m.Y H:i') }}@else Oczekuje na zatwierdzenie @endif</p>@endforeach
@if($canWrite && $profile->status==='submitted')<form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@csrf<input type="hidden" name="lock_version" value="{{ $profile->lock_version }}">
@if(!$client)<label>Wynik przeglądu / uwagi do uzupełnienia<textarea name="note" required maxlength="3000" placeholder="Potwierdź przegląd danych i opisz sposób potraktowania informacji nieznanych lub brakujących."></textarea></label><button name="operation" value="approve" class="primary" data-confirm="Zatwierdzić tę wersję profilu po przeglądzie?">Zatwierdź jako audytor</button><button name="operation" value="return">Zwróć do uzupełnienia</button>
@elseif($canClientApprove)<button name="operation" value="withdraw">Wycofaj zatwierdzenie i edytuj</button>@endif</form>@endif
@if($profile->status==='approved')
<div class="plant-inline"><form method="post" action="{{ route($prefix.'pdf',[$audit,$profile]) }}" target="_blank">@csrf<input type="hidden" name="lock_version" value="{{ $profile->lock_version }}"><input type="hidden" name="preview" value="1"><button>Podgląd PDF ↗</button></form>
<form method="post" action="{{ route($prefix.'pdf',[$audit,$profile]) }}">@csrf<input type="hidden" name="lock_version" value="{{ $profile->lock_version }}"><button class="primary">Generuj i zapisz PDF</button></form>
@if(!$client && $canWrite && ($isLatest ?? true))
<form method="post" action="{{ route($prefix.'pdf',[$audit,$profile]) }}">@csrf<input type="hidden" name="lock_version" value="{{ $profile->lock_version }}"><input type="hidden" name="kind" value="elaboration"><button class="primary" title="Utwórz opisową charakterystykę zakładu na podstawie zatwierdzonych danych">Opracuj ankietę</button></form>
<form method="post" action="{{ route($prefix.'pdf',[$audit,$profile]) }}" target="_blank">@csrf<input type="hidden" name="lock_version" value="{{ $profile->lock_version }}"><input type="hidden" name="kind" value="elaboration"><input type="hidden" name="preview" value="1"><button>Podgląd opracowania ↗</button></form>
@endif
@if($canWrite)<form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@csrf<input type="hidden" name="lock_version" value="{{ $profile->lock_version }}"><button name="operation" value="revise">Utwórz nową wersję do edycji</button></form>@endif</div>
@if($profile->document_id)<p><a href="{{route($client ? 'client.audits.show' : 'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>'intro'])}}#iso-documents-intro">PDF zapisany — przejdź do Dokumentacji punktu we „Wstępie do ISO” →</a></p>@endif
@if($profile->elaboration_document_id)<p><a href="{{route($client ? 'client.audits.show' : 'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>'intro'])}}#iso-documents-intro">Opracowanie zapisane — otwórz Dokumentację punktu →</a></p>@endif
@endif
<h3>Historia</h3><div class="plant-scroll"><table><thead><tr><th>Data</th><th>Osoba</th><th>Działanie</th></tr></thead><tbody>@foreach($events as $event)<tr><td data-sort-value="{{ $event->created_at }}">{{ \Carbon\Carbon::parse($event->created_at)->format('d.m.Y H:i:s') }}</td><td>{{ $event->user_name }}</td><td>{{ $operations[$event->action]??$event->action }}</td></tr>@endforeach</tbody></table></div></section>
</div>
@endsection
