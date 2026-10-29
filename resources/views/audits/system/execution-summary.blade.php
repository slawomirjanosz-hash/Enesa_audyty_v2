@php($execution=app(\App\Services\IsoExecutionQuestionnaire::class))
@if($section==='7-5')
@php($coverage=$execution->documentationCoverage($answers))
<section class="plant-card" id="documentation-coverage"><h2>Pokrycie wymagań normy</h2>
<p>Pozycje wykazu wskazują <span data-coverage-count>{{count(array_filter($coverage,fn($row)=>$row['covered']))}}</span> z {{count($coverage)}} wymaganych punktów. Pokrycie wykazu nie zastępuje oceny dokumentów i dowodów.</p>
@if(!($pdf??false))<p>Uzupełnij pole „Punkt normy” w odpowiednich pozycjach wykazu. Zapisz zmiany, aby trafiły do dokumentu.</p>@endif
<div class="plant-scroll"><table><thead><tr><th>Punkt normy</th><th>Wymagana udokumentowana informacja</th><th>Stan</th></tr></thead><tbody>
@foreach($coverage as $row)<tr data-coverage-clause="{{$row['punkt']}}"><td data-sort-value="{{str_pad(explode('.',$row['punkt'])[0],2,'0',STR_PAD_LEFT).'.'.explode('.',$row['punkt'])[1]}}">{{$row['punkt']}}</td><td>{{$row['co']}}</td><td data-coverage-status>{{$row['covered']?'Wskazano w wykazie':'Brak pozycji w wykazie'}}</td></tr>@endforeach
</tbody></table></div></section>
@endif
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
