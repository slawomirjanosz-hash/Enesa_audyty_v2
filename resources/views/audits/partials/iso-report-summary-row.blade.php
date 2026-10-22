<div class="system-row" data-report-row>
@php($reportPrefix=$reportRoot.'[PODSUM]['.$reportCollection.']['.$reportIndex.']')
@if($reportCollection==='ustalenia')
<label>Ustalenie źródłowe<select name="{{$reportPrefix}}[czynnik]"><option value="">Wybierz</option>@foreach($reportChoices??[] as $code=>$label)<option value="{{$code}}" @selected(($reportRow['czynnik']??'')===$code)>{{$code}} · {{$label}}</option>@endforeach @if(filled($reportRow['czynnik']??null)&&!array_key_exists($reportRow['czynnik'],$reportChoices??[]))<option selected value="{{$reportRow['czynnik']}}">{{$reportRow['czynnik']}} — źródło wymaga przeglądu</option>@endif</select></label>
<label>Znaczenie dla Zarządu<textarea name="{{$reportPrefix}}[komentarz]" maxlength="3000">{{$reportRow['komentarz']??''}}</textarea></label>
@else
@foreach(['przedmiot'=>'Przedmiot decyzji','rekomendacja'=>'Rekomendacja','uzasadnienie'=>'Uzasadnienie','termin'=>'Termin','zatwierdzil'=>'Kto zatwierdził — dokument i data'] as $key=>$label)<label>{{$label}}<textarea name="{{$reportPrefix}}[{{$key}}]" maxlength="{{$key==='termin'?255:($key==='zatwierdzil'?1000:3000)}}">{{$reportRow[$key]??''}}</textarea></label>@endforeach
<label>Status decyzji<select name="{{$reportPrefix}}[status]"><option value="rekomendacja" @selected(($reportRow['status']??'rekomendacja')==='rekomendacja')>Rekomendacja</option><option value="zatwierdzona" @selected(($reportRow['status']??'')==='zatwierdzona')>Zatwierdzona — podaj podstawę powyżej</option></select></label>
@endif
<button type="button" data-report-remove>Usuń pozycję</button>
</div>
