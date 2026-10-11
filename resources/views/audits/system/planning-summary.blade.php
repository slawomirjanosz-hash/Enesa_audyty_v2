<section class="plant-card"><h2>Powiązania i wyniki rozdziału 6</h2>
<p class="plant-help">Karty EnPI i EnB są wspólne. Zmiana danych źródłowych wymaga ponownego sprawdzenia powiązanych ankiet. Wzory zapisujemy jako opis metody — nie wykonujemy kodu z formularza.</p>
@foreach($sources['upstream']??[] as $key=>$data)
<p><a href="{{route($prefix.'show',[$audit,$profile,$key])}}">Otwórz {{str_replace('-','.',$key)}}</a> · {{empty($data)?'Brak zapisanych danych':'Dane powiązane dostępne'}}</p>
@endforeach
@foreach($sources['upstream']??[] as $key=>$data)
@foreach(['RYZYKA','SZANSE','SEU','MOZL','CELE','DZIAL'] as $root)
@if(!empty($data[$root]))<details><summary>{{str_replace('-','.',$key)}} · {{$root}} ({{count($data[$root])}})</summary><div class="plant-scroll"><table><thead><tr><th>Identyfikator</th><th>Treść</th><th>Odpowiedzialny / osoby</th></tr></thead><tbody>
@foreach($data[$root] as $row)<tr><td>{{$row['nr']??$row['doCelu']??''}}</td><td>{{$row['nazwa']??$row['opis']??$row['tresc']??''}}</td><td>{{$row['odp']??$row['osoby']??''}}</td></tr>@endforeach
</tbody></table></div></details>@endif
@endforeach @endforeach
@if(in_array($section,['6-2','6-4']))<p><a href="{{route($prefix.'show',[$audit,$profile,'6-6'])}}">Plan zbierania danych 6.6 — najpierw zapisz pozycje, następnie powiąż je z kartami</a></p>
@foreach($sources['plan']??[] as $position)<p><strong>{{$position['nr']??''}}</strong> · {{$position['parametr']??''}}</p>@endforeach @endif
@if(in_array($section,['6-4','6-5']))<p><a href="{{route($prefix.'show',[$audit,$profile,$section==='6-4'?'6-5':'6-4'])}}">{{$section==='6-4'?'Uzupełnij linie bazowe w 6.5':'Dodaj lub zmień wskaźniki w 6.4'}}</a></p>@endif
@if(!empty($sources['cards']))<div class="plant-scroll"><table><thead><tr><th>Identyfikator</th><th>Wskaźnik</th><th>Okres bazowy</th><th>EnB</th></tr></thead><tbody>
@foreach($sources['cards'] as $card)<tr><td>{{$card['nr']??''}}</td><td>{{$card['nazwa']??''}}</td><td>{{$card['okresBazowy']??'Do uzupełnienia'}}</td><td>{{$card['wartoscEnB']??'Do uzupełnienia'}}</td></tr>@endforeach
</tbody></table></div>@endif
@if($section==='6-3')
@php($totals=app(\App\Services\IsoPlanningQuestionnaire::class)->calculations($section,$answers))
<p>Bilans nośników: <strong>{{number_format($totals['energy'],3,',',' ')}} MWh</strong> · Obszary: <strong>{{number_format($totals['areas'],3,',',' ')}} MWh</strong> · Różnica: {{number_format($totals['difference'],2,',',' ')}}%.</p>
<p class="plant-help">Wyniki przeliczają się po zapisie. Współczynnik gazu wpisz zgodnie z wybraną podstawą i źródłem; nie zastępuj go bez sprawdzenia.</p>
<div class="plant-scroll"><table><thead><tr><th>Możliwość poprawy</th><th>Oszczędność MWh/rok</th><th>Oszczędność zł/rok</th><th>Nakłady zł</th><th>SPBT (lata)</th></tr></thead><tbody>
@foreach($answers['MOZL']??[] as $row)@php($spbt=(float)($row['zl']??0)>0?(float)($row['capex']??0)/(float)$row['zl']:null)
<tr><td>{{$row['nr']??''}} · {{$row['nazwa']??''}}</td><td data-sort-value="{{$row['mwh']??''}}">{{$row['mwh']??'—'}}</td><td data-sort-value="{{$row['zl']??''}}">{{$row['zl']??'—'}}</td><td data-sort-value="{{$row['capex']??''}}">{{$row['capex']??'—'}}</td><td data-sort-value="{{$spbt??''}}">{{$spbt===null?'—':number_format($spbt,2,',',' ')}}</td></tr>@endforeach
</tbody></table></div>
@endif
@if($section==='6-1')<div class="plant-scroll"><table><thead><tr><th>Rodzaj</th><th>Identyfikator</th><th>Opis</th><th>Priorytet P × W</th></tr></thead><tbody>
@foreach(['RYZYKA'=>'Ryzyko','SZANSE'=>'Szansa'] as $root=>$label)@foreach($answers[$root]??[] as $row)
@php($priority=(int)($row['P']??0)*(int)($row['W']??0))
<tr><td>{{$label}}</td><td>{{$row['nr']??''}}</td><td>{{$row['opis']??''}}</td><td data-sort-value="{{$priority}}"><strong style="color:{{$priority>=6?'#b42318':($priority>=3?'#996000':'#276245')}}">{{$priority}} · {{$priority>=6?'decyzja kierownictwa':($priority>=3?'działanie':'monitorowanie')}}</strong></td></tr>
@endforeach @endforeach</tbody></table></div>@endif
<h3>Lista kontrolna</h3>
@foreach($service->schema($section)['LISTA_'.str_replace('-','',$section)] as $item)<p><strong>{{$item['podstawa']}}</strong> · {{$item['tekst']}}</p>@endforeach
</section>
