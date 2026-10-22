<table><thead><tr><th style="width:34%">Ustalenie</th><th style="width:66%">Treść / potwierdzenie</th></tr></thead><tbody>
@foreach($group['fields'] as $field)@if($field['type']!=='hidden' && $service->visible($field,$review->answers))
@php($value=data_get($review->answers,$field['key']))
@php($display=filled($value)?(is_array($value)?implode(', ',array_map(fn($v)=>$field['options'][$v]??$v,$value)):($field['options'][$value]??$value)):($field['required']?'Do uzupełnienia':'Nie podano / nie dotyczy'))
@foreach(mb_str_split((string)$display,600) as $chunk=>$text)<tr><td>{{$chunk===0?$field['label']:'Ciąg dalszy'}}</td><td style="white-space:pre-wrap">{{$text}}</td></tr>@endforeach
@endif @endforeach
</tbody></table>
