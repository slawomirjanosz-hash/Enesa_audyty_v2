<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appBrand?->name ?? config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&family=Manrope:wght@400;500;600;700&display=swap">
    <style>
        :root { --brand: {{ $appBrand?->primaryColor() ?? '#1A4D3A' }}; --cream:#f5f2eb; --ink:#18221d; --muted:#65716a; --line:#e5e1d8; }
        * { box-sizing:border-box; } html { scroll-behavior:smooth; } body { margin:0; color:var(--ink); background:#fff; font-family:Lato,sans-serif; }
        .container { width:min(1120px, calc(100% - 48px)); margin:auto; }
        .nav { height:76px; display:flex; align-items:center; justify-content:space-between; gap:22px; }
        .brand { display:flex; gap:12px; align-items:center; color:#fff; font:700 18px Manrope,sans-serif; letter-spacing:.03em; text-decoration:none; }
        .brand img { width:44px; height:44px; object-fit:contain; } .nav-actions { display:flex; align-items:center; gap:18px; }
        .nav-actions a { color:rgba(255,255,255,.78); text-decoration:none; font-size:14px; } .nav-actions a:hover { color:#fff; }
        .login { padding:10px 18px; border:1px solid rgba(255,255,255,.6); border-radius:8px; color:#fff !important; font-weight:700; }
        .login:hover { background:#fff; color:var(--brand)!important; }
        .hero-wrap { color:#fff; background:var(--brand); position:relative; overflow:hidden; }
        .hero-wrap::before,.hero-wrap::after { content:""; position:absolute; border:1px solid rgba(255,255,255,.12); border-radius:50%; pointer-events:none; }
        .hero-wrap::before { width:700px; height:700px; right:-330px; top:-340px; } .hero-wrap::after { width:460px; height:460px; left:-280px; bottom:-300px; }
        .hero { position:relative; z-index:1; min-height:570px; padding:76px 0 88px; text-align:center; display:flex; flex-direction:column; align-items:center; }
        .eyebrow { display:inline-flex; align-items:center; gap:8px; padding:7px 14px; border:1px solid rgba(255,255,255,.28); border-radius:999px; background:rgba(255,255,255,.09); font:700 12px Manrope,sans-serif; letter-spacing:.08em; text-transform:uppercase; }
        h1 { max-width:820px; margin:27px 0 20px; font-size:clamp(38px,5.3vw,64px); line-height:1.08; font-weight:900; letter-spacing:-.035em; }
        .hero p { max-width:630px; margin:0; color:rgba(255,255,255,.78); font-size:18px; line-height:1.65; }
        .hero-buttons { display:flex; justify-content:center; gap:12px; margin-top:34px; } .button { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:14px 22px; border-radius:8px; text-decoration:none; font-weight:700; transition:transform .15s, background .15s; }
        .button:hover { transform:translateY(-2px); } .button-main { color:var(--brand); background:#fff; } .button-alt { color:#fff; border:1px solid rgba(255,255,255,.56); }
        .trust { display:flex; flex-wrap:wrap; gap:20px 28px; justify-content:center; margin-top:62px; font-size:13px; color:rgba(255,255,255,.7); } .trust span { display:inline-flex; gap:7px; align-items:center; } .trust i { font-size:17px; color:#bfe2c9; }
        section { padding:88px 0; } .section-intro { max-width:670px; margin:0 auto 44px; text-align:center; } .kicker { display:inline-block; color:var(--brand); font:700 12px Manrope,sans-serif; text-transform:uppercase; letter-spacing:.09em; } h2 { margin:13px 0; font-size:36px; line-height:1.18; letter-spacing:-.025em; } .section-intro p { margin:0; color:var(--muted); font-size:16px; line-height:1.65; }
        .features { background:var(--cream); } .feature-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; } .feature { padding:28px 25px; background:#fff; border:1px solid var(--line); border-radius:13px; transition:transform .18s, box-shadow .18s; } .feature:hover { transform:translateY(-4px); box-shadow:0 14px 30px rgba(25,42,32,.09); }
        .icon { width:48px; height:48px; display:grid; place-items:center; border-radius:11px; background:color-mix(in srgb, var(--brand) 10%, white); color:var(--brand); font-size:23px; } .feature h3 { margin:19px 0 9px; font-size:17px; } .feature p { margin:0; color:var(--muted); font-size:14px; line-height:1.65; }
        .process-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:36px; } .step { position:relative; padding:0 10px; text-align:center; } .step:not(:last-child)::after { content:""; position:absolute; height:1px; background:var(--line); left:calc(50% + 47px); right:calc(-50% + 47px); top:31px; } .step-num { width:62px; height:62px; display:grid; place-items:center; margin:0 auto 17px; border:1px solid color-mix(in srgb, var(--brand) 28%, white); border-radius:50%; color:var(--brand); background:#fff; font:700 18px Manrope,sans-serif; position:relative; z-index:1; } .step h3 { margin:0 0 8px; font-size:16px; } .step p { margin:0; color:var(--muted); font-size:14px; line-height:1.6; }
        .cta { padding:76px 0; color:#fff; text-align:center; background:var(--brand); } .cta h2 { margin:0 0 14px; font-size:38px; } .cta p { max-width:540px; margin:0 auto 28px; color:rgba(255,255,255,.75); font-size:16px; line-height:1.6; }
        footer { padding:29px 0; color:#88938c; background:#111b16; font-size:13px; } .footer-row { display:flex; justify-content:space-between; gap:12px; align-items:center; } .footer-row strong { color:#e8eee9; }
        @media(max-width:760px) { .container { width:min(100% - 32px, 1120px); } .nav { height:68px; } .nav-actions > a:first-child { display:none; } .hero { min-height:530px; padding:65px 0; } .hero p { font-size:16px; } .trust { margin-top:48px; gap:12px 16px; } section { padding:64px 0; } .feature-grid,.process-grid { grid-template-columns:1fr; } .step { display:grid; grid-template-columns:64px 1fr; text-align:left; column-gap:17px; align-items:start; } .step-num { grid-row:span 2; margin:0; } .step:not(:last-child)::after { width:1px; height:30px; left:41px; top:66px; right:auto; } .step h3 { margin-top:6px; } .step p { grid-column:2; } h2 { font-size:30px; } .footer-row { align-items:flex-start; flex-direction:column; } }
    </style>
</head>
<body>
    <header class="hero-wrap">
        <div class="container nav">
            <a class="brand" href="{{ route('home') }}"><img src="{{ $appBrand?->logoUrl() ?? asset('Logo2.png') }}" alt="Logo {{ $appBrand?->name }}"><span>{{ $appBrand?->name ?? config('app.name') }}</span></a>
            <div class="nav-actions"><a href="#mozliwosci">Możliwości</a><a href="#jak-dziala">Jak to działa</a><a class="login" href="{{ route('login') }}">Zaloguj się</a></div>
        </div>
        <div class="container hero">
            <div class="eyebrow"><i class="ti ti-sparkles"></i> Twój system pracy</div>
            <h1>{{ $appBrand?->tagline ?: 'Prowadź firmę prościej. Wszystko w jednym miejscu.' }}</h1>
            <p>Uporządkuj klientów, oferty, dokumenty i codzienną pracę zespołu — w bezpiecznym, wspólnym panelu.</p>
            <div class="hero-buttons"><a class="button button-main" href="{{ route('login') }}">Przejdź do panelu <i class="ti ti-arrow-right"></i></a><a class="button button-alt" href="#mozliwosci">Poznaj możliwości</a></div>
            <div class="trust"><span><i class="ti ti-shield-check"></i> Kontrola dostępu</span><span><i class="ti ti-file-description"></i> Dokumenty pod ręką</span><span><i class="ti ti-users-group"></i> Praca całego zespołu</span></div>
        </div>
    </header>
    <main>
        <section id="mozliwosci" class="features"><div class="container"><div class="section-intro"><span class="kicker">Jedno miejsce</span><h2>Od pierwszego kontaktu do realizacji</h2><p>Najważniejsze elementy codziennej pracy są dostępne bez przełączania między plikami, skrzynką mailową i kolejnymi narzędziami.</p></div><div class="feature-grid"><article class="feature"><div class="icon"><i class="ti ti-users"></i></div><h3>Klienci i relacje</h3><p>Zachowaj pełny kontekst firmy, kontaktów, rozmów oraz historii współpracy.</p></article><article class="feature"><div class="icon"><i class="ti ti-file-invoice"></i></div><h3>Oferty pod kontrolą</h3><p>Przygotowuj oferty, śledź ich status i trzymaj wszystkie ustalenia w jednym miejscu.</p></article><article class="feature"><div class="icon"><i class="ti ti-folders"></i></div><h3>Dokumenty zespołu</h3><p>Porządkuj ważne pliki przy właściwych klientach i nadaj dostęp tylko właściwym osobom.</p></article></div></div></section>
        <section id="jak-dziala"><div class="container"><div class="section-intro"><span class="kicker">Prosty proces</span><h2>Praca bez zbędnego chaosu</h2><p>System prowadzi zespół przez kolejne etapy, a Ty zawsze widzisz, co jest zrobione i co wymaga uwagi.</p></div><div class="process-grid"><article class="step"><div class="step-num">01</div><h3>Dodaj klienta</h3><p>Zbierz podstawowe dane i rozpocznij współpracę w uporządkowany sposób.</p></article><article class="step"><div class="step-num">02</div><h3>Prowadź sprawę</h3><p>Pracuj na ofertach, dokumentach, zadaniach i informacjach dostępnych dla zespołu.</p></article><article class="step"><div class="step-num">03</div><h3>Zachowaj kontrolę</h3><p>Sprawdzaj postęp pracy i dbaj o to, aby każdy widział wyłącznie potrzebne dane.</p></article></div></div></section>
    </main>
    <section class="cta"><div class="container"><h2>Gotowi do pracy?</h2><p>Zaloguj się do panelu i przejdź do spraw, które wymagają dziś Twojej uwagi.</p><a class="button button-main" href="{{ route('login') }}">Zaloguj się <i class="ti ti-login"></i></a></div></section>
    <footer><div class="container footer-row"><span><strong>{{ $appBrand?->name ?? config('app.name') }}</strong> — system zarządzania firmą</span><span>© {{ now()->year }}</span></div></footer>
</body>
</html>
