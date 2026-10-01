@php($row=is_array($row)?$row:[])
<div class="plant-question" data-custom-party>
<input type="hidden" name="answers[custom][{{$index}}][id]" value="{{is_scalar($row['id']??null)?$row['id']:''}}">
@foreach(['nazwa'=>'Nazwa strony','wymagania'=>'Wymagania i oczekiwania','jak'=>'Jak uwzględniono w systemie'] as $key=>$label)<label>{{$label}}<textarea name="answers[custom][{{$index}}][{{$key}}]" maxlength="{{$key==='nazwa'?255:($key==='jak'?5000:10000)}}">{{is_scalar($row[$key]??null)?$row[$key]:''}}</textarea></label>@endforeach
<label>Typ<select name="answers[custom][{{$index}}][typ]">@foreach(['wewnętrzna','zewnętrzna'] as $type)<option @selected(($row['typ']??'zewnętrzna')===$type)>{{$type}}</option>@endforeach</select></label><button type="button" data-remove-party>Usuń stronę</button>
</div>
