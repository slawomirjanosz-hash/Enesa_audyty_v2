@foreach($sources['upstream']??[] as $sourceSection=>$sourceAnswers)
<details><summary>{{\App\Models\IsoSystemReview::TITLES[$sourceSection]??$sourceSection}} — dane powiązane</summary>
<p><a href="{{route($prefix.'show',[$audit,$profile,$sourceSection])}}">Otwórz punkt źródłowy →</a></p>
@php($hasSource=false)
@foreach(['SEU','KARTY','CELE','ZADANIA','PROJEKTY','ZAKUPY','WYM','PROGRAM','RAPORTY','ODCH','DECYZJE','POZYCJE','WSKAZNIKI'] as $sourceRoot)
@foreach($sourceAnswers[$sourceRoot]??[] as $sourceRow)
@php($hasSource=true)
<p><strong>{{$sourceRow['nr']??$sourceRow['kod']??''}}</strong> {{implode(' · ',array_filter(array_intersect_key($sourceRow,array_flip(['nazwa','obszar','opis','seu','przedmiot','wymaganie','wynik','decyzja','dzialanie','termin','stan','kto','odp'])),fn($v)=>is_scalar($v)&&filled($v)))}}</p>
@endforeach
@endforeach
@unless($hasSource)<p>Brak zapisanych pozycji w źródle.</p>@endunless
</details>
@endforeach
