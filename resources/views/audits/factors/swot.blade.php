<section id="swot" class="plant-card">
<h2>Analiza SWOT — zarządzanie energią</h2>
@php($swotEditable = !$client && $canWrite && $ready && !$stale && $review->client_approval && in_array($review->status, ['submitted','approved']))
@if($swotEditable)
<p>Podsumuj zatwierdzone czynniki i profil zakładu. Wskaż ich wpływ na zużycie energii oraz wynik energetyczny. Jeśli nie stwierdzono elementów w danej części, wpisz uzasadnienie.</p>
<form data-autosave data-draft-name="swot" data-draft-operation="save_swot" data-draft-exit="{{route($client?'client.audits.show':'audits.show',[$audit,'tab'=>'surveys'])}}" method="post" data-swot-form data-review-action action="{{route($prefix.'update',[$audit,$profile])}}">
@csrf
<input type="hidden" name="lock_version" value="{{$review->lock_version}}">
<input type="hidden" name="source_hash" value="{{$questionnaire->hash($profile)}}">
@foreach(\App\Models\IsoFactorReview::SWOT_FIELDS as $key=>$label)
<label for="swot-{{$key}}">{{$label}}</label>
<p class="plant-help">{{[
 'strengths'=>'Czynniki wewnętrzne sprzyjające poprawie: np. kompetencje zespołu, opomiarowanie, sprawne urządzenia.',
 'weaknesses'=>'Ograniczenia wewnętrzne: np. braki pomiarów, straty energii, niewystarczające zasoby.',
 'opportunities'=>'Możliwości zewnętrzne: np. dostępne technologie, finansowanie, współpraca z dostawcami.',
 'threats'=>'Ryzyka zewnętrzne: np. zmienność cen, dostępność energii, skutki zmiany klimatu.',
 'conclusions'=>'Określ najważniejsze priorytety, proponowane działania, odpowiedzialność i sposób sprawdzenia efektu.'
][$key]}}</p>
<textarea id="swot-{{$key}}" name="swot[{{$key}}]" maxlength="10000" rows="5" @if($errors->has('swot.'.$key)) aria-invalid="true" style="border:2px solid #b91c1c;background:#fff0f0" @endif>{{old('swot.'.$key,$review->swot[$key]??'')}}</textarea>
@endforeach
<button name="operation" value="save_swot">Zapisz analizę SWOT</button>
@if($review->status==='submitted')
<label>Wynik przeglądu / uwagi<textarea name="note" maxlength="3000">{{old('note',$review->review_note)}}</textarea></label>
<button class="primary" name="operation" value="approve">Zatwierdź jako audytor — z analizą SWOT</button>
@else
<p class="plant-help">Zapisanie analizy wycofa zatwierdzenie audytora i wymaga ponownego wygenerowania dokumentu. Zatwierdzenie klienta pozostanie zachowane.</p>
@endif
</form>
@elseif($review->swot)
@if($stale || !$review->client_approval)<p class="plant-notice">Analiza wymaga ponownego przeglądu po zatwierdzeniu aktualnych odpowiedzi klienta.</p>@endif
@foreach(\App\Models\IsoFactorReview::SWOT_FIELDS as $key=>$label)
<h3>{{$label}}</h3><p style="white-space:pre-wrap;overflow-wrap:anywhere">{{$review->swot[$key]??'Do uzupełnienia przez audytora'}}</p>
@endforeach
@else
<p>Analizę SWOT uzupełni audytor lub konsultant po zatwierdzeniu ankiety przez klienta.</p>
@endif
@if($review->swot['author']??null)<p class="plant-help">Autor analizy: {{$review->swot['author']['name']}} · {{\Carbon\Carbon::parse($review->swot['author']['at'])->format('d.m.Y H:i')}}</p>@endif
</section>
