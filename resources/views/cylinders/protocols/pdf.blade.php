<!doctype html>
<html lang="pl"><head><meta charset="utf-8"><title>{{ $number ?? 'Podgląd protokołu' }}</title>
<style>
@page{margin:32px 38px 44px}body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#24362e;line-height:1.45}h1{font-size:23px;color:{{ $snapshot['color'] }};margin:12px 0 4px}h2{font-size:15px;color:{{ $snapshot['color'] }};margin:18px 0 8px}h3{font-size:11px;margin:14px 0 6px}p{margin:5px 0 9px}.muted{color:#64776d}.eyebrow{font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#64776d}.brand{border-bottom:3px solid {{ $snapshot['color'] }};padding-bottom:12px;margin-bottom:20px}.brand img{max-width:110px;max-height:48px;vertical-align:middle;margin-right:16px}.brand strong{font-size:17px;color:{{ $snapshot['color'] }}}table{width:100%;border-collapse:collapse;table-layout:fixed;margin:8px 0 14px}th,td{padding:7px 8px;text-align:left;vertical-align:top;border-bottom:1px solid #dce5df;word-wrap:break-word}th{background:#edf3ef;font-weight:bold;font-size:9px}thead{display:table-header-group}tr{page-break-inside:avoid}.meta td{border:0;background:#f4f7f5}.meta td:first-child{width:27%;color:#64776d}.parameters td{padding:3px 7px;font-size:9px;line-height:1.25}.parameters td:first-child{width:53%;color:#64776d}.device{page-break-before:always}.notice{border-left:3px solid {{ $snapshot['color'] }};padding:9px 12px;background:#f1f5f2;margin:14px 0}.notes{white-space:pre-wrap;word-wrap:break-word}.footer{position:fixed;bottom:-26px;left:0;right:0;color:#718178;font-size:8px;border-top:1px solid #dce5df;padding-top:5px}.page:after{content:counter(page)}.footer .page{float:right}.negative{color:#a32d2d}.draft{color:#9c6823;font-weight:bold}a{color:{{ $snapshot['color'] }};text-decoration:none}
</style></head><body>
@php
    $display = fn ($value) => preg_replace('/(\S{55})(?=\S)/u', '$1 ', (string) ($value ?? '-'));
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d.m.Y') : 'Nie ustalono';
    $results = \App\Models\CylinderInspection::RESULTS;
    $saved = count(array_filter($snapshot['items'], fn ($item) => $item['inspection'] !== null));
@endphp
<div class="footer">{{ $number ?? 'PODGLĄD ROBOCZY' }} · Wzór {{ $snapshot['template_version'] }}<span class="page">Strona </span></div>
<div class="brand">@if($snapshot['logo'])<img src="{{ $snapshot['logo'] }}" alt="Logo">@endif<strong>{{ $display($snapshot['issuer']['name']) }}</strong></div>
<div class="eyebrow">Przeglądy urządzeń</div>
<h1>Protokół zbiorczy przeglądów</h1>
<p class="{{ $number ? '' : 'draft' }}">{{ $number ?? 'PODGLĄD ROBOCZY - dokument niewydany' }}</p>
<table class="meta"><tbody>
<tr><td>Seria</td><td>{{ $display($snapshot['series']['name']) }} · #{{ $snapshot['series']['id'] }}</td></tr>
<tr><td>{{ $number ? 'Data wydania' : 'Data podglądu' }}</td><td>{{ $date($snapshot['date']) }}</td></tr>
<tr><td>Osoba prowadząca</td><td>{{ $display($snapshot['series']['responsible']) }}</td></tr>
<tr><td>Klient serii</td><td>{{ $display($snapshot['client']['name'] ?? 'Bez przypisanego klienta') }}@if($snapshot['client'])<br>{{ $display($snapshot['client']['address']) }} {{ $display($snapshot['client']['city']) }}@if($snapshot['client']['nip'])<br>NIP: {{ $snapshot['client']['nip'] }}@endif @endif</td></tr>
<tr><td>Wystawca</td><td>{{ $display($snapshot['issuer']['name']) }}<br>{{ $display($snapshot['issuer']['address'] ?? null) }} {{ $snapshot['issuer']['postcode'] ?? '' }} {{ $display($snapshot['issuer']['city'] ?? null) }}@if($snapshot['issuer']['nip'] ?? null)<br>NIP: {{ $snapshot['issuer']['nip'] }}@endif</td></tr>
</tbody></table>
<h2>Podsumowanie serii</h2>
<p>Zapisane przeglądy: <strong>{{ $saved }} / {{ count($snapshot['items']) }}</strong>. Liczba urządzeń z wynikiem pozytywnym: <strong>{{ count(array_filter($snapshot['items'], fn ($item) => ($item['inspection']['result'] ?? null) === 'no_findings')) }}</strong>.</p>
<table><thead><tr><th style="width:5%">Lp.</th><th>Urządzenie / znak wytwórczy</th><th>Numer fabryczny</th><th>Data przeglądu</th><th>Wynik</th></tr></thead><tbody>
@foreach($snapshot['items'] as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $display($item['parameters']['name']) }}<br><span class="muted">{{ $display($item['parameters']['manufacturer_mark']) }}</span></td><td>{{ $display($item['parameters']['serial_number']) }}</td><td>{{ $item['inspection'] ? $date($item['inspection']['inspected_at']) : 'Do wykonania' }}</td><td>{{ $results[$item['inspection']['result'] ?? ''] ?? 'Brak wyniku' }}</td></tr>@endforeach
</tbody></table>
<div class="notice">Protokół przedstawia wyniki zapisane przez inspektorów. Szczegóły urządzeń, uwagi i wykaz załączników znajdują się na kolejnych stronach. Dokument nie zawiera podpisu elektronicznego.</div>
@foreach($snapshot['items'] as $item)
<div class="device">
<div class="eyebrow">Urządzenie {{ $loop->iteration }} z {{ count($snapshot['items']) }} · ID {{ $item['device_id'] }}</div>
<h2>{{ $display($item['parameters']['name']) }}</h2>
<p>Nazwa lub znak wytwórcy: <strong>{{ $display($item['parameters']['manufacturer_mark']) }}</strong> · Numer fabryczny: <strong>{{ $display($item['parameters']['serial_number']) }}</strong></p>
<p class="muted">Klient urządzenia: {{ $display($item['client']['name'] ?? 'Bez przypisanego klienta') }}</p>
<h3>Parametry techniczne</h3>
<table class="parameters"><tbody>@foreach(\App\Models\Cylinder::PARAMETER_LABELS as $field=>$label)<tr><td>{{ str_replace(chr(226).chr(128).chr(148), '-', $label) }}</td><td>{{ $display($item['parameters'][$field] ?? null) }}</td></tr>@endforeach</tbody></table>
@if($item['inspection'])
@php($entry = $item['inspection'])
<h3>Wynik przeglądu #{{ $entry['id'] }} · rewizja {{ $entry['revision'] }}</h3>
<table class="meta"><tbody>
<tr><td>Data / wykonujący</td><td>{{ $date($entry['inspected_at']) }} · {{ $display($entry['inspector_name']) }}</td></tr>
<tr><td>Wynik</td><td class="{{ $entry['result'] === 'defects_found' ? 'negative' : '' }}">{{ $results[$entry['result']] ?? $entry['result'] }}</td></tr>
<tr><td>Następny termin</td><td>{{ $date($entry['next_due_at']) }}</td></tr>
<tr><td>Waga / ciśnienie</td><td>{{ $entry['weight_kg'] ?? '-' }} kg / {{ $entry['working_pressure_bar'] ?? '-' }} bar</td></tr>
</tbody></table>
<h3>Zakres i uwagi inspektora</h3>
@foreach(explode("\n", wordwrap($display($entry['observations']), 1200, "\n", false)) as $paragraph)<p class="notes">{{ $paragraph }}</p>@endforeach
<h3>Załączniki przeglądu</h3>
<p>Zdjęcia: {{ count($entry['photos']) }}. Filmy: {{ count($entry['videos']) }}. Materiały są dostępne w aplikacji osobom z uprawnieniami do urządzenia.</p>
@foreach($entry['photos'] as $photo)<p class="muted">Zdjęcie #{{ $photo['id'] }}</p>@endforeach
@foreach($entry['videos'] as $video)<p class="muted">Film #{{ $video['id'] }}: {{ $display($video['title']) }}</p>@endforeach
@else<div class="notice">Przegląd tego urządzenia nie został jeszcze zapisany.</div>@endif
</div>
@endforeach
</body></html>
