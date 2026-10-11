@php($execution=app(\App\Services\IsoExecutionQuestionnaire::class))
@php($calculations=$execution->calculations($section,$answers))
@if($calculations)
<section class="plant-card"><h2>Obliczenia na podstawie zapisanych odpowiedzi</h2>
<p>Obliczenia odświeżają się po zapisie. Brak danych nie oznacza zera.</p>
@foreach($calculations as $calculation)
<h3>{{$calculation['label']}}</h3><p>{{$calculation['value']===null?'Brak kompletnych danych':number_format($calculation['value'],2,',',' ')}} {{$calculation['unit']}}</p><p>{{$calculation['note']}}</p>
@endforeach</section>
@endif
@if($execution->checklist($section))
<section class="plant-card"><h2>Lista kontrolna do przeglądu</h2>
<p>Lista pomaga sprawdzić treść i dowody. Samo uzupełnienie pól nie potwierdza spełnienia wymagań.</p>
@foreach($execution->checklist($section) as $item)
<p>{{$item['tekst']??$item['nazwa']??''}} @if(isset($item['podstawa'])) ({{$item['podstawa']}}) @endif</p>
@endforeach</section>
@endif
