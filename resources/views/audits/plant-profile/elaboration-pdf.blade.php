@php
    $brand = new \App\Models\CompanySettings(['primary_color' => $profile->issuer['color'] ?? null]);
    $logo = $profile->issuer['logo'] ?? null;
@endphp
<!doctype html><html lang="pl"><head><meta charset="utf-8">
<style>
@page { margin: 90px 46px 55px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.55; color: #202d29; }
.page-header { position: fixed; top: -66px; left: 0; right: 0; height: 52px; }
.page-header img { max-width: 140px; max-height: 42px; vertical-align: middle; }
.issuer { font-size: 9px; color: #66726e; }
footer { position: fixed; bottom: -32px; left: 0; right: 0; color: #6b7771; font-size: 8px; }
.page:after { content: counter(page); }
h1 { font-size: 23px; line-height: 1.2; color: {{ $brand->primaryColor() }}; margin: 8px 0 12px; }
h2 { font-size: 14px; color: {{ $brand->primaryColor() }}; margin: 20px 0 7px; page-break-after: avoid; }
h3 { font-size: 11px; margin: 12px 0 4px; page-break-after: avoid; }
p { margin: 0 0 9px; overflow-wrap: break-word; }
.meta { background: #f4f6f5; padding: 12px; margin: 14px 0; }
.muted { color: #64736b; font-size: 9px; }
.gap { color: #a52a22; }
.source { font-size: 8px; overflow-wrap: break-word; margin-bottom: 6px; }
.copy { white-space: pre-wrap; }
</style></head><body>
<div class="page-header">@if($logo)<img src="{{ $logo }}" alt="Logo firmy">@else<strong>{{ $profile->issuer['name'] ?? '' }}</strong>@endif <span class="issuer">System zarządzania energią · Wstęp do ISO</span></div>
<footer>Profil #{{ $profile->id }} · wersja {{ $profile->revision }} / zapis {{ $profile->lock_version }} · strona <span class="page"></span></footer>
<h1>Charakterystyka zakładu<br>i podstawy wdrożenia EnMS</h1>
<p>{{ $profile->answers['organization.name']['value'] ?? '' }}<br>{{ $profile->answers['site.name']['value'] ?? '' }}</p>
<div class="meta">Audyt: <strong>{{ $audit->number }}</strong><br>Stan danych: {{ $profile->as_of_date->format('d.m.Y') }} · wersja profilu {{ $profile->revision }} · formularz {{ $profile->definition['version'] ?? '' }}<br>Opracowanie wygenerował: {{ $author }} · {{ $generatedAt->format('d.m.Y H:i') }}</div>
@foreach($report['sections'] as $section)
<h2>{{ $loop->iteration }}. {{ $section['title'] }}</h2>
@foreach($section['paragraphs'] as $paragraph)<p class="{{ str_contains($paragraph, '[do uzupełnienia') ? 'gap' : '' }}">{{ $paragraph }}</p>@endforeach
@endforeach
<h2>Wnioski i dalsze działania</h2>
<p>Na podstawie profilu ustalono poniższe kierunki dalszej pracy. Nie są one potwierdzeniem wdrożenia ani wyniku certyfikacji. Odpowiedzialnych, terminy i zasoby należy uzgodnić w planie działań.</p>
@foreach($report['actions'] as $action)<h3>{{ $loop->iteration }}. {{ $action['title'] }}</h3><p>{{ $action['text'] }} <span class="muted">Powiązanie: {{ $action['target'] }}.</span></p>@endforeach
@if($report['gaps'])<h2>Dane wymagające uzupełnienia lub potwierdzenia</h2><p>Nie przyjęto brakujących informacji jako odpowiedzi „nie” ani jako wartości zerowych. Do dalszej oceny pozostają:</p>@foreach($report['gaps'] as $gap)<p class="gap">{{ $gap }}.</p>@endforeach @endif
<h2>Przegląd i zatwierdzenie danych wejściowych</h2>
@foreach(['client_approval'=>'Klient','auditor_approval'=>'Audytor'] as $field=>$label)<p><strong>{{ $label }}:</strong> {{ $profile->$field['name'] }} · {{ \Carbon\Carbon::parse($profile->$field['at'])->format('d.m.Y H:i') }}</p>@endforeach
@if($profile->review_note)<h3>Ustalenia audytora z przeglądu profilu</h3><p class="copy">{{ $profile->review_note }}</p>@endif
<h2>Podstawa danych i identyfikowalność</h2>
<p class="muted">Opracowanie korzysta z profilu wskazanego powyżej. Źródła poniżej podano zgodnie z deklaracjami; ich wskazanie nie oznacza niezależnego sprawdzenia dokumentów. Szczegółowe odpowiedzi pozostają w osobnym wydruku ankiety.</p>
@foreach($report['evidence'] as $evidence)<p class="source"><strong>{{ $evidence['key'] }}</strong> · {{ $evidence['source'] }}@if(filled($evidence['detail']))<br>{{ $evidence['detail'] }}@endif</p>@endforeach
<p class="muted">Podstawa metodyczna: Bronisław Pytel, „Energia pod kontrolą”, Tom 1 (październik 2026), rozdziały 4–6; Tom 2 (październik 2026, v2), punkty 4.1–4.4, 6.3 i 7.5 oraz plan pierwszych 90 dni. Wykorzystano zasady organizacji pracy i nadzoru nad danymi, nie dane przykładowych przedsiębiorstw.</p>
</body></html>
