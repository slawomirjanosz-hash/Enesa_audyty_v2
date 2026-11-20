<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>5.1 Przywództwo i zaangażowanie</title><link rel="stylesheet" href="{{asset('css/iso-plant-profile.css')}}"><link rel="stylesheet" href="{{asset('css/iso-chapter-four.css')}}"><link rel="stylesheet" href="{{asset('css/enms-leadership.css')}}"></head><body>
<header class="plant-header"><div><h1>5.1 Przywództwo i zaangażowanie</h1><span>{{ $audit->company->name }} · {{ $profile->answers['site.name']['value']??'Zakład' }} · {{ $client?'Strefa klienta':'Panel audytora' }}</span></div><a href="{{ route($client?'client.audits.show':'audits.show',[$audit,'tab'=>'iso50001','section'=>'5-1']) }}">← Wróć do audytu</a></header>
<main class="plant-main">
<details class="plant-card"><summary>Excel ankiety</summary><a href="{{ route($prefix.'excel',[$audit,$profile]) }}">Pobierz odpowiedzi do Excel</a>@if($editable)<form method="post" enctype="multipart/form-data" action="{{ route($prefix.'excel-import',[$audit,$profile]) }}">@csrf<label>Aktualny plik XLSX<input type="file" name="file" accept=".xlsx" required></label><button>Importuj odpowiedzi</button></form>@endif<p>Import nie zatwierdza ankiety. Dowody i przypisania edytuj w rejestrze platformy.</p></details>
@if(session('success'))<div class="plant-notice success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="plant-notice error" role="alert"><strong>Sprawdź zapis:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@php
$normative=array_filter($library->requirements(),fn($r)=>$r['normative']);
$answered=count(array_filter($normative,fn($r)=>!empty($service->one($w,'declaration','requirement_id',$r['id'])['payload']['stan'])));
@endphp
<div class="plant-summary iso-questionnaire-summary"><strong>Wypełnienie: {{ $answered }} / {{ count($normative) }} obowiązków · {{ round(100*$answered/count($normative)) }}%</strong>@include('audits.enms.status',['leadershipStatus'=>$status])</div>
@if($status['changed'])<div class="plant-notice">Dane lub źródła zmieniły się od zatwierdzenia. Sprawdź odpowiedzi i zatwierdź je ponownie. Poprzedni zapis pozostaje w historii.</div>@endif
<nav class="iso-clause-tabs" aria-label="Zakładki ankiety">@foreach($tabs as $key=>$label)<a href="{{ route($prefix.'show',[$audit,$profile,'tab'=>$key]) }}" @if($key===$tab) class="active" aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
@if($tab==='pytania')
<section class="plant-card"><h2>Cztery pytania kontrolne</h2><p>Zakładka dla kierownictwa. Trzynaście zobowiązań normy układa się w cztery pytania; odpowiedź na nie jest deklaracją kierownictwa, a nie oceną — ocenę dołącza konsultant po przejrzeniu dowodów.</p></section>
@foreach($library->data()['PYTANIA_4'] as $q)@include('audits.enms.self-assessment')@endforeach
@elseif($tab==='obowiazki')
<section class="plant-card"><h2>Obowiązki kierownictwa — trzynaście pytań</h2><p>Trzynaście obowiązków, które norma stawia najwyższemu kierownictwu. Przy każdym jedno pytanie, a szczegóły tylko wtedy, gdy są potrzebne. Odpowiedź „jeszcze tego nie ma” jest prawidłowa — wtedy zamiast szczegółów wpisujecie plan.</p></section>
@foreach($library->data()['PYTANIA_4'] as $q)<h2 id="group-{{ $q['id'] }}">{{ $q['nazwa'] }}</h2>@foreach($normative as $requirement)@if($requirement['group']===$q['id'])@include('audits.enms.declaration')@endif @endforeach @endforeach
<h2>Pozycje metodyczne ENESA</h2>@foreach($library->requirements() as $requirement)@if(!$requirement['normative'])@include('audits.enms.declaration')@endif @endforeach
@elseif($tab==='spojnosc')
<section class="plant-card"><h2>Przed przekazaniem</h2><p>Braki formalne i niewyjaśnione niespójności wymagają uzupełnienia. Luki wdrożeniowe opisane planem nie blokują przekazania.</p>
<h3>Braki formalne ({{ count($readiness['missing']) }})</h3>
@forelse($readiness['missing'] as $index=>$missing)
@php
    $target = $readiness['missingTargets'][$index];
    $params = [$audit, $profile, 'tab' => $target['tab']];
    $anchor = $target['anchor'];
    if (isset($target['edit'])) {
        if ($editable) $params['edit'] = $target['edit'];
        else $anchor = $target['readonly_anchor'];
    }
@endphp
<div class="plant-notice enms-readiness-issue"><span>{{ $missing }}</span><a class="plant-button" href="{{ route($prefix.'show',$params) }}#{{ $anchor }}" aria-label="Przejdź — {{ $missing }}">Przejdź</a></div>
@empty<p>Brak przeszkód formalnych.</p>@endforelse
<h3>Luki wdrożeniowe</h3>@forelse($readiness['gaps'] as $gap)<p>{{ $gap }}</p>@empty<p>Nie zgłoszono luk.</p>@endforelse
<h3>Uwagi do sprawdzenia</h3>@forelse($readiness['warnings'] as $warning)<p>{{ $warning }}</p>@empty<p>Brak dodatkowych uwag.</p>@endforelse
@foreach($readiness['inconsistencies'] as $key=>$message)<section class="plant-card" id="explanation-{{ $key }}" data-enms-jump-target><h3>{{ $message }}</h3><form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@include('audits.enms.token')<input type="hidden" name="operation" value="explanation"><input type="hidden" name="payload[key]" value="{{ $key }}"><label>Wyjaśnienie<textarea name="payload[text]" @disabled(!$editable)>{{ $service->one($w,'explanation','key',$key)['payload']['text']??'' }}</textarea></label>@if($editable)<button>Zapisz wyjaśnienie</button>@endif</form></section>@endforeach
@include('audits.enms.questions')
@if($client && $w->actor->hasRole('client_admin') && !$status['submitted'])<form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@include('audits.enms.token')<button class="primary" name="operation" value="submission">Zatwierdź i przekaż ankietę konsultantowi</button></form>@endif
</section>
@elseif($tab==='weryfikacja')
<section class="plant-card"><h2>Deklaracje organizacji</h2>@foreach($normative as $r)<details><summary>{{ $r['iso'] }} · {{ $r['title'] }}</summary><p>{{ $service->one($w,'declaration','requirement_id',$r['id'])['payload']['opis']??'Brak komentarza' }}</p><a href="{{ route($prefix.'show',[$audit,$profile,'tab'=>'obowiazki']) }}#req-{{ $r['kod'] }}">Odpowiedź i dane źródłowe</a></details>@endforeach</section>
@elseif($tab==='ocena')
<section class="plant-card"><h2>Kryteria dowodowe</h2>@foreach($library->requirements() as $r)<details><summary>{{ $r['iso']??$r['kod'] }} · {{ $r['title'] }}</summary>@foreach($r['kryteria'] as $c)<h4>{{ $c['id'] }} · {{ $c['nazwa'] }}</h4><p>{{ $c['opis'] }}</p><p>Źródła: {{ $c['zrodla'] }}. Sposób: {{ $c['sposob'] }}.</p><p>{{ ['normatywny'=>'Kryterium normatywne','warunek'=>'Warunek oceny','praktyka'=>'Praktyka ENESA'][$c['rodzaj']]??$c['rodzaj'] }} {{ $c['waga']==='rdzeniowe'?'— rdzeniowe':'' }}</p>@endforeach</details>@endforeach</section>
@elseif($tab==='historia')
@if(!$client)<section class="plant-card"><h2>Historia operacji</h2><div class="plant-scroll"><table><thead><tr><th>Data</th><th>Operacja</th><th>Rekord</th><th>Wersja</th><th>Rewizja</th><th>Osoba</th></tr></thead><tbody>@foreach($history as $event)<tr><td data-sort-value="{{ $event['created_at'] }}">{{ $event['created_at'] }}</td><td>{{ ['created'=>'Utworzenie','revised'=>'Nowa wersja','denied'=>'Odmowa'][$event['action']]??$event['action'] }}</td><td>{{ $records[$event['record_id']]['symbol']??'—' }}</td><td>{{ $event['record_version'] }}</td><td>{{ $event['workspace_revision'] }}</td><td>{{ $options['users'][$event['actor_id']]??'Użytkownik historyczny' }}</td></tr>@endforeach</tbody></table></div></section>@endif
@endif
@foreach($groups as $kind)@include('audits.enms.register')@endforeach
@if(in_array($tab,['raport','karta']))@include('audits.enms.documents')@endif
@if($tab==='ocena' && !$client && $w->writable)<section class="plant-card"><h2>Zakończenie oceny konsultanta</h2><form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@include('audits.enms.token')<label>Uzasadnienie zakończenia oceny<textarea name="payload[text]" required></textarea></label><button class="primary" name="operation" value="completion">Zakończ ocenę</button></form></section>@endif
@if($tab==='spojnosc' && $w->writable && ($status['submitted'] || !$client))<section class="plant-card"><h2>{{ $client?'Wycofanie zatwierdzenia':'Zwrot do uzupełnienia' }}</h2><form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@include('audits.enms.token')<label>Przyczyna<textarea name="payload[text]" required></textarea></label><button name="operation" value="{{ $client?'withdraw':'return' }}">{{ $client?'Wycofaj zatwierdzenie':'Zwróć ankietę' }}</button></form></section>@endif
</main><script type="module" src="{{ asset('js/table-sort.js') }}"></script><script src="{{ asset('js/enms-leadership.js') }}" defer></script></body></html>
