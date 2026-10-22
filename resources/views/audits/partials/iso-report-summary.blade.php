@if(!empty($summary['bezPodsumowania']))
<p class="report-note">Raport szczegółowy bez podsumowania dla Zarządu. Uzasadnienie: {{$summary['uzasadnienie']??'Nie podano uzasadnienia.'}}</p>
@else
<h2>Część A — podsumowanie dla Zarządu</h2>
<h3>Ocena sytuacji</h3><p>{{$summary['ocena']??'Podsumowanie dla Zarządu nie zostało opracowane. Raport przedstawia rejestr ustaleń.'}}</p>
@if(!empty($summary['ustalenia']))<h3>Najważniejsze ustalenia</h3>@foreach($summary['ustalenia'] as $finding)<p><strong>{{$finding['czynnik']??''}}</strong> — {{$finding['komentarz']??''}}</p>@endforeach @endif
@if(!empty($summary['decyzje']))<h3>Decyzje rekomendowane i zatwierdzone</h3>@foreach($summary['decyzje'] as $decision)<h4>{{$decision['przedmiot']??''}}</h4><p>{{$decision['rekomendacja']??''}}</p><p>Uzasadnienie: {{$decision['uzasadnienie']??''}}<br>Termin: {{$decision['termin']??'Nie określono'}}<br>Status: {{($decision['status']??'')==='zatwierdzona'?'Zatwierdzona':'Rekomendacja do decyzji'}}@if(($decision['status']??'')==='zatwierdzona')<br>Potwierdzenie decyzji: {{$decision['zatwierdzil']??'Nie podano'}}@endif</p>@endforeach @endif
@if(filled($summary['braki']??null))<h3>Braki wpływające na decyzję</h3><p>{{$summary['braki']}}</p>@endif
@endif
<h2 class="report-part">Część B — analiza szczegółowa</h2>
