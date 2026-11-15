@php($fields=$registers->fields($kind,$snapshot['options']))
@forelse($rows($kind) as $record)<div class="item"><h3>{{ $record['symbol'] }}</h3>@foreach($fields as $f)@php($v=$record['payload'][$f['key']]??null)@if(filled($v))<p><strong>{{ $f['label'] }}:</strong> @if($f['type']==='multi'){{ implode(', ',array_map(fn($x)=>$f['choices'][$x]??'Pozycja historyczna',(array)$v)) }}@elseif($f['type']==='select'){{ $f['choices'][$v]??'Pozycja historyczna' }}@else{{ $v }}@endif</p>@endif @endforeach
@if($kind==='task' || $kind==='decision')
@php($deadlines=array_filter($rows('deadline'),fn($t)=>$t['payload']['target_id']===$record['id']))
@forelse($deadlines as $deadline)<p><strong>Termin:</strong> {{ $deadline['payload']['date'] }} · {{ ['enesa'=>'rekomendacja ENESA','organizacja'=>'plan organizacji','zatwierdzony'=>'zatwierdzony, obowiązujący'][$deadline['payload']['origin']] }}</p>@empty<p>Brak terminu obowiązującego.</p>@endforelse
@endif</div>@empty<p class="note">Nie zapisano pozycji w tym rejestrze.</p>@endforelse
