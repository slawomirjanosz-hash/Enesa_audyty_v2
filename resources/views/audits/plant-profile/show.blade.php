@extends('audits.plant-profile.layout')
@section('content')
@php
    $answers = old('answers', $profile->answers);
    $editable = $canWrite && in_array($profile->status,['editing','returned']);
    $operations = ['create'=>'Utworzono profil','save'=>'Zapisano odpowiedzi','submit'=>'Zatwierdzono jako klient','approve'=>'Zatwierdzono jako audytor','return'=>'Zwrócono do uzupełnienia','withdraw'=>'Wycofano zatwierdzenie klienta','revise'=>'Utworzono nową wersję'];
@endphp
@include('partials.questionnaire-progress', ['progress'=>app(\App\Services\QuestionnaireCompletion::class)->plant($profile->definition,$answers), 'progressForm'=>'#plant-form', 'progressMode'=>'plant'])
<div class="plant-summary"><div><a href="{{ route($prefix.'index',$audit) }}">← Wszystkie profile</a><h2>{{ $profile->answers['site.name']['value'] ?? 'Zakład' }}</h2></div><span class="plant-badge">{{ \App\Models\IsoPlantProfile::STATUSES[$profile->status] }} · wersja {{ $profile->revision }}</span></div>
@if($profile->review_note)<div class="plant-notice"><strong>Uwagi audytora</strong><p>{{ $profile->review_note }}</p></div>@endif
<nav class="plant-nav" aria-label="Części profilu">@foreach($profile->definition['groups'] as $group)<a href="#group-{{ $loop->index }}">{{ $group['title'] }}</a>@endforeach<a href="#approvals">Zatwierdzenia i historia</a></nav>
<form id="plant-form" method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@csrf<input type="hidden" name="lock_version" value="{{ old('lock_version',$profile->lock_version) }}">
<fieldset @disabled(!$editable)><section class="plant-card"><label>Stan danych na dzień<input type="date" name="as_of_date" value="{{ old('as_of_date',$profile->as_of_date->format('Y-m-d')) }}" required></label></section>
@foreach($profile->definition['groups'] as $group)<section class="plant-card" id="group-{{ $loop->index }}"><h2>{{ $group['title'] }}</h2>
@foreach($group['questions'] as $q)
@php
    $answer = $answers[$q['key']] ?? [];
    $name = 'answers['.$q['key'].']';
    $id = 'q-'.str_replace('.','-',$q['key']);
@endphp
<div class="plant-question" data-question="{{ $q['key'] }}" @if(isset($q['condition'])) data-condition='@json($q["condition"])' @endif>
<label for="{{ $id }}" class="question-title">{{ $q['label'] }} @if($q['required'])<span aria-label="wymagane">*</span>@endif</label>
@if($q['type']==='select')<select id="{{ $id }}" name="{{ $name }}[value]"><option value="">Wybierz odpowiedź</option>@foreach($q['options'] as $value=>$label)<option value="{{ $value }}" @selected(($answer['value']??null)===$value)>{{ $label }}</option>@endforeach</select>
@elseif($q['type']==='multi')<details class="plant-multi"><summary id="{{ $id }}"><span data-selection-label>{{ count($answer['value']??[]) ? $questionnaire->display($q,$answer) : 'Wybierz odpowiedzi' }}</span></summary><div>@foreach($q['options'] as $value=>$label)<label><input type="checkbox" name="{{ $name }}[value][]" value="{{ $value }}" data-label="{{ $label }}" @if(in_array($label,['Nie wiem','Brak','Brak pomiarów','Brak znanych zmian'])) data-exclusive @endif @checked(in_array($value,$answer['value']??[]))> {{ $label }}</label>@endforeach</div></details>
@elseif($q['type']==='rows')
<div data-repeat data-next="{{ count($answer['value'] ?? []) }}">
<div data-rows>@foreach($answer['value'] ?? [] as $row)@include('audits.plant-profile.row',['rowIndex'=>$loop->index])@endforeach</div>
<template>@include('audits.plant-profile.row',['rowIndex'=>'__INDEX__','row'=>[]])</template>
<button type="button" data-add-row>+ Dodaj {{ $q['key']==='site.buildings'?'budynek':'dane nośnika' }}</button>
</div>
@else<input id="{{ $id }}" type="{{ $q['type']==='number'?'number':'text' }}" @if($q['type']==='number') min="0" step="1" @else maxlength="2000" @endif name="{{ $name }}[value]" value="{{ $answer['value']??'' }}">@endif
@if(!in_array($q['type'],['select','multi']) && !$q['required'])<label class="plant-check"><input type="checkbox" name="{{ $name }}[unknown]" value="1" @checked($answer['unknown']??false)> Nie wiem / dane niedostępne</label>@endif
<details @if(filled($answer['detail']??null)||filled($answer['source']??null)) open @endif><summary>Uzupełnienie i źródło odpowiedzi</summary><p class="plant-help">{{ $q['hint'] }}</p><label for="{{ $id }}-detail">Szczegóły / wyjaśnienie<textarea id="{{ $id }}-detail" name="{{ $name }}[detail]" maxlength="3000" rows="2">{{ $answer['detail']??'' }}</textarea></label><label for="{{ $id }}-source">Źródło / nazwa dokumentu<input id="{{ $id }}-source" name="{{ $name }}[source]" maxlength="500" value="{{ $answer['source']??'' }}"></label></details>
</div>@endforeach</section>@endforeach</fieldset>
@if($editable)<div class="plant-actions"><span id="plant-save-state" role="status">Zapisz odpowiedzi przed wyjściem.</span><button name="operation" value="save" class="primary">Zapisz</button>@if($canClientApprove)<button name="operation" value="submit" data-confirm="Potwierdzasz dane tej wersji profilu i przekazujesz je do przeglądu audytora?">Zatwierdź jako klient</button>@elseif($client)<small>Zatwierdza administrator klienta.</small>@endif</div>@endif
<input type="hidden" name="complete_form" value="1"></form>
<section id="approvals" class="plant-card"><h2>Zatwierdzenia</h2>
@foreach(['client_approval'=>'Klient','auditor_approval'=>'Audytor'] as $field=>$label)<p><strong>{{ $label }}:</strong> @if($profile->$field){{ $profile->$field['name'] }} · {{ \Carbon\Carbon::parse($profile->$field['at'])->format('d.m.Y H:i') }}@else Oczekuje na zatwierdzenie @endif</p>@endforeach
@if($canWrite && $profile->status==='submitted')<form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@csrf<input type="hidden" name="lock_version" value="{{ $profile->lock_version }}">
@if(!$client)<label>Wynik przeglądu / uwagi do uzupełnienia<textarea name="note" required maxlength="3000" placeholder="Potwierdź przegląd danych i opisz sposób potraktowania informacji nieznanych lub brakujących."></textarea></label><button name="operation" value="approve" class="primary" data-confirm="Zatwierdzić tę wersję profilu po przeglądzie?">Zatwierdź jako audytor</button><button name="operation" value="return">Zwróć do uzupełnienia</button>
@elseif($canClientApprove)<button name="operation" value="withdraw">Wycofaj zatwierdzenie i edytuj</button>@endif</form>@endif
@if($profile->status==='approved')
<div class="plant-inline"><form method="post" action="{{ route($prefix.'pdf',[$audit,$profile]) }}" target="_blank">@csrf<input type="hidden" name="lock_version" value="{{ $profile->lock_version }}"><input type="hidden" name="preview" value="1"><button>Podgląd PDF ↗</button></form>
<form method="post" action="{{ route($prefix.'pdf',[$audit,$profile]) }}">@csrf<input type="hidden" name="lock_version" value="{{ $profile->lock_version }}"><button class="primary">Generuj i zapisz PDF</button></form>
@if($canWrite)<form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@csrf<input type="hidden" name="lock_version" value="{{ $profile->lock_version }}"><button name="operation" value="revise">Utwórz nową wersję do edycji</button></form>@endif</div>
@if($profile->document_id)<p>PDF zapisany w dokumentacji klienta we „Wstępie do ISO”.</p>@endif
@endif
<h3>Historia</h3><div class="plant-scroll"><table><thead><tr><th>Data</th><th>Osoba</th><th>Działanie</th></tr></thead><tbody>@foreach($events as $event)<tr><td data-sort-value="{{ $event->created_at }}">{{ \Carbon\Carbon::parse($event->created_at)->format('d.m.Y H:i:s') }}</td><td>{{ $event->user_name }}</td><td>{{ $operations[$event->action]??$event->action }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
