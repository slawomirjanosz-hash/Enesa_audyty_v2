<h2>Polityka energetyczna — {{data_get($answers,'POL.nazwa')}}</h2>
<p>{{data_get($answers,'POL.czym')}}<br>{{data_get($answers,'POL.rola')}}<br>{{data_get($answers,'POL.wstep')}}</p>
@foreach($service->schema('5-2')['ELEMENTY'] as $element)
<h3>{{$element['nazwa']}}</h3><p>{{data_get($answers,'TRESC.'.$element['kod'])?:'Do uzupełnienia'}}</p>
@endforeach
@foreach($answers['DODATKOWE']??[] as $item)<p>{{$item['tresc']??''}}</p>@endforeach
<h3>Utrzymywanie i dostępność polityki</h3>
@foreach($service->schema('5-2')['UTRZYMANIE'] as $item)<p><strong>{{$item['etykieta']}}:</strong> {{data_get($answers,'UTRZ.'.$item['kod'])?:'Do uzupełnienia'}}</p>@endforeach
<p>Wersja: {{data_get($answers,'ZATW.wersja')}} · Obowiązuje od: {{data_get($answers,'ZATW.od')}}<br>Zatwierdził: {{data_get($answers,'ZATW.kto')}}<br>Sposób: {{['podpis'=>'Podpis i data','uchwala'=>'Uchwała','elektronicznie'=>'Obieg elektroniczny'][data_get($answers,'ZATW.sposob')]??'Do uzupełnienia'}} {{data_get($answers,'ZATW.uchwala')}}</p>
