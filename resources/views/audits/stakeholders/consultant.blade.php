<section id="consultant" class="plant-card @if(isset($changes['consultant'])||isset($changes['analysis'])) stakeholder-change @endif"><h2>Ocena konsultanta i rejestr</h2>
<p>„Tak” w bibliotece oznacza wymóg zgodności po potwierdzeniu, że strona i wymaganie rzeczywiście dotyczą organizacji. Konsultant weryfikuje konkretną podstawę prawną lub umowną. Nierozstrzygnięty kandydat blokuje zatwierdzenie klienta.</p>
@if($consultantEditable)<form method="post" data-questionnaire-form action="{{route($prefix.'update',[$audit,$profile])}}">@csrf<input type="hidden" name="lock_version" value="{{$review->lock_version}}"><input type="hidden" name="source_hash" value="{{$hash}}"><input type="hidden" name="operation" value="consultant">@endif
@forelse($register as $row)
@php($code=$row['kod'])
<article class="plant-question"><h3>{{$code}} · {{$row['nazwa']}}</h3><p class="stakeholder-read">{{$row['wymagania']}}</p>
@if($consultantEditable)
@if($row['classification']==='KANDYDAT')<label>Wymóg zgodności<select name="consultant[{{$code}}][zgodnosc]">@foreach(['pending'=>'Nierozstrzygnięte','tak'=>'Tak','nie'=>'Nie — oczekiwanie'] as $key=>$label)<option value="{{$key}}" @selected($safeOld('consultant.'.$code.'.zgodnosc',$row['zgodnosc'])===$key)>{{$label}}</option>@endforeach</select></label><label>Uzasadnienie decyzji<textarea name="consultant[{{$code}}][reason]" maxlength="3000">{{$safeOld('consultant.'.$code.'.reason',$row['consultant']['reason']??'')}}</textarea></label>@else<p>Wymóg zgodności: <strong>{{$row['zgodnosc']}}</strong> — klasyfikacja biblioteki</p>@endif
@foreach(['legal_basis'=>'Podstawa prawna lub umowna (konkretny przepis, umowa, decyzja)','owner'=>'Osoba / rola odpowiedzialna','deadline'=>'Termin spełnienia wymagania (jeżeli określono)'] as $key=>$label)<label>{{$label}}<input type="{{$key==='deadline'?'date':'text'}}" name="consultant[{{$code}}][{{$key}}]" value="{{$safeOld('consultant.'.$code.'.'.$key,$row['consultant'][$key]??'')}}" @if($key!=='deadline') maxlength="{{$key==='owner'?255:5000}}" @endif></label>@endforeach
@else
<p>Wymóg zgodności: <strong>{{['tak'=>'Tak','nie'=>'Nie','pending'=>'Do rozstrzygnięcia przez konsultanta'][$row['zgodnosc']]}}</strong></p>
@foreach(['reason'=>'Uzasadnienie','legal_basis'=>'Podstawa prawna / umowna','owner'=>'Odpowiedzialny','deadline'=>'Termin'] as $key=>$label)<p class="stakeholder-read">{{$label}}: {{$row['consultant'][$key]??'Do uzupełnienia przez konsultanta'}}</p>@endforeach
@endif
</article>@empty<p>Zapisz najpierw potwierdzone strony w ankiecie. Pojawią się tutaj do oceny konsultanta.</p>@endforelse
<h3>Analiza i przegląd rejestru</h3>
@foreach(['summary'=>'Wnioski, powiązanie wymagań z celami, ryzykami i planami działań','owner'=>'Odpowiedzialny za przegląd rejestru','reviewed_on'=>'Data przeglądu','next_review'=>'Termin kolejnego przeglądu'] as $key=>$label)
@if($consultantEditable)<label>{{$label}}@if($key==='summary')<textarea name="analysis[{{$key}}]" maxlength="10000">{{$safeOld('analysis.'.$key,$review->analysis[$key]??'')}}</textarea>@else<input type="{{in_array($key,['reviewed_on','next_review'])?'date':'text'}}" name="analysis[{{$key}}]" value="{{$safeOld('analysis.'.$key,$review->analysis[$key]??'')}}" @if($key==='owner') maxlength="255" @endif>@endif</label>@else<h4>{{$label}}</h4><p class="stakeholder-read">{{$review->analysis[$key]??'Do uzupełnienia przez konsultanta'}}</p>@endif
@endforeach
@if($consultantEditable)<p class="plant-help">Zmiana oceny konsultanta wymaga ponownego zatwierdzenia klienta. Nie powstaje nowa ankieta.</p><button class="primary" type="submit">Zapisz ocenę konsultanta</button></form>@endif
</section>
