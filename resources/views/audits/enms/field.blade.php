@php($value = $value ?? null)
<label id="edit-{{ $kind }}-field-{{ $field['key'] }}" data-enms-jump-target data-enms-field="{{ $field['key'] }}" class="{{ $errors->has($field['key']) || $errors->has('payload.'.$field['key']) ? 'enms-invalid' : '' }}">{{ $field['label'] }}@if($field['required']) * @endif
@if(in_array($field['type'], ['select','multi']))
<select name="payload[{{ $field['key'] }}]{{ $field['type']==='multi'?'[]':'' }}" @if($field['type']==='multi') multiple size="5" @endif>
@if($field['type']!=='multi')<option value="">Wybierz</option>@endif
@foreach($field['choices'] as $key=>$label)<option value="{{ $key }}" @selected(in_array((string)$key, array_map('strval', (array)$value), true))>{{ $label }}</option>@endforeach
</select>
@elseif($field['type']==='textarea')<textarea name="payload[{{ $field['key'] }}]" rows="3" maxlength="12000">{{ $value }}</textarea>
@else<input name="payload[{{ $field['key'] }}]" type="{{ $field['type']==='date'?'date':'text' }}" @if(in_array($field['type'],['number','integer'])) inputmode="decimal" @endif value="{{ $value }}" maxlength="12000">
@endif
</label>
