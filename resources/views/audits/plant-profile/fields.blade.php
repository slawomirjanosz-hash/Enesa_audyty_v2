<fieldset @disabled(!$editable)><section class="plant-card"><label>Stan danych na dzień<input type="date" name="as_of_date" value="{{ old('as_of_date',$profile->as_of_date->format('Y-m-d')) }}" required></label></section>
@foreach($questionnaire->expandedDefinition($profile->definition)['groups'] as $group)<section class="plant-card" id="group-{{ $loop->index }}"><h2>{{ $group['title'] }}</h2>@if(isset($group['owner']))<p class="plant-help">Dział / osoba wspierająca wypełnienie: {{$group['owner']}}</p>@endif
@foreach($group['questions'] as $q)
@php
    $answer = $answers[$q['key']] ?? [];
    $name = 'answers['.$q['key'].']';
    $id = 'q-'.str_replace('.','-',$q['key']);
@endphp
<div class="plant-question" data-question="{{ $q['key'] }}" @if($q['type']==='auto') data-calculated @endif @if(isset($q['condition'])) data-condition='@json($q["condition"])' @endif>
<label for="{{ $id }}" class="question-title">{{ $q['label'] }} @if($q['required'])<span aria-label="wymagane">*</span>@endif</label>
<small class="plant-help" style="display:block">{{$q['key']}}@if(!empty($q['unit'])) · {{$q['unit']}}@endif</small>
<span class="plant-unanswered-label">Brak odpowiedzi</span>
@foreach(['auditor_changes'=>'audytora','client_changes'=>'klienta'] as $changeField=>$changeAuthor)
@if(isset($profile->$changeField[$q['key']]))
@php($change=$profile->$changeField[$q['key']])
<div class="plant-correction" @if($changeField==='auditor_changes') data-auditor-change="{{$q['key']}}" @else data-client-change="{{$q['key']}}" @endif><strong>Zmienione przez {{$changeAuthor}} — {{$change['by']}}</strong><small> · {{\Carbon\Carbon::parse($change['at'])->format('d.m.Y H:i')}}</small>
<p><strong>Przed zmianą:</strong> {{$questionnaire->display($q,$change['before'])}}</p><p><strong>Po korekcie {{$changeAuthor}}:</strong> {{$questionnaire->display($q,$change['after'])}}</p>
@foreach(['detail'=>'Uzupełnienie','source'=>'Źródło'] as $field=>$label)@if(($change['before'][$field]??null)!==($change['after'][$field]??null))<p><strong>{{$label}}:</strong> {{$change['before'][$field]??'Nie podano'}} → {{$change['after'][$field]??'Nie podano'}}</p>@endif @endforeach
</div>
@endif
@endforeach
@if($q['type']==='auto')<output id="{{$id}}" data-auto-value="{{$q['key']}}">{{$questionnaire->display($q,$answer)}}</output><p class="plant-help" data-auto-help="{{$q['key']}}">{{$answer['detail']??$q['hint']}}</p>
@elseif($q['type']==='select')<select id="{{ $id }}" name="{{ $name }}[value]"><option value="">Wybierz odpowiedź</option>@foreach($q['options'] as $value=>$label)<option value="{{ $value }}" @selected((string)($answer['value']??'')===(string)$value)>{{ $label }}</option>@endforeach</select>
@elseif($q['type']==='multi')<details class="plant-multi"><summary id="{{ $id }}"><span data-selection-label>{{ count($answer['value']??[]) ? $questionnaire->display($q,$answer) : 'Wybierz odpowiedzi' }}</span></summary><div>@foreach($q['options'] as $value=>$label)<label><input type="checkbox" name="{{ $name }}[value][]" value="{{ $value }}" data-label="{{ $label }}" @if(in_array($label,['Nie wiem','Brak','Brak pomiarów','Brak znanych zmian'])) data-exclusive @endif @checked(in_array($value,$answer['value']??[]))> {{ $label }}</label>@endforeach</div></details>
@elseif($q['type']==='rows')
<div data-repeat data-next="{{ empty($answer['value']) ? 0 : max(array_keys($answer['value']))+1 }}">
<div data-rows>@foreach($answer['value'] ?? [] as $rowIndex=>$row)@include('audits.plant-profile.row',['rowIndex'=>$rowIndex])@endforeach</div>
<template>@include('audits.plant-profile.row',['rowIndex'=>'__INDEX__','row'=>[]])</template>
<button type="button" data-add-row>+ Dodaj {{ $q['key']==='site.buildings'?'budynek':($q['key']==='FAKT_LOKALIZACJE_LISTA'?'lokalizację':'dane nośnika') }}</button>
</div>
@else<input id="{{ $id }}" type="{{ in_array($q['type'],['number','date']) && !$errors->has('answers.'.$q['key'].'.value')?$q['type']:'text' }}" @if($q['type']==='number') inputmode="decimal" min="{{$q['min']??0}}" @if(isset($q['max'])) max="{{$q['max']}}" @endif step="{{($q['integer']??false)?'1':'any'}}" @else maxlength="2000" @endif name="{{ $name }}[value]" value="{{ $answer['value']??'' }}">@endif
@if(!in_array($q['type'],['select','multi','auto']) && !$q['required'])<label class="plant-check"><input type="checkbox" name="{{ $name }}[unknown]" value="1" @checked($answer['unknown']??false)> Nie wiem / dane niedostępne</label>@endif
@if($q['type']!=='auto')
<details><summary>Uzupełnienie i źródło odpowiedzi</summary><p class="plant-help">{{ $q['hint'] }}</p><label for="{{ $id }}-detail">Szczegóły / wyjaśnienie<textarea id="{{ $id }}-detail" name="{{ $name }}[detail]" maxlength="3000" rows="2">{{ $answer['detail']??'' }}</textarea></label><label for="{{ $id }}-source">Źródło / nazwa dokumentu<input id="{{ $id }}-source" name="{{ $name }}[source]" maxlength="500" value="{{ $answer['source']??'' }}"></label></details>
@endif
</div>@endforeach</section>@endforeach</fieldset>
