<!doctype html><html lang="pl"><head><meta charset="utf-8"><style>@page{margin:26mm 20mm 23mm}body{font-family:DejaVu Sans,sans-serif;font-size:9px;line-height:1.55;color:#263830}h1{font-size:20px;color:#1a4d3a;line-height:1.25;margin:20px 0}h2{font-size:14px;color:#1a4d3a;margin:24px 0 12px;page-break-after:avoid}h3{font-size:11px;page-break-after:avoid}p{margin:5px 0;overflow-wrap:break-word}.logo{height:48px;max-width:160px}.meta{font-size:8px;color:#5a6d61}.note{padding:10px;background:#f1f5f2}.item{margin:15px 0;page-break-inside:avoid}.hash{font-size:7px;word-wrap:break-word}.footer{position:fixed;bottom:-13mm;font-size:8px;color:#5a6d61}.footer .page:after{content:counter(page)}</style></head><body>
@php
$type=$document['type']; $labels=\App\Services\Enms\LeadershipDocuments::TYPES[$type];
$declarationStates=['jest'=>'Jest i można pokazać','czesciowo'=>'Częściowo','niema'=>'Jeszcze tego nie ma','niedotyczy'=>'Nie dotyczy'];
$assessmentStates=['wykazane'=>'Wykazane','zadeklarowane'=>'Zadeklarowane','niewykazane'=>'Niewykazane','wtoku'=>'W toku','niespelnione'=>'Niespełnione','pozazakresem'=>'Poza zakresem'];
@endphp
<div class="footer">{{ $labels[0] }} · {{ $snapshot['company'] }} · {{ $document['version'] }} · strona <span class="page"></span></div>
@if($snapshot['issuer']['logo']??null)<img class="logo" src="{{ $snapshot['issuer']['logo'] }}" alt="Logo">@else<strong>{{ $snapshot['issuer']['name']??'ENESA' }}</strong>@endif
<h1>{{ $labels[1] }}</h1><p>{{ $labels[0] }} · punkt 5.1 ISO 50001</p><p>{{ $snapshot['company'] }} · {{ $snapshot['site'] }}</p><p class="meta">Wersja {{ $document['version'] }} · {{ substr($snapshot['date'],0,10) }} · {{ $issued?'Wydanie kontrolowane'.($type==='karta'?', niezatwierdzone':''):'Projekt — dokument niewydany' }}</p>
<p class="hash">Odcisk danych SHA-256: {{ $snapshot['data_hash'] }}</p><p class="meta">Biblioteka: {{ $snapshot['library']['version'] }} · generator: {{ $document['generator'] }} · zakres: {{ $snapshot['sources']['scope_version'] }}</p>
@if($type==='raport')
<h2>1. Metryka oceny</h2><p>Zakres oceny: przywództwo i zaangażowanie najwyższego kierownictwa; trzynaście obowiązków a)–m).</p><p>Ocenę wykonał: {{ $snapshot['author'] }}. Ocenę zakończono: {{ $snapshot['status']['completion']['created_at']??'nie zakończono' }}.</p><p>Podstawa oceny: {{ $snapshot['status']['completion']['payload']['text']??'nie zapisano' }}.</p>
<h2>2. Podsumowanie dla kierownictwa</h2><p>Ocenę każdego wymagania wystawia konsultant. Raport nie zawiera procentu zgodności.</p>@foreach($snapshot['requirements'] as $id=>$r)@if($r['normative'])<p><strong>{{ $r['iso'] }} · {{ $r['title'] }}:</strong> {{ $assessmentStates[$one('assessment','requirement_id',$id)['payload']['stan']??'']??'Nie wystawiono oceny' }}</p>@endif @endforeach
<h2>3. Ustalenia i luki</h2>@include('audits.enms.pdf-register',['kind'=>'finding'])@foreach($snapshot['readiness']['gaps'] as $gap)<p>{{ $gap }}</p>@endforeach
<h2>4. Ograniczenia oceny</h2>@foreach($snapshot['limitations']??[] as $missing)<p>{{ $missing }}</p>@endforeach
@foreach($rows('evidence') as $e)@php($v=$one('verification','evidence_id',$e['id'])['payload']??[])@if(($v['dostepnosc']??'')!=='okazany' || ($v['wiarygodnosc']??'')!=='potwierdzona' || ($v['evidence_version']??0)!==$e['version'])<p>{{ $e['symbol'] }}: brak aktualnego potwierdzenia dostępności i wiarygodności dowodu.</p>@endif @endforeach
@foreach($snapshot['sources']['citations'] as $c)@if(!$c['approved'])<p>Punkt {{ str_replace('-','.',$c['section']) }}: brak aktualnego zatwierdzonego dokumentu źródłowego.</p>@endif @endforeach
<h2>5. Wymagane decyzje i działania</h2>@include('audits.enms.pdf-register',['kind'=>'decision'])@include('audits.enms.pdf-register',['kind'=>'task'])
@elseif(in_array($type,['zalacznik','ankieta']))
<h2>1. {{ $type==='zalacznik'?'Do którego raportu należy ten załącznik':'Odpowiedzi organizacji' }}</h2><p>{{ $type==='zalacznik'?'Załącznik do R-EnMS-PRZ-01. Należy czytać razem z raportem o tym samym odcisku danych.':'Wydruk ankiety przedstawia deklaracje organizacji. Nie zastępuje raportu diagnostycznego ani Karty przywództwa.' }}</p>
<h2>2. Pozycje metodyczne ENESA — warunki oceny</h2>@foreach($snapshot['requirements'] as $requirement)@if(!$requirement['normative'])@include('audits.enms.pdf-declaration')@endif @endforeach
<h2>3. Obowiązki a)–m) — co ustalono</h2>@foreach($snapshot['requirements'] as $requirement)@if($requirement['normative'])@include('audits.enms.pdf-declaration')@endif @endforeach
<h2>4. Rejestr dowodów</h2>@include('audits.enms.pdf-register',['kind'=>'evidence'])
@if($type==='zalacznik')<h3>Sprawdzenie dostępności i wiarygodności</h3>@include('audits.enms.pdf-register',['kind'=>'verification'])<h3>Weryfikacja merytoryczna cytatów</h3>@include('audits.enms.pdf-register',['kind'=>'citation_verification'])@endif
@if($type==='zalacznik')<h2>5. Pokrycie kryteriów — zestawienie</h2>@foreach($snapshot['criteria'] as $id=>$c)<div class="item"><h3>{{ $c['id'] }} · {{ $c['nazwa'] }}</h3><p>{{ $c['opis'] }}</p>@php($closure=$one('closure','criterion_id',$id)['payload']??[])<p>Stan: {{ ($closure['stan']??'')==='zamkniete'?'zamknięte przez konsultanta':'otwarte' }}. {{ $closure['uzasadnienie']??'' }}</p>@foreach($rows('coverage') as $coverage)@if($coverage['payload']['criterion_id']===$id)<p>{{ $snapshot['options']['evidence'][$coverage['payload']['evidence_id']]??'Dowód historyczny' }} · {{ ['pelne'=>'pełne','czesciowe'=>'częściowe','zadne'=>'żadne'][$coverage['payload']['zakres']] }}: {{ $coverage['payload']['coPotwierdza']??'' }}. Braki: {{ $coverage['payload']['czegoBrakuje']??'nie wskazano' }}.</p>@endif @endforeach</div>@endforeach @endif
@else
<h2>1. Status dokumentu</h2><p>{{ $issued?'Wydany, niezatwierdzony. Zatwierdzenie tej wersji i data wejścia w życie wymagają odrębnych aktów uprawnionego podmiotu.':'Projekt. Dokument nie jest obowiązujący.' }}</p>
<h2>2. Najwyższe kierownictwo i jego umocowanie</h2>@foreach($snapshot['requirements'] as $requirement)@if($requirement['kod']==='PRZ-00')@include('audits.enms.pdf-declaration')@endif @endforeach
<h2>3. Zasady przyjęte przez kierownictwo</h2><p>Treść zasady, jej przyjęcie i wykazanie wdrożenia są odrębnymi informacjami.</p>
@foreach($snapshot['requirements'] as $id=>$r)@if($r['normative'])
@php($rule=$one('rule','requirement_id',$id)['payload']??[])
<div class="item"><h3>{{ $r['iso'] }} · {{ $r['title'] }}</h3>
<p>{{ ['przyjeta'=>'Zasada przyjęta przez kierownictwo','nieprzyjeta'=>'Zasada nieprzyjęta przez kierownictwo'][$rule['resolution']['state']??'']??'Brak rozstrzygnięcia kierownictwa' }}.</p>
<p>{{ filled($rule['tresc']??null)?$rule['tresc']:'Treść zasady nie została określona — wymaga decyzji kierownictwa.' }}</p>
<p>Podstawa treści: {{ $snapshot['options']['evidence'][$rule['evidence_id']??'']??'Nie wskazano' }}.</p>
<p>Dowód rozstrzygnięcia: {{ $snapshot['options']['evidence'][$rule['resolution']['evidence_id']??'']??'Nie wskazano' }}. {{ $rule['resolution']['text']??'' }}</p>
<p>Wdrożenie: {{ $assessmentStates[$one('assessment','requirement_id',$id)['payload']['stan']??'']??'Nie oceniono' }}.</p></div>@endif @endforeach
<h2>4. Co pozostaje otwarte</h2>@foreach($snapshot['requirements'] as $id=>$r)@if($r['normative'] && ($one('assessment','requirement_id',$id)['payload']['stan']??'')!=='wykazane')<p>{{ $r['iso'] }} · {{ $r['title'] }}: wdrożenie nie zostało wykazane. Przyjęcie Karty nie zamyka tej pozycji.</p>@endif @endforeach @include('audits.enms.pdf-register',['kind'=>'task'])
<h2>5. Kalendarz kontaktów kierownictwa z systemem</h2>@foreach($snapshot['requirements'] as $requirement)@if($requirement['kod']==='PRZ-14')@include('audits.enms.pdf-declaration')@endif @endforeach
<h2>6. Zatwierdzenie i historia</h2><p>Przyczyna przygotowania wersji: {{ $document['reason'] }}</p><p>Ten plik przedstawia stan w chwili {{ $issued?'wydania':'przygotowania projektu' }}. Późniejsze akty zatwierdzenia i wejścia w życie są przechowywane w rejestrze wraz z sumą SHA-256 tego pliku. Wydanego pliku nie zastępuje się regeneracją.</p>
@endif
</body></html>
