@php($support=app(\App\Services\IsoSupportQuestionnaire::class))
<section class="plant-card">
@if($section==='7-1')
<h2>Bilans czasu — {{data_get($answers,'META.okres') ?: 'określ okres'}}</h2>
<p>Wyniki dla zapisanych danych. Brak czasu jest sygnałem do decyzji, nie blokadą zapisania ankiety.</p>
<div class="plant-scroll"><table style="width:100%"><thead><tr><th>Osoba / funkcja</th><th>Potrzeba (h)</th><th>Dostępne (h)</th><th>Różnica (h)</th></tr></thead><tbody>
@foreach($support->balance($answers) as $row)<tr style="background:{{$row['difference']!==null && $row['difference']<0?'#fff0ee':($row['available']>0 && $row['difference']<.15*$row['available']?'#fff8df':'transparent')}}"><td>{{$row['name']}}</td>@foreach(['required','available','difference'] as $key)<td data-sort-value="{{$row[$key]}}">{{$row[$key]===null?'Nie podano':number_format($row[$key],2,',',' ')}}</td>@endforeach</tr>@endforeach
</tbody></table></div>
@elseif($section==='7-2')
<h2>Matryca kompetencji F-EnMS-24</h2><p>W — poziom wymagany, A — aktualny. Skala 0–4; 0 oznacza „nie dotyczy”. Luka 1: do uwagi; luka od 2: wymagane działanie (progi metodyki ENESA).</p>
@elseif($section==='7-3')
<h2>Plan działań F-EnMS-12 — wspólny z 7.2</h2><p>Pozycje dotyczące świadomości, tylko do odczytu. Edycja planu w punkcie 7.2.</p>
<div class="plant-scroll"><table style="width:100%"><thead><tr><th>Nr</th><th>Temat</th><th>Grupa</th><th>Termin</th><th>Ocena skuteczności</th><th>Status</th></tr></thead><tbody>
@forelse($support->plan($sources) as $row)<tr><td>{{$row['nr']??''}}</td><td>{{$row['temat']??''}}</td><td>{{$row['grupa']??''}}</td><td data-sort-value="{{$row['termin']??''}}">{{$row['termin']??''}}</td><td>{{$row['ocena']??''}}</td><td>{{['planowane'=>'Planowane','wtoku'=>'W toku','zaliczone'=>'Zaliczone','niezaliczone'=>'Niezaliczone','odwolane'=>'Odwołane'][$row['status']??'']??''}}</td></tr>@empty<tr><td colspan="6">Brak pozycji oznaczonych 7.3 w planie 7.2.</td></tr>@endforelse
</tbody></table></div><p>Sprawdzenia świadomości są trwałymi zapisami. Korektę dodaj jako kolejny wpis z odniesieniem do poprzedniego.</p>
@endif
<details><summary>Lista kontrolna — N: norma, M: metodyka</summary><ul>
@foreach($support->schema($section)['checklist'] as $check)<li><strong>{{$check['podstawa']}}</strong> · {{$check['tekst']}}</li>@endforeach
</ul></details>
@if(empty($pdf))
<details><summary>Powiązane dane z wcześniejszych punktów</summary>
@foreach($sources['upstream']??[] as $key=>$source)<h3><a href="{{route($client?'client.audits.system.show':'audits.system.show',[$audit,$profile,$key])}}">{{str_replace('-','.',$key)}} — otwórz ankietę</a></h3>
@foreach(['ROLE'=>'Role','DZIAL'=>'Działania','ZADANIA'=>'Zadania','POZYCJE'=>'Pomiary','SEU'=>'Znaczące wykorzystania energii'] as $root=>$label)
@if(!empty($source[$root]))<h4>{{$label}}</h4><ul>@foreach($source[$root] as $entry)<li>{{$entry['nr']??$entry['id']??''}} · {{$entry['nazwa']??$entry['opis']??$entry['temat']??$entry['co']??''}}</li>@endforeach</ul>@endif
@endforeach
@endforeach</details>
@endif
</section>
