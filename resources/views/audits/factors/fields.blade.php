<fieldset @disabled(!$editable)>
<section class="plant-card"><h2>Stan prawny</h2><label>Na jaki dzień sprawdzono przepisy?<input type="date" name="answers[PRAWO][data]" value="{{$display['PRAWO']['data']??''}}"></label><label>Źródła i uwagi z weryfikacji<textarea name="answers[PRAWO][notatka]" maxlength="5000">{{$display['PRAWO']['notatka']??''}}</textarea></label><p class="plant-help">Propozycje biblioteki nie zastępują sprawdzenia aktualnych przepisów i ich zastosowania do przedsiębiorstwa.</p></section>
<section id="climate" class="plant-card"><h2>Istotność zmiany klimatu</h2>
@include('audits.factors.changes',['changeKey'=>'FAKT_KLIMAT_ISTOTNY'])
<label for="climate-choice">Czy zmiana klimatu jest istotna dla zdolności systemu zarządzania energią do osiągania zamierzonych wyników?</label><small class="plant-help">FAKT_KLIMAT_ISTOTNY · ocena w punkcie 4.1</small>
<select id="climate-choice" name="answers[FAKT_KLIMAT_ISTOTNY]"><option value="">Wybierz</option><option value="tak" @selected(($display['FAKT_KLIMAT_ISTOTNY']??'')==='tak')>Tak</option><option value="nie" @selected(($display['FAKT_KLIMAT_ISTOTNY']??'')==='nie')>Nie</option></select><label>Uzasadnienie oceny<textarea name="answers[climate_reason]" maxlength="3000">{{$display['climate_reason']??''}}</textarea></label><p class="plant-help">Zmiana oceny aktualizuje czynniki klimatyczne po zapisaniu.</p></section>
@foreach($questionnaire->schema()['sekcje'] as $section)
<section class="plant-card" id="section-{{$loop->index}}"><h2>{{$section['opis']}}</h2><p class="plant-help">{{$section['nazwa']}}</p>
@foreach($factors as $factor)@if($factor['wymiar']===$section['id'] && $factor['visible'])
@php($row=is_array($display['factors'][$factor['kod']]??null)?$display['factors'][$factor['kod']]:[])
<div class="plant-question @if($review->lock_version && $factor['rodzaj']!=='AUTO' && empty($row['decyzja'])) factor-empty @endif" data-factor="{{$factor['kod']}}">
<h3>{{$factor['kod']}} · {{$factor['rodzaj']==='AUTO'?'Wynik oceny':($factor['rodzaj']==='OCENA'?'Do oceny':'Propozycja')}}</h3><p>{{$factor['tresc']}}</p><p class="plant-help">Wpływ: {{$factor['wplyw']}} · {{$factor['skutek']}}</p>
@if($questionnairePreview ?? false)<p class="preview-condition">Warunek wyświetlenia u klienta: {{$factor['pokaz_gdy']}}</p>@endif
@include('audits.factors.changes',['changeKey'=>$factor['kod']])
@if($factor['rodzaj']==='AUTO')<div class="factor-auto">Wynika z zapisanej oceny klimatu. Treść uwzględniana w dokumencie.</div>
@else
@if(isset($factor['verification']))<p class="plant-notice">{{$factor['verification']}}</p>@endif
<label>Decyzja<select name="answers[factors][{{$factor['kod']}}][decyzja]" data-decision><option value="">Wybierz decyzję</option><option value="potwierdzony" @selected(($row['decyzja']??'')==='potwierdzony')>Potwierdzam — uwzględnij</option><option value="odrzucony" @selected(($row['decyzja']??'')==='odrzucony')>Odrzucam — nie dotyczy</option></select></label>
<label data-reason>Powód odrzucenia<textarea name="answers[factors][{{$factor['kod']}}][powod]" maxlength="3000">{{$row['powod']??''}}</textarea></label>
<details><summary>Treść do dokumentu — uzupełnij lub zmień</summary><textarea name="answers[factors][{{$factor['kod']}}][tresc]" maxlength="5000">{{$row['tresc']??$factor['tresc']}}</textarea></details>
<div data-significance>
<label>Istotność<select name="answers[factors][{{$factor['kod']}}][istotnosc]" data-importance><option value="">Wybierz</option>@foreach(['W'=>'Wysoka','S'=>'Średnia','N'=>'Niska'] as $key=>$label)<option value="{{$key}}" @selected(($row['istotnosc']??'')===$key)>{{$label}}</option>@endforeach</select></label>
@foreach(['znaczenie'=>'Co to znaczy dla systemu?','decyzja_w'=>'Co postanawiamy?','dzialanie'=>'Co konkretnie robimy?','sprawdzenie'=>'Po czym poznamy, że zadziałało?'] as $key=>$label)<label>{{$label}}<textarea data-conclusion name="answers[factors][{{$factor['kod']}}][{{$key}}]" maxlength="5000">{{$row[$key]??''}}</textarea></label>@endforeach
<p class="plant-help">Przy wysokiej istotności wszystkie cztery części wniosku są wymagane.</p></div>
@endif
@if($factor['dependencies'])<details class="factor-source"><summary>Źródło — odpowiedzi z profilu zakładu</summary>@foreach($factor['dependencies'] as $key=>$value)<p><strong>{{$key}}</strong>: {{is_array($value)?implode(', ',$value):($value??'Brak danych')}}</p>@endforeach</details>@endif
</div>@endif @endforeach
</section>@endforeach
<section id="custom" class="plant-card"><h2>Własne czynniki</h2>@include('audits.factors.changes',['changeKey'=>'custom'])<div id="custom-rows">@foreach($display['custom']??[] as $index=>$row)@include('audits.factors.custom-row')@endforeach</div><template id="custom-template">@include('audits.factors.custom-row',['index'=>'__INDEX__','row'=>[]])</template><button type="button" id="add-custom">+ Dodaj własny czynnik</button></section>
</fieldset>
