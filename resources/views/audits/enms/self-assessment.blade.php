@php
$selfAssessment = $service->one($w, 'self_assessment', 'question_id', $q['id']);
$answer = $selfAssessment['payload'] ?? [];
$oldSelfAssessment = old('operation') === 'self_assessment' && old('question_id') === $q['id'];
if ($oldSelfAssessment && is_array(old('payload'))) $answer = array_replace($answer, old('payload'));
if (!is_string($answer['komentarz']??'')) $answer['komentarz'] = '';
@endphp
<section class="plant-card enms-declaration {{ $selfAssessment && blank($answer['stan']??null)?'enms-missing':'' }}" id="self-{{ $q['id'] }}">
<p><strong>{{ $q['id'] }}</strong> · {{ count(array_filter($library->data()['ZOBOWIAZANIA'],fn($item)=>$item['pytanie']===$q['id'])) }} zobowiązań</p>
<h2>{{ $q['nazwa'] }}</h2><p>{{ $q['opis'] }}</p>
<details><summary>Podpowiedzi i źródła</summary><p>Czego szuka audytor: {{ $q['dowod'] }}.</p></details>
@if($selfAssessment && $service->changedSinceSubmission($w,$selfAssessment))<p class="plant-notice">Ta odpowiedź zmieniła się od ostatniego przekazania ankiety.</p>@endif
<form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">
@include('audits.enms.token')
<input type="hidden" name="operation" value="self_assessment"><input type="hidden" name="question_id" value="{{ $q['id'] }}">
<fieldset @disabled(!$editable)>
<label class="{{ $oldSelfAssessment && $errors->has('stan')?'enms-invalid':'' }}">Odpowiedź kierownictwa<select name="payload[stan]"><option value="">Wybierz</option>@foreach(\App\Services\Enms\LeadershipLibrary::SELF_ASSESSMENT_STATES as $value=>$label)<option value="{{ $value }}" @selected(($answer['stan']??'')===$value)>{{ $label }}</option>@endforeach</select></label>
<label class="{{ $oldSelfAssessment && $errors->has('komentarz')?'enms-invalid':'' }}">Komentarz — opcjonalnie<textarea name="payload[komentarz]" rows="2" maxlength="12000">{{ $answer['komentarz']??'' }}</textarea></label>
@if($editable)<button class="primary">Zapisz odpowiedź</button>@endif
</fieldset></form>
<p><a href="{{ route($prefix.'show',[$audit,$profile,'tab'=>'obowiazki']) }}#group-{{ $q['id'] }}">Przejdź do obowiązków</a></p>
</section>
