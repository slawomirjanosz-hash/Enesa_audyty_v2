<!doctype html><html lang="pl"><head><meta charset="utf-8"><style>
@page{margin:22mm 18mm}body{font-family:DejaVu Sans,sans-serif;color:#24352c;font-size:10.5pt;line-height:1.5}h1{font-size:18pt;color:#174c38;margin:0 0 4px}.meta{color:#69766e;margin-bottom:24px}.box{border:1px solid #d8e0da;border-radius:6px;padding:12px;margin:0 0 10px}.label{font-size:8pt;text-transform:uppercase;color:#718078;font-weight:bold;margin-bottom:4px}.value{font-size:11pt;white-space:pre-wrap}.foot{margin-top:28px;border-top:1px solid #ddd;padding-top:8px;color:#7a857e;font-size:8pt}
</style></head><body>
<h1>ISO 50001 – punkt 3.1</h1><div class="meta"><strong>{{ $workflow['title'] }}</strong><br>{{ $audit->company->name }} · audyt {{ $audit->number }}</div>
@foreach($workflow['fields'] as $key => $field)<div class="box"><div class="label">{{ $field['label'] }}</div><div class="value">{{ filled($answers[$key] ?? null) ? $answers[$key] : 'Nie podano' }}</div></div>@endforeach
<div class="foot">Wygenerowano {{ now()->format('d.m.Y H:i') }} · {{ $generatedBy->name }}. Dokument roboczy stanowiący zapis wdrożenia EnMS.</div>
</body></html>
