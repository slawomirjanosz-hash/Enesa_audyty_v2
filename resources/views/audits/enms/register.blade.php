@php($fields=$service->registers->fields($kind,$options))
@php($rows=array_filter($records,fn($r)=>$r['kind']===$kind))
@php($canEditRegister=$kind==='rule'?$w->writable:$editable)
<section class="plant-card" id="register-{{ $kind }}"><h2>{{ \App\Services\Enms\Registers::TITLES[$kind] }}</h2>
<div class="plant-scroll"><table><thead><tr><th>Symbol</th>@foreach($fields as $f)<th>{{ $f['label'] }}</th>@endforeach<th>Wersja</th><th data-sort-disabled>Akcje</th></tr></thead><tbody>
@foreach(array_reverse($rows) as $record)<tr><td>{{ $record['symbol'] }}@if($service->changedSinceSubmission($w,$record))<p class="plant-notice">Zmiana od przekazania ankiety</p>@endif</td>@foreach($fields as $f)
@php($v=$record['payload'][$f['key']]??null)
<td @if(in_array($f['type'],['date','integer','number'])) data-sort-value="{{ $v }}" @endif>@if($f['type']==='multi'){{ implode(', ',array_map(fn($x)=>$f['choices'][$x]??'Brak pozycji', (array)$v)) }}@elseif($f['type']==='select'){{ $f['choices'][$v??'']??'—' }}@else{{ $v??'—' }}@endif</td>@endforeach
<td data-sort-value="{{ $record['version'] }}">{{ $record['version'] }}</td><td>
@if($canEditRegister && $kind!=='effectiveness')<a href="{{ route($prefix.'show',[$audit,$profile,'tab'=>$tab,'edit'=>$record['id']]) }}#edit-{{ $kind }}">Edytuj</a>@endif
@if(in_array($kind,['task','deadline','decision','authority','effectiveness','rule']))@include('audits.enms.action',['target'=>$record])@endif
@if($kind==='question' && !$client && $w->writable && ($record['payload']['state']??'')==='open')<details><summary>Rozstrzygnij pytanie</summary><form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@include('audits.enms.token')<input type="hidden" name="payload[question_id]" value="{{ $record['id'] }}"><label>Przyczyna<textarea name="payload[text]" required></textarea></label>@if($service->hasCurrentAnswer($w,$record))<button name="operation" value="close_question">Zamknij po odpowiedzi</button>@endif<button name="operation" value="withdraw_question">Wycofaj pytanie</button></form></details>@endif
</td></tr>@endforeach
</tbody></table></div>
@if($kind==='verification')@foreach($rows as $record)@foreach($service->verificationMissing($record['payload']) as $missing)<p class="plant-notice enms-missing">{{ $record['symbol'] }}: {{ $missing }}</p>@endforeach @endforeach @endif
@if($kind==='rule')<p>Zapis treści jest propozycją. Przyjęcie lub odmowę zapisuje uprawnione kierownictwo osobną czynnością. Zmiana treści wymaga nowego rozstrzygnięcia.</p>@foreach($rows as $record)<p>{{ $record['symbol'] }}: {{ ['przyjeta'=>'Zasada przyjęta','nieprzyjeta'=>'Zasada nieprzyjęta'][$record['payload']['resolution']['state']??'']??'Oczekuje na rozstrzygnięcie kierownictwa' }}</p>@endforeach @endif
@if($kind==='closure')@foreach($rows as $record)@if($record['payload']['stan']==='zamkniete')<p class="plant-notice">{{ $record['symbol'] }}: {{ !\App\Services\Enms\SnapshotComparison::same($record['payload']['basis'],$service->basis($w,$record['payload']['criterion_id']))?'Podstawa zmieniła się — zamknięcie wymaga ponownego sprawdzenia.':(($record['payload']['partial_only']??false)?'Zamknięto wyłącznie na podstawie pokryć częściowych. Ograniczenie zostanie wskazane w raporcie.':'Zamknięcie aktualne.') }}</p>@endif @endforeach @endif
@if($kind==='effectiveness')@foreach($rows as $record)<p>{{ $record['symbol'] }}: {{ ($record['payload']['mode']??'')==='samoocena'?'Samoocena':'Ocena niezależna' }} · {{ ($record['payload']['supervised']??false)?'Właściwy tryb oceny potwierdzony':'Oczekuje na zatwierdzenie przez uprawniony nadzór' }}</p>@endforeach @endif
@if($kind==='task')@foreach($rows as $record)@if(!empty($record['payload']['similar_ids']))<p class="plant-notice">{{ $record['symbol'] }}: podobne zadanie już istnieje. Sprawdź powiązania i wystąpienie cyklu.</p>@endif @endforeach @endif
@if($canEditRegister && $kind!=='effectiveness')
@php($editing=($edit['kind']??'')===$kind?$edit:null)
<details id="edit-{{ $kind }}" @if($editing) open @endif><summary>{{ $editing ? 'Edytuj '.$editing['symbol'] : 'Dodaj zapis' }}</summary>
<form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}" data-enms-register="{{ $kind }}">@include('audits.enms.token')<input type="hidden" name="operation" value="record"><input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="record_id" value="{{ $editing['id']??'' }}">
<div class="enms-fields">@foreach($fields as $field)@include('audits.enms.field',['value'=>old('kind')===$kind?old('payload.'.$field['key']):($editing['payload'][$field['key']]??null)])@endforeach</div>
<button class="primary">Zapisz</button></form></details>@endif
</section>
