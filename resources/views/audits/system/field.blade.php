@php
    $key=$field['key']; $value=data_get($answers,$key); $name='answers['.str_replace('.','][',$key).']';
    $visible=$service->visible($field,$answers); $fieldChanges=$client?($review->auditor_changes??[]):($review->client_changes??[]);
@endphp
<div class="plant-question @if($review->exists && $visible && $field['required'] && !filled($value)) plant-unanswered @endif @if(isset($fieldChanges[$key])) system-changed @endif" data-system-field data-key="{{$key}}" data-required="{{$field['required']?1:0}}" data-when='@json($field["when"])' @if(!$visible) hidden @endif>
<label><strong>{{$field['label']}}</strong>@if($field['required']) <span aria-label="wymagane">*</span>@endif
@if($field['type']==='multi')
<input type="hidden" name="{{$name}}" value="">
@foreach($field['options'] as $val=>$label)<label class="plant-check"><input type="checkbox" name="{{$name}}[]" value="{{$val}}" @checked(in_array($val,is_array($value)?$value:[],true))> {{$label}}</label>@endforeach
@elseif($field['options'])
<select name="{{$name}}"><option value="">Wybierz</option>@foreach($field['options'] as $val=>$label)<option value="{{$val}}" @selected($value===$val)>{{$label}}</option>@endforeach</select>
@elseif($field['type']==='textarea')
<textarea name="{{$name}}" maxlength="10000" rows="3">{{is_scalar($value)?$value:''}}</textarea>
@else
<input type="{{$field['type']==='date'?'date':'text'}}" name="{{$name}}" value="{{is_scalar($value)?$value:''}}" maxlength="10000">
@endif</label>
<small class="plant-help">{{$key}}</small>
@if($field['help'])<details><summary>Uzupełnienie i źródło odpowiedzi</summary><p>{{$field['help']}}</p></details>@endif
@if(isset($fieldChanges[$key]))<p class="plant-help">Zmiana: {{$fieldChanges[$key]['by']}}. Poprzednio: {{is_scalar($fieldChanges[$key]['before'])?$fieldChanges[$key]['before']:json_encode($fieldChanges[$key]['before'],JSON_UNESCAPED_UNICODE)}}</p>@endif
@error('answers.'.$key)<p style="color:#b42318" role="alert">{{$message}}</p>@enderror
</div>
