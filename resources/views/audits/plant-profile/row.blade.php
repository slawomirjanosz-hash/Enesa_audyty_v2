<div class="plant-repeat-row"><input type="hidden" name="{{ $name }}[value][{{ $rowIndex }}][id]" value="{{ $row['id']??'' }}"><div class="plant-fields">
@foreach($q['fields'] as $key=>$field)<label>{{ $field['label'] }}
@if($field['type']==='select')<select name="{{ $name }}[value][{{ $rowIndex }}][{{ $key }}]"><option value="">Wybierz</option>@foreach($field['options'] as $value=>$label)<option value="{{ $value }}" @selected(($row[$key]??null)===$value)>{{ $label }}</option>@endforeach</select>
@else<input type="{{ $field['type'] }}" name="{{ $name }}[value][{{ $rowIndex }}][{{ $key }}]" value="{{ $row[$key]??'' }}" @if($field['type']==='number') min="0" step="any" @else maxlength="500" @endif>@endif</label>@endforeach
</div><button type="button" data-remove-row>Usuń wiersz</button></div>
