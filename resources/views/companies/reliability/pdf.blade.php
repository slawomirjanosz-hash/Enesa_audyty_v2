<!doctype html><html lang="pl"><head><meta charset="utf-8"><style>
@page{margin:30px 38px 45px}body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#203c32;line-height:1.35}h1{font-size:20px;margin:5px 0}h2{font-size:13px;border-bottom:1px solid #cddbd3;padding-bottom:4px;margin:12px 0 6px;page-break-after:avoid}p{margin:7px 0;overflow-wrap:break-word}.muted{color:#68796f}.badge{padding:8px;background:#edf3ef;font-size:14px}table{width:100%;border-collapse:collapse;font-size:9px}th,td{padding:6px 5px;text-align:left;border-bottom:1px solid #dce5df}th{background:#eef3f0}tr{page-break-inside:avoid}.footer{position:fixed;bottom:-25px;font-size:8px;color:#6b7771}.copy{white-space:pre-wrap;word-wrap:break-word}
</style></head><body>
@php($s=$report->snapshot)
@if($logo)<img src="{{$logo}}" style="max-width:150px;max-height:50px" alt="Logo">@endif
<p class="muted">DOKUMENT POUFNY - WYŁĄCZNIE DO UŻYTKU WEWNĘTRZNEGO</p>
@if($s['issuer'] ?? null)<p>{{$s['issuer']}}</p>@endif
<h1>Raport wiarygodności firmy</h1><p><strong>{{$s['company']['name']}}</strong><br>NIP: {{$s['company']['nip']}}<br>Data raportu: {{$s['generated_at']}} · Opracował(a): {{$s['author']}}</p>
<div class="badge">Ocena: {{\App\Models\CompanyReliabilityReport::LABELS[$report->status]}}</div>
<p>Ocena pracownika na podstawie wskazanych źródeł. Nie stanowi gwarancji wypłacalności ani decyzji kredytowej. Brak wpisów nie oznacza braku wszystkich długów. Ocena wymaga odświeżenia najpóźniej po 30 dniach.</p>
<h2>Weryfikacja rejestrów online</h2>
<p>Data sprawdzenia: {{data_get($s,'registry.checked_at','Nie wykonano aktualnego sprawdzenia')}}<br>VAT: {{data_get($s,'registry.vat.status','Brak potwierdzonego wyniku')}}<br>Identyfikator VAT: {{data_get($s,'registry.vat.request_id','-')}}<br>KRS: {{data_get($s,'registry.krs.number','Brak potwierdzonego wyniku')}}</p>
<p class="muted">Źródła online: wl-api.mf.gov.pl (wykaz VAT), api-krs.ms.gov.pl (odpis aktualny KRS). Niedostępność źródła nie jest pozytywnym wynikiem kontroli.</p>
<h2>Kontrole pracownika</h2><table><tr><th>Zakres</th><th>Wynik</th></tr>
@foreach(['legal'=>'Status prawny / likwidacja','krz'=>'Upadłość i restrukturyzacja (KRZ)','debt'=>'Zaległe płatności'] as $field=>$label)<tr><td>{{$label}}</td><td>{{['unknown'=>'Nie sprawdzono / brak danych','clear'=>'Nie stwierdzono zagrożeń w sprawdzonych źródłach','risk'=>'Wykryto zagrożenie'][$s['assessment'][$field]]}}</td></tr>@endforeach</table>
<p>Data weryfikacji: {{$s['assessment']['verified_on']}}</p>
<p class="copy">{{$s['assessment']['evidence'] ?: 'Nie wskazano źródeł.'}}</p>
<h2>Dane finansowe (PLN)</h2><p>Zobowiązania bilansowe nie oznaczają przeterminowanych długów. Dane historyczne nie określają dzisiejszego salda rachunków.</p>
@if(empty($s['assessment']['finances']))<p>Brak danych finansowych.</p>@else
<table><thead><tr><th>Rok</th><th>Przychody</th><th>Wynik netto</th><th>Kapitał własny</th><th>Zobowiązania</th></tr></thead><tbody>
@foreach($s['assessment']['finances'] as $row)<tr><td>{{$row['year']}}</td>@foreach(['revenue','profit','equity','liabilities'] as $key)<td>{{isset($row[$key]) ? number_format((float)$row[$key],2,',',' ') : 'Brak danych'}}</td>@endforeach</tr>@endforeach
</tbody></table>@foreach($s['assessment']['finances'] as $row)<p class="copy">Źródło {{$row['year']}}: {{$row['source']}}</p>@endforeach
@endif
<h2>Uzasadnienie i zalecenia</h2><p class="copy">{{$s['assessment']['notes']}}</p>
<div class="footer">Raport poufny · {{$s['company']['name']}} · {{$s['generated_at']}}</div>
</body></html>
