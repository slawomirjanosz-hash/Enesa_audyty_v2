<div class="system-row" data-report-row>
<input type="hidden" name="{{$reportRoot}}[WNIOSKI][{{$reportIndex}}][id]" value="{{$reportRow['id']??''}}">
<label>Powiązane ustalenia<select multiple name="{{$reportRoot}}[WNIOSKI][{{$reportIndex}}][czynniki][]">@foreach($reportChoices as $code=>$label)<option value="{{$code}}" @selected(in_array($code,$reportRow['czynniki']??[],true))>{{$code}} — {{$label}}</option>@endforeach</select></label>
@foreach(['znaczenie'=>'Co z tego wynika?','decyzja'=>'Co wybieramy?','dzialanie'=>'Co konkretnie robimy?','sprawdzenie'=>'Po czym poznamy, że zadziałało?'] as $key=>$label)<label>{{$label}}<textarea name="{{$reportRoot}}[WNIOSKI][{{$reportIndex}}][{{$key}}]" maxlength="3000" required>{{$reportRow[$key]??''}}</textarea></label>@endforeach
<button type="button" data-report-remove>Usuń wniosek</button></div>
