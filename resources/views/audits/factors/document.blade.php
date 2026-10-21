<?php
 $a=$review->answers??[]; $all=$questionnaire->factors($facts,$a); $missing='brak — do uzupełnienia';
 $text=fn($v)=>filled($v)?$v:$missing;
 $format=fn($v)=>is_numeric($v)?number_format((float)$v, min(6,strlen(explode('.',(string)$v)[1]??'')),',',' '):$text($v);
 $basis=fn($r)=>implode(' · ',array_filter($r['podstawa']??[]));
 $summary=$review->swot['podsum']??[];
 $confirmed=collect($all)->filter(fn($f)=>$f['visible']&&($f['rodzaj']==='AUTO'||data_get($a,'factors.'.$f['kod'].'.decyzja')==='potwierdzony'));
 $plantQuestions=collect(app(\App\Services\IsoPlantQuestionnaire::class)->questions($profile->definition))->keyBy('key');
 $factDisplay=function($key) use($facts,$format,$plantQuestions,$text){$v=$facts[$key]??null;$q=$plantQuestions[$key]??[];if($v===null)return $text(null);if($key==='FAKT_ROK_ODNIESIENIA')return (string)$v;return isset($q['options'][$v])?$q['options'][$v]:$format($v).(isset($q['unit'])&&$key!=='ZUZYCIE_TJ'?' '.$q['unit']:'');};
 $factorText=fn($code)=>data_get($a,'factors.'.$code.'.tresc')??collect($all)->firstWhere('kod',$code)['tresc']??collect($a['custom']??[])->firstWhere('id',$code)['tresc']??$code;
?>
<article class="iso-document" data-sortable="false">
<h2>Analiza kontekstu organizacji</h2>
<p><strong>D-EnMS-KON-01 · {{$edition?'Wydanie '.$edition->version_number:'WERSJA ROBOCZA — nie jest wydanym dokumentem'}}</strong></p>
<table><thead><tr><th>Pole</th><th>Wartość</th></tr></thead><tbody>
<tr><th>Organizacja</th><td>{{$profile->answers['organization.name']['value']??$profile->audit?->company?->name??$missing}}</td></tr>
<tr><th>Zakład</th><td>{{$profile->answers['site.name']['value']??$missing}} · {{$profile->answers['site.address']['value']??''}}</td></tr>
<tr><th>Podstawa</th><td>ISO 50001:2018 pkt 4.1 z uwzględnieniem Amd 1:2024; procedura P-EnMS-01 §5.1</td></tr>
<tr><th>Odpowiednik formularza</th><td>F-EnMS-01 (Toolkit, załącznik III-01)</td></tr>
<tr><th>Wersja danych wejściowych</th><td class="fingerprint">{{$fingerprint}}</td></tr>
<tr><th>Zatwierdzenie klienta</th><td>{{$review->client_approval['name']??$missing}} · {{$review->client_approval['at']??''}}</td></tr>
<tr><th>Publikacja przez konsultanta</th><td>{{$edition?->generation_metadata['published_by']??'jeszcze nieopublikowany'}} · {{$edition?->generation_metadata['published_at']??''}}</td></tr>
</tbody></table>
@if($private)
<h3>Część A — podsumowanie dla Zarządu</h3>
<p>{{$summary['ocena']??'Podsumowanie dla Zarządu nie zostało opracowane. Raport bez tej części jest rejestrem ustaleń, a nie materiałem do decyzji.'}}</p>
@if($summary['ustalenia']??[])<table><thead><tr><th>Ustalenie</th><th>Co to znaczy</th></tr></thead><tbody>@foreach($summary['ustalenia'] as $row)<tr><td>{{$factorText($row['czynnik']??'')}}</td><td>{{$text($row['komentarz']??null)}}</td></tr>@endforeach</tbody></table>@endif
@foreach(['rekomendacja'=>'Decyzje wymagane od Zarządu — rekomendacje konsultanta','zatwierdzona'=>'Decyzje już zatwierdzone'] as $status=>$label)
<?php $decisions=collect($summary['decyzje']??[])->filter(fn($r)=>($r['status']??'rekomendacja')===$status); ?>
@if($decisions->isNotEmpty())<h4>{{$label}}</h4><table><thead><tr><th>Decyzja</th><th>{{$status==='zatwierdzona'?'Podstawa zatwierdzenia':'Rekomendacja'}}</th><th>Uzasadnienie i konsekwencja</th><th>Termin</th></tr></thead><tbody>@foreach($decisions as $row)<tr><td>{{$text($row['przedmiot']??null)}}</td><td>{{$text($row[$status==='zatwierdzona'?'zatwierdzil':'rekomendacja']??null)}}</td><td>{{$text($row['uzasadnienie']??null)}}</td><td>{{$text($row['termin']??null)}}</td></tr>@endforeach</tbody></table>@endif
@endforeach
@if($summary['braki']??[])<h4>Braki wpływające na decyzję</h4><ul>@foreach($summary['braki'] as $row)<li>{{$text($row['t']??null)}}</li>@endforeach</ul>@endif
<h3>Część B — analiza szczegółowa</h3>
@endif
<h3>1. Dane wejściowe analizy</h3>
<table><thead><tr><th>Fakt</th><th>Wartość</th></tr></thead><tbody>
@foreach(['FAKT_BRANZA'=>'Działalność','FAKT_PRACOWNICY'=>'Liczba pracowników','FAKT_LOKALIZACJE'=>'Lokalizacje objęte systemem','FAKT_ZMIANY'=>'Praca zmianowa','FAKT_PRODUKCJA'=>'Produkcja w roku odniesienia','FAKT_PRZYCHOD'=>'Przychód ze sprzedaży','FAKT_UDZIAL_ENERGII'=>'Udział energii w kosztach operacyjnych','ZUZYCIE_TJ'=>'Roczne zużycie energii [TJ]','FAKT_MOC_UMOWNA'=>'Moc umowna energii elektrycznej','FAKT_PODLICZNIKI'=>'Opomiarowanie energii elektrycznej','FAKT_SYSTEM_BAZOWY'=>'Wdrożony system zarządzania','FAKT_WIEK_INFRA'=>'Wiek głównych urządzeń energetycznych','FAKT_ROK_ODNIESIENIA'=>'Rok odniesienia danych'] as $key=>$label)
<tr><th>{{$label}}</th><td>{{$factDisplay($key)}}@if($key==='FAKT_PRODUKCJA') {{['t'=>'t','szt'=>'szt.','m2'=>'m²','m3'=>'m³','mwh'=>'MWh','inna'=>$facts['FAKT_PRODUKCJA_INNA']??''][$facts['FAKT_PRODUKCJA_JEDN']??'']??''}}@elseif($key==='FAKT_ZMIANY') zmiany @endif</td></tr>@endforeach
</tbody></table>
@if(($facts['ZUZYCIE_TJ']??null)===null)<p class="warn">Bilans energii jest niekompletny. Nie podajemy sumy częściowej jako pełnego zużycia. {{app(\App\Services\IsoPlantCalculations::class)->energy($facts['FAKT_NOSNIKI']??[])['detail']}}</p>@endif
<table><thead><tr><th>Nośnik</th><th>Ilość</th><th>Jednostka</th><th>Koszt [zł]</th><th>Użyty przelicznik [GJ/jedn.]</th></tr></thead><tbody>
@foreach(is_array($facts['FAKT_NOSNIKI']??null)?$facts['FAKT_NOSNIKI']:[] as $row)<tr><td>{{$text($row['c0']??null)}}</td><td>{{$format(\App\Services\IsoPlantCalculations::number($row['c1']??null))}}</td><td>{{$text($row['c2']??null)}}</td><td>{{$format(\App\Services\IsoPlantCalculations::number($row['c3']??null))}}</td><td>{{$format(\App\Services\IsoPlantCalculations::factors()[$row['c0']??''][$row['c2']??'']??null)}}</td></tr>@endforeach
</tbody></table><p class="opis">Przeliczniki biblioteki ENESA. Dane dotyczą roku odniesienia; kwalifikacja wymagająca średniej wieloletniej liczona jest osobno.</p>
<h3>2. Rozstrzygnięcie w sprawie zmiany klimatu</h3><p>Wynik oceny: {{['tak'=>'zmiana klimatu jest istotna','nie'=>'zmiana klimatu nie jest istotna'][$a['FAKT_KLIMAT_ISTOTNY']??'']??$missing}}.</p><p>{{$text($a['climate_reason']??null)}}</p>
<h3>3. Stan prawny</h3><p>Brzmienie przepisów ustalono na dzień: {{$text($a['PRAWO']['data']??null)}}.</p><p>{{$text($a['PRAWO']['notatka']??null)}}</p>
@foreach(['zewnętrzny'=>'4. Czynniki zewnętrzne','wewnętrzny'=>'5. Czynniki wewnętrzne'] as $type=>$title)
<h3 class="factor-start">{{$title}}</h3>@if($type==='zewnętrzny')<p class="opis">Cztery wymiary zewnętrzne i trzy wewnętrzne oraz skala istotności W/S/N są metodą porządkowania ENESA, a nie wymaganiem ISO 50001. Analiza kontekstu jest wejściem do oceny ryzyk i szans (6.1), nie jej wynikiem.</p>@endif
<table class="factors"><thead><tr><th>Kod</th><th>Czynnik</th><th>Podstawa</th><th>Wpływ</th><th>Istotność</th><th>Skutek dla systemu</th></tr></thead><tbody>
@foreach($questionnaire->schema()['sekcje'] as $section)@if($section['typ']===$type)
<?php $list=$confirmed->where('wymiar',$section['id']); ?>
@if($list->isNotEmpty())<tr class="grupa"><td colspan="6">{{$section['nazwa']}}</td></tr>@endif
@foreach($list as $factor)<?php
 $r=$a['factors'][$factor['kod']]??[];
 $autoBasis=str_contains($factor['pokaz_gdy'],'FAKT_KLIMAT_ISTOTNY')?'ocena istotności zmiany klimatu — sekcja 2':($factor['wymiar']==='zewn. regulacyjny'?'stan prawny ustalony na '.($a['PRAWO']['data']??$missing):'profil zakładu, rok '.($facts['FAKT_ROK_ODNIESIENIA']??$missing));
?>
<tr><td class="kod">{{$factor['kod']}}</td><td>{{$r['tresc']??$factor['tresc']}}</td><td>{{$factor['rodzaj']==='AUTO'?$autoBasis:(($r['istotnosc']??'')==='W'?$text($basis($r)):($r['podstawa']['rodzaj']??$autoBasis))}}</td><td>{{['+'=>'szansa','−'=>'zagrożenie','○'=>'neutralny'][$factor['wplyw']]??$factor['wplyw']}}</td><td>{{$factor['rodzaj']==='AUTO'?'—':(['W'=>'wysoka','S'=>'średnia','N'=>'niska'][$r['istotnosc']??'']??$missing)}}</td><td>{{$r['skutek']??$factor['skutek']}}</td></tr>
@endforeach @endif @endforeach
</tbody></table>@endforeach
<h3>6. Czynniki spoza biblioteki</h3><table><thead><tr><th>Kod</th><th>Czynnik</th><th>Podstawa</th><th>Wymiar / wpływ</th><th>Skutek dla systemu</th></tr></thead><tbody>@forelse($a['custom']??[] as $r)<tr><td class="kod">{{$r['id']}}</td><td>{{$text($r['tresc']??null)}}</td><td>{{$text($basis($r))}}</td><td>{{$text($r['wymiar']??null)}} · {{$r['wplyw']??''}}</td><td>{{$text($r['skutek']??null)}}</td></tr>@empty<tr><td colspan="5">Nie dopisano czynników spoza biblioteki.</td></tr>@endforelse</tbody></table>
<h3>7. Czynniki rozpatrzone i nieuwzględnione</h3><table><thead><tr><th>Kod</th><th>Czynnik</th><th>Uzasadnienie</th></tr></thead><tbody>@foreach($all as $f)@if($f['visible']&&data_get($a,'factors.'.$f['kod'].'.decyzja')==='odrzucony')<tr><td class="kod">{{$f['kod']}}</td><td>{{$factorText($f['kod'])}}</td><td>{{$text(data_get($a,'factors.'.$f['kod'].'.powod'))}}</td></tr>@endif @endforeach</tbody></table>
@if($private)
<h3>8. Analiza SWOT</h3><?php $swot=collect($review->swot??[])->only(['strengths','weaknesses','opportunities','threats'])->filter(); ?>
@if($swot->isEmpty())<p>Metoda SWOT nie została zastosowana — analiza opcjonalna.</p>@else<table><thead><tr><th>Obszar</th><th>Synteza</th></tr></thead><tbody>@foreach($swot as $key=>$value)<tr><th>{{\App\Models\IsoFactorReview::SWOT_FIELDS[$key]}}</th><td>{{$value}}</td></tr>@endforeach</tbody></table>@endif
<h3>9. Wnioski i dalsze postępowanie</h3><table class="conclusions"><thead><tr><th>Id</th><th>Z czynników</th><th>Znaczenie</th><th>Decyzja</th><th>Działanie</th><th>Sprawdzenie</th></tr></thead><tbody>@forelse($registry as $r)<tr><td class="kod">{{$r['id']}}</td><td>{{implode(', ',$r['czynniki'])}}</td>@foreach(['znaczenie','decyzja','dzialanie','sprawdzenie'] as $key)<td>{{$text($r[$key]??null)}}</td>@endforeach</tr>@empty<tr><td colspan="6">{{$missing}}: brak pełnego wniosku.</td></tr>@endforelse</tbody></table>
@endif
<h3>10. Zatwierdzenie i historia zmian</h3>
@foreach(['client_approval'=>'Klient','auditor_approval'=>'Konsultant'] as $key=>$label)<p>{{$label}}: {{$review->$key['name']??$missing}} · {{$review->$key['at']??''}}</p>@endforeach
@if($private&&filled($review->swot['conclusions']??null))<p>{{$review->swot['conclusions']}}</p>@endif
<table><thead><tr><th>Data</th><th>Osoba</th><th>Działanie</th></tr></thead><tbody>@foreach($review->exists?\Illuminate\Support\Facades\DB::table('iso_factor_events')->where('review_id',$review->id)->orderBy('id')->get(['created_at','user_name','action']):[] as $event)<tr><td>{{$event->created_at}}</td><td>{{$event->user_name}}</td><td>{{['save'=>'Zapisano odpowiedzi','submit'=>'Zatwierdzono jako klient','approve'=>'Zatwierdzono jako konsultant','save_swot'=>'Zapisano analizę konsultanta','publish'=>'Opublikowano wydanie','return'=>'Zwrócono do uzupełnienia','withdraw'=>'Wycofano zatwierdzenie'][$event->action]??$event->action}}</td></tr>@endforeach</tbody></table>
</article>
