<h2>Lista kontrolna {{str_replace('-','.',$section)}}</h2>
<p class="plant-help">N — wymaganie według materiału audytora; M — metodyka. Ocena kompletności zapisów nie zastępuje sprawdzenia dowodów.</p>
<div class="plant-scroll"><table><thead><tr><th>Pozycja</th><th>Podstawa</th><th>Stan</th></tr></thead><tbody>
@foreach(app(\App\Services\IsoLeadershipQuestionnaire::class)->checklist($section,$answers,$sources) as $item)<tr><td>{{$item['tekst']}}</td><td>{{$item['podstawa']}}</td><td>{{$item['state']}}</td></tr>@endforeach
</tbody></table></div>
