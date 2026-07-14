<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVuSans, sans-serif; font-size:12px; color:#1a1a1a; }
  h1 { font-size:16px; margin:0 0 2px; }
  .sub { color:#666; font-size:11px; margin:0 0 14px; }
  .meta { font-size:11px; color:#444; margin-bottom:16px; line-height:1.5; }
  .qa { margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid #e5e1d8; }
  .q { font-weight:bold; font-size:11px; color:#333; }
  .a { font-size:13px; margin-top:2px; }
  .empty { color:#999; font-style:italic; font-size:12px; }
</style>
</head>
<body>
  <h1>{{ $template->name }}</h1>
  <div class="sub">{{ $broker->name }}</div>
  <div class="meta">
    @if($offerRequest->end_client_company)Firma: {{ $offerRequest->end_client_company }}<br>@endif
    @if($offerRequest->end_client_name)Osoba: {{ $offerRequest->end_client_name }}<br>@endif
    Data wygenerowania: {{ now()->format('d.m.Y H:i') }}
  </div>

  @php $hasAny = false; @endphp
  @foreach($template->flatFields() as $f)
    @php $val = $responses[$f['key']] ?? null; @endphp
    @if($val !== null && $val !== '')
      @php $hasAny = true; @endphp
      <div class="qa">
        <div class="q">{{ $f['label'] }}</div>
        <div class="a">{{ is_array($val) ? implode(', ', $val) : $val }}</div>
      </div>
    @endif
  @endforeach

  @if(!$hasAny)
    <div class="empty">Brak wypełnionych odpowiedzi.</div>
  @endif
</body>
</html>
