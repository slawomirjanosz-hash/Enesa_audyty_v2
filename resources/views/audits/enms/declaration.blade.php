@php
$declaration = $service->one($w, 'declaration', 'requirement_id', $requirement['id']);
$answer = $declaration['payload'] ?? [];
if(old('operation')==='declaration' && old('requirement_id')===$requirement['id']) $answer=array_replace_recursive($answer,old('payload',[]));
$answerStates = ['jest'=>'Jest i można pokazać','czesciowo'=>'Częściowo','niema'=>'Jeszcze tego nie ma'];
if (!$requirement['normative'] && ($requirement['dopuszczaNiedotyczy'] ?? false)) $answerStates['niedotyczy']='Nie dotyczy — z uzasadnieniem';
@endphp
<section class="plant-card enms-declaration {{ $declaration && empty($answer['stan'])?'enms-missing':'' }}" id="req-{{ ($hostRequirement??'').$requirement['kod'] }}">
<h3>{{ $requirement['iso'] ?? $requirement['kod'] }} · {{ $requirement['title'] }}</h3>
<p><strong>{{ $requirement['question'] }}</strong></p>
@if($declaration && $service->changedSinceSubmission($w,$declaration))<p class="plant-notice">Ta odpowiedź zmieniła się od ostatniego przekazania ankiety.</p>@endif
@if(!empty($requirement['pytaniaPomocnicze']))<details><summary>Podpowiedzi i źródła</summary><p>{{ $requirement['pytaniaPomocnicze'] }}</p><p>Powiązania: {{ implode(', ', $requirement['powiazania']??[]) }}</p></details>@endif
<form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}" data-enms-declaration>
@include('audits.enms.token')
<input type="hidden" name="operation" value="declaration"><input type="hidden" name="requirement_id" value="{{ $requirement['id'] }}">
<fieldset @disabled(!$editable)>
<label>Odpowiedź<select name="payload[stan]" data-enms-state><option value="">Wybierz</option>@foreach($answerStates as $key=>$label)<option value="{{ $key }}" @selected(($answer['stan']??'')===$key)>{{ $label }}</option>@endforeach</select></label>
<div data-enms-states="jest czesciowo niema niedotyczy"><label>Komentarz organizacji — nieobowiązkowy<textarea name="payload[opis]" rows="2">{{ $answer['opis']??'' }}</textarea></label></div>
<div data-enms-states="jest czesciowo">
@foreach($requirement['pola'] as $field)
<label class="{{ $errors->has('pola.'.$field['klucz'])?'enms-invalid':($declaration && ($answer['stan']??'')==='jest' && $field['wymagane'] && $field['typ']!=='odwolanie' && blank($answer['pola'][$field['klucz']]??null)?'enms-missing':'') }}">{{ $field['etykieta'] }} @if(!$field['wymagane']) (nieobowiązkowe) @endif
@if($field['typ']==='odwolanie')
@php($citation=$sources['citations'][$field['zrodlo']['obiekt']])
<div class="enms-source"><strong>Punkt {{ $field['zrodlo']['punkt'] }}</strong> · {{ $citation['source_id']?'Odczyt potwierdzony':'Brak zapisu w punkcie źródłowym' }} · {{ $citation['approved']?'Aktualny, zatwierdzony dokument':'Brak aktualnego zatwierdzonego dokumentu' }}
@if($citation['value']===null || $citation['value']===[] || $citation['value']==='')
<p>Nie podano — uzupełnij punkt źródłowy.</p>
@else
@include('audits.enms.source-content',['content'=>app(\App\Services\Enms\SourcePreview::class)->content($citation,$field['zrodlo']['obiekt'])])
@endif
<p>Wersja źródła: {{ $citation['source_version']??'—' }}. Zapis odpowiedzi utrwali odczyt. Ocena merytoryczna należy do audytora.</p></div>
@elseif($field['typ']==='wybor')<select name="payload[pola][{{ $field['klucz'] }}]"><option value="">Wybierz</option>@foreach($field['opcje'] as $option)<option @selected(($answer['pola'][$field['klucz']]??'')===$option)>{{ $option }}</option>@endforeach</select>
@elseif($field['typ']==='data')<input type="date" name="payload[pola][{{ $field['klucz'] }}]" value="{{ $answer['pola'][$field['klucz']]??'' }}">
@else<textarea name="payload[pola][{{ $field['klucz'] }}]" rows="2">{{ $answer['pola'][$field['klucz']]??'' }}</textarea>@endif
</label>
@endforeach
<details><summary>Dowody do tej odpowiedzi</summary>
@forelse($w->records('evidence') as $evidence)<label class="enms-check"><input type="checkbox" name="payload[evidence_ids][]" value="{{ $evidence['id'] }}" @checked(in_array($requirement['id'],$evidence['payload']['requirements']??[],true))>{{ $evidence['symbol'] }} · {{ $evidence['payload']['tytul']??'Bez tytułu' }}</label>@empty<p>Dodaj dowód w rejestrze dowodów, a następnie przypisz go do odpowiedzi.</p>@endforelse
<a href="{{ route($prefix.'show',[$audit,$profile,'tab'=>'dowody']) }}">Otwórz rejestr dowodów</a></details>
</div>
<div data-enms-states="czesciowo niema"><h4>Plan uzupełnienia</h4>@foreach(['opis'=>'Co zamierzacie zrobić','termin'=>'Do kiedy','kto'=>'Kto — nieobowiązkowo'] as $key=>$label)<label>{{ $label }}<textarea rows="2" name="payload[plan][{{ $key }}]">{{ $answer['plan'][$key]??'' }}</textarea></label>@endforeach</div>
<div data-enms-states="niedotyczy"><label>Dlaczego nie dotyczy<textarea name="payload[uzasadnienieNiedotyczy]">{{ $answer['uzasadnienieNiedotyczy']??'' }}</textarea></label><p>{{ $requirement['warunekNiedotyczy']??'' }}</p></div>
@if($editable)<button class="primary">Zapisz odpowiedź</button>@endif
</fieldset></form>
@if(!empty($requirement['pozycjeWspierajace']))<details><summary>Pozycje wspierające ({{ count($requirement['pozycjeWspierajace']) }})</summary>
@foreach($requirement['pozycjeWspierajace'] as $code)@include('audits.enms.declaration',['hostRequirement'=>$requirement['kod'].'-','requirement'=>$library->requirement($library->id('requirement',$code))])@endforeach
</details>@endif
</section>
