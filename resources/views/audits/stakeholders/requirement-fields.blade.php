@foreach(\App\Services\IsoStakeholderQuestionnaire::REQUIREMENT_FIELDS as $key=>$label)
@php($fieldValue=is_scalar($row[$key]??null)?$row[$key]:'')
<label>{{$label}}
@if($key==='kwalifikacja')
<select name="{{$fieldPrefix}}[{{$key}}]"><option value="">Wybierz kwalifikację</option>@foreach(\App\Services\IsoStakeholderQuestionnaire::QUALIFICATIONS as $code=>$text)<option value="{{$code}}" @selected($fieldValue===$code)>{{$text}}</option>@endforeach</select>
@else
<input type="{{in_array($key,['zr_data','data_brzmienia'])?'date':'text'}}" name="{{$fieldPrefix}}[{{$key}}]" value="{{$fieldValue}}" maxlength="{{$key==='wlasciciel'?255:5000}}">
@endif
</label>
@endforeach
<p class="plant-help">Dla wymagania prawnego, umownego lub przyjętego zobowiązania podaj źródło, właściciela, sposób realizacji i sprawdzania. Dla oczekiwania nieprzyjętego zapisz uzasadnienie. Kwalifikację weryfikuje audytor.</p>
