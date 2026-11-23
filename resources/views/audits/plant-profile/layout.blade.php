<!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profil zakładu · ISO 50001</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/iso-plant-profile.css') }}?v={{filemtime(public_path('css/iso-plant-profile.css'))}}"><style>:root{--brand:{{ $appBrand?->primaryColor() ?: '#1a4d3a' }}}</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head><body>
<header class="plant-header"><div><small>ISO 50001 · Wstęp</small><h1>Profil zakładu</h1><span>{{ $audit->company->name }} · {{ $client ? 'Strefa klienta' : 'Panel audytora' }}</span></div><nav class="plant-header-links" aria-label="Powrót do audytu"><a href="{{ route($client?'client.audits.show':'audits.show', ['audit'=>$audit,'tab'=>'iso50001','section'=>'intro']) }}">← Wróć do audytu</a><a href="{{ route($client?'client.audits.show':'audits.show', ['audit'=>$audit,'tab'=>'iso50001','section'=>'intro']) }}#plant-profiles">Wszystkie profile</a></nav></header>
<main class="plant-main">
@if(session('success'))<div class="plant-notice success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="plant-notice error" role="alert"><strong>Sprawdź formularz:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
@include('partials.field-validation')
@include('partials.questionnaire-navigation')
</main><script type="module" src="{{ asset('js/table-sort.js') }}"></script><script src="{{ asset('js/iso-plant-energy.js') }}" defer></script><script src="{{ asset('js/iso-plant-profile.js') }}?v={{filemtime(public_path('js/iso-plant-profile.js'))}}" defer></script>@include('partials.form-drafts')
</body></html>
