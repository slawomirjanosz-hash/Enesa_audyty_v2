<!doctype html><html lang="pl"><head><meta charset="utf-8"><style>
@page{margin:17mm}body{font-family:DejaVu Sans,sans-serif;color:#26352d;font-size:9pt;line-height:1.42}h1{font-size:18pt;color:#174c38;margin:2px 0 3px}h2{font-size:12pt;color:#174c38;margin:18px 0 7px}.tag{font-weight:bold;color:#19704b}.meta{color:#637168;border-bottom:2px solid #174c38;padding-bottom:9px}.info{margin:12px 0}table{border-collapse:collapse;width:100%;font-size:7.7pt;margin:5px 0 10px}th{background:#174c38;color:#fff;text-align:left;padding:5px}td{border:1px solid #d9e0db;padding:5px;vertical-align:top}.swot td:first-child{width:22%;font-weight:bold;background:#f3f7f4}.foot{margin-top:22px;border-top:1px solid #ddd;padding-top:7px;color:#718078;font-size:7.5pt}
</style></head><body>
<div class="tag">D-EnMS-KON-01</div><h1>Kontekst organizacji i strony zainteresowane</h1><div class="meta">PN-EN ISO 50001:2018 · klauzule 4.1 i 4.2</div>
<div class="info"><b>Organizacja:</b> {{ data_get($answers, 'facts.organization', $audit->company->name) ?: $audit->company->name }}<br><b>Zakres systemu:</b> {{ data_get($answers, 'facts.scope') ?: 'Do uzupełnienia' }}<br><b>Data opracowania:</b> {{ now()->format('d.m.Y') }}</div>
<h2>1. Cel dokumentu</h2><p>Dokument identyfikuje czynniki wewnętrzne i zewnętrzne wpływające na zdolność organizacji do osiągania zamierzonych wyników systemu zarządzania energią oraz strony zainteresowane, których wymagania muszą być uwzględnione. Analiza stanowi podstawę wyznaczenia zakresu systemu, rejestru ryzyk i szans oraz celów energetycznych.</p>
@foreach(['W'=>'2. Kontekst wewnętrzny (kl. 4.1)','Z'=>'3. Kontekst zewnętrzny (kl. 4.1)'] as $prefix=>$title)
<h2>{{ $title }}</h2>
@foreach(config('iso50001-context.dimensions') as $dimension=>$label)
@php($group = collect($factors)->where('dimension',$dimension))
@if(str_starts_with($dimension,$prefix) && $group->isNotEmpty())
<h3>{{ $label }}</h3><table><thead><tr><th>Zidentyfikowany czynnik</th><th>Wpływ</th><th>Skutek dla systemu</th></tr></thead><tbody>@foreach($group as $factor)<tr><td>{{ $factor['text'] }}</td><td>{{ $factor['impact'] }}</td><td>{{ $factor['effect'] }}</td></tr>@endforeach</tbody></table>
@endif
@endforeach
@endforeach
<h2>4. Synteza — analiza SWOT</h2><table class="swot">@foreach(config('iso50001-context.swot') as $key=>$label)<tr><td>{{ $label }}</td><td>{!! nl2br(e(data_get($answers,'swot.'.$key) ?: '[do uzupełnienia]')) !!}</td></tr>@endforeach</table>
<h2>5. Strony zainteresowane (kl. 4.2)</h2><table><thead><tr><th>Strona</th><th>Typ</th><th>Wymagania i oczekiwania</th><th>Wymóg zgodności</th></tr></thead><tbody>@foreach(app(\App\Services\IsoContextService::class)->stakeholders($answers) as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table>
<h2>6. Wnioski — jak kontekst ukształtował system</h2><table><thead><tr><th>Lp.</th><th>Wniosek</th><th>Decyzja projektowa</th><th>Dokument</th></tr></thead><tbody>@for($i=0;$i<4;$i++)<tr><td>{{ $i+1 }}</td>@foreach(['finding','decision','document'] as $key)<td>{{ data_get($answers,'conclusions.'.$i.'.'.$key) ?: '[do uzupełnienia]' }}</td>@endforeach</tr>@endfor</tbody></table>
<h2>7. Dokumenty powiązane i aktualizacja</h2><table><tr><th>Dokument</th><th>Powiązanie</th></tr>@foreach(config('iso50001-context.relatedDocuments') as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</table>
<p>Dokument podlega przeglądowi co najmniej raz w roku, przed Przeglądem Zarządzania, lub przy istotnej zmianie otoczenia organizacji.</p>
<p style="margin-top:30px">Opracował (Energy Manager): ...................................... Data: ....................</p>
<p>Zatwierdził (Zarząd): ...................................... Data: ....................</p>
<div class="foot">Wygenerowano {{ now()->format('d.m.Y H:i') }} · {{ $generatedBy->name }} · dokument roboczy EnMS</div>
</body></html>
