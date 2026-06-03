<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENESA — System zarządzania audytami energetycznymi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&family=Manrope:wght@400;500;600;700&display=swap">
    <style>
        :root {
            --sidebar-bg: #1A4D3A;
            --cream: #F4F1EA;
            --white: #FFFFFF;
            --green-main: #2E7D32;
            --text-dark: #1A1A1A;
            --border: #E5E1D8;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Lato', sans-serif; color: var(--text-dark); background: var(--white); }

        /* NAVBAR */
        .navbar { position: sticky; top: 0; z-index: 500; background: var(--sidebar-bg); height: 64px; padding: 0 48px; display: flex; align-items: center; gap: 40px; }
        .navbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
        .navbar-brand img { width: 38px; height: 38px; object-fit: contain; }
        .navbar-brand span { font-family: 'Lato', sans-serif; font-weight: 700; font-size: 18px; color: #F5F0E8; letter-spacing: 0.04em; }
        .navbar-links { display: flex; align-items: center; gap: 28px; flex: 1; }
        .navbar-links a { font-size: 14px; font-weight: 400; color: rgba(255,255,255,0.7); text-decoration: none; transition: color .2s; }
        .navbar-links a:hover { color: #fff; }
        .navbar-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .btn-outline-nav { padding: 8px 18px; border: 1px solid rgba(255,255,255,0.5); border-radius: 7px; background: transparent; color: #F5F0E8; font-family: 'Lato', sans-serif; font-size: 14px; font-weight: 700; text-decoration: none; cursor: pointer; transition: background .2s, border-color .2s; }
        .btn-outline-nav:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.8); }
        .btn-filled-nav { padding: 8px 18px; border: 1px solid #F5F0E8; border-radius: 7px; background: #F5F0E8; color: #1A4D3A; font-family: 'Lato', sans-serif; font-size: 14px; font-weight: 700; cursor: pointer; transition: background .2s, transform .15s; }
        .btn-filled-nav:hover { background: #fff; transform: translateY(-1px); }

        /* HERO */
        .hero { background: var(--sidebar-bg); padding: 96px 48px; text-align: center; }
        .badge-pill { display: inline-block; background: rgba(168,213,181,0.15); border: 1px solid rgba(168,213,181,0.3); color: #A8D5B5; font-size: 13px; font-weight: 600; font-family: 'Manrope', sans-serif; padding: 6px 18px; border-radius: 999px; margin-bottom: 28px; }
        .hero h1 { font-family: 'Lato', sans-serif; font-weight: 900; font-size: 48px; line-height: 1.15; color: #F5F0E8; max-width: 760px; margin: 0 auto 24px; }
        .hero p { font-size: 17px; line-height: 1.7; color: rgba(245,240,232,0.7); max-width: 620px; margin: 0 auto 40px; }
        .hero-buttons { display: flex; align-items: center; justify-content: center; gap: 14px; }
        .btn-hero-filled { padding: 14px 28px; background: #F5F0E8; color: #1A4D3A; border: none; border-radius: 8px; font-family: 'Lato', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .2s, transform .15s; text-decoration: none; }
        .btn-hero-filled:hover { background: #fff; transform: translateY(-2px); }
        .btn-hero-outline { padding: 14px 28px; background: transparent; color: #F5F0E8; border: 1px solid rgba(255,255,255,0.6); border-radius: 8px; font-family: 'Lato', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .2s, border-color .2s; text-decoration: none; }
        .btn-hero-outline:hover { background: rgba(255,255,255,0.08); border-color: #fff; }

        /* JAK TO DZIALA */
        .section-system { background: var(--cream); padding: 80px 48px; }
        .section-header { text-align: center; margin-bottom: 52px; }
        .section-badge { display: inline-block; background: rgba(26,77,58,0.08); border: 1px solid rgba(26,77,58,0.2); color: #1A4D3A; font-size: 12px; font-weight: 700; font-family: 'Manrope', sans-serif; padding: 5px 16px; border-radius: 999px; margin-bottom: 18px; text-transform: uppercase; letter-spacing: 0.06em; }
        .section-title { font-family: 'Lato', sans-serif; font-weight: 900; font-size: 34px; color: var(--sidebar-bg); margin-bottom: 14px; }
        .section-desc { font-size: 16px; line-height: 1.7; color: #5a6a60; max-width: 580px; margin: 0 auto; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 1100px; margin: 0 auto; }
        .feature-card { background: var(--white); border-radius: 12px; padding: 32px 28px; display: flex; gap: 20px; align-items: flex-start; border: 1px solid var(--border); transition: box-shadow .2s, transform .2s; }
        .feature-card:hover { box-shadow: 0 6px 24px rgba(26,77,58,0.10); transform: translateY(-2px); }
        .feature-icon { width: 48px; height: 48px; background: rgba(26,77,58,0.08); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .feature-icon i { font-size: 24px; color: #1A4D3A; }
        .feature-card h3 { font-family: 'Lato', sans-serif; font-weight: 700; font-size: 17px; color: var(--text-dark); margin-bottom: 8px; }
        .feature-card p { font-size: 14px; line-height: 1.65; color: #5a6a60; }

        /* CYTAT */
        .section-quote { background: var(--white); padding: 60px 48px; }
        .quote-inner { max-width: 820px; margin: 0 auto; }
        .quote-inner blockquote { border-left: 4px solid var(--green-main); padding-left: 32px; }
        .quote-text { font-family: 'Lato', sans-serif; font-style: italic; font-size: 20px; line-height: 1.65; color: var(--text-dark); margin-bottom: 20px; }
        .quote-author { font-size: 14px; font-weight: 700; color: #5a6a60; }

        /* OFERTA */
        .section-oferta { background: var(--cream); padding: 80px 48px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; max-width: 1100px; margin: 0 auto; }
        .offer-card { background: var(--white); border-radius: 12px; padding: 28px 24px; border-left: 3px solid #1A4D3A; border-top: 1px solid var(--border); border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); transition: transform .2s, border-left-color .2s, box-shadow .2s; }
        .offer-card:hover { transform: translateY(-2px); border-left-color: #43A047; box-shadow: 0 6px 24px rgba(26,77,58,0.10); }
        .offer-number { font-family: 'Manrope', sans-serif; font-size: 12px; font-weight: 700; color: #A8D5B5; letter-spacing: 0.08em; margin-bottom: 12px; }
        .offer-icon { font-size: 26px; color: #1A4D3A; margin-bottom: 12px; }
        .offer-card h3 { font-family: 'Lato', sans-serif; font-weight: 700; font-size: 16px; color: var(--text-dark); margin-bottom: 10px; }
        .offer-card p { font-size: 13px; line-height: 1.65; color: #5a6a60; margin-bottom: 18px; }
        .offer-link { font-size: 13px; font-weight: 700; color: #1A4D3A; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .offer-link:hover { text-decoration: underline; }

        /* CTA */
        .section-cta { background: var(--sidebar-bg); padding: 80px 48px; text-align: center; }
        .section-cta h2 { font-family: 'Lato', sans-serif; font-weight: 700; font-size: 36px; color: #F5F0E8; margin-bottom: 16px; }
        .section-cta p { font-size: 16px; color: rgba(245,240,232,0.65); margin-bottom: 36px; }

        /* FOOTER */
        .footer { background: #0D3B12; padding: 56px 48px; }
        .footer-grid { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 48px; max-width: 1100px; margin: 0 auto; }
        .footer-brand-row { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .footer-brand-row img { width: 36px; height: 36px; object-fit: contain; }
        .footer-brand-name { font-family: 'Lato', sans-serif; font-weight: 700; font-size: 17px; color: #F5F0E8; }
        .footer-desc { font-size: 13px; color: rgba(245,240,232,0.55); line-height: 1.65; margin-bottom: 24px; }
        .footer-contact-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .footer-contact-list li { display: flex; align-items: center; gap: 10px; font-size: 13px; color: rgba(245,240,232,0.65); }
        .footer-contact-list li a { color: rgba(245,240,232,0.65); text-decoration: none; transition: color .2s; }
        .footer-contact-list li a:hover { color: #A8D5B5; }
        .footer-contact-list i { font-size: 16px; color: #A8D5B5; }
        .footer-col h4 { font-family: 'Manrope', sans-serif; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(245,240,232,0.4); margin-bottom: 18px; }
        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .footer-col ul li a { font-size: 14px; color: rgba(245,240,232,0.65); text-decoration: none; transition: color .2s; }
        .footer-col ul li a:hover { color: #A8D5B5; }
        .footer-copy { background: #091F0B; text-align: center; padding: 16px 48px; font-size: 12px; color: rgba(245,240,232,0.35); }

        /* MODAL */
        #modalOverlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9000; align-items: center; justify-content: center; }
        #modalOverlay.open { display: flex; }
        .modal-box { background: #fff; border-radius: 14px; padding: 40px; max-width: 480px; width: 95%; max-height: 90vh; overflow-y: auto; position: relative; }
        .modal-close { position: absolute; top: 16px; right: 20px; background: none; border: none; font-size: 22px; color: #888; cursor: pointer; line-height: 1; }
        .modal-close:hover { color: #333; }
        .modal-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 22px; }
        .modal-brand img { width: 40px; height: 40px; object-fit: contain; }
        .modal-brand span { font-family: 'Lato', sans-serif; font-weight: 700; font-size: 16px; color: #1A4D3A; }
        .modal-box h2 { font-family: 'Manrope', sans-serif; font-size: 22px; font-weight: 700; color: #1A4D3A; margin-bottom: 8px; }
        .modal-box > p { font-size: 13px; color: #5a6a60; margin-bottom: 24px; line-height: 1.6; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #3a3a3a; margin-bottom: 5px; }
        .form-row { display: flex; gap: 8px; }
        .form-row input { flex: 1; }
        .form-input { width: 100%; background: #FAFAF6; border: 1px solid #D0CCC0; border-radius: 6px; padding: 10px 12px; font-size: 14px; font-family: 'Lato', sans-serif; outline: none; transition: border-color .2s; }
        .form-input:focus { border-color: #2E7D32; }
        .btn-gus { padding: 10px 14px; background: rgba(26,77,58,0.08); border: 1px solid rgba(26,77,58,0.25); border-radius: 6px; color: #1A4D3A; font-family: 'Lato', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: background .2s; flex-shrink: 0; }
        .btn-gus:hover { background: rgba(26,77,58,0.15); }
        .modal-divider { border: none; border-top: 1px solid #E5E1D8; margin: 20px 0; }
        .btn-submit { width: 100%; background: #1A4D3A; color: #F5F0E8; border: none; border-radius: 8px; padding: 13px; font-family: 'Manrope', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .2s; margin-top: 4px; }
        .btn-submit:hover { background: #2E7D32; }
        .modal-login-link { display: block; text-align: center; font-size: 13px; color: #888; margin-top: 16px; text-decoration: none; }
        .modal-login-link a { color: #1A4D3A; font-weight: 700; text-decoration: none; }
        .modal-login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="{{ url('/') }}" class="navbar-brand">
        <img src="{{ asset('Logo2.png') }}" alt="ENESA logo">
        <span>ENESA</span>
    </a>
    <div class="navbar-links">
        <a href="#oferta">Nasza oferta</a>
        <a href="#system">Jak to działa</a>
        <a href="#kontakt">Kontakt</a>
    </div>
    <div class="navbar-actions">
        <a href="{{ route('login') }}" class="btn-outline-nav">Zaloguj się</a>
        <button class="btn-filled-nav" onclick="openModal()">Zarejestruj firmę</button>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="badge-pill">Niezależne audyty energetyczne dla przemysłu</div>
    <h1>System zarządzania audytami energetycznymi</h1>
    <p>Od zapytania przez ofertę do gotowego raportu &mdash; wszystko w jednym miejscu. Bez sprzedaży sprzętu, bez konfliktów interesów.</p>
    <div class="hero-buttons">
        <button class="btn-hero-filled" onclick="openModal()">Zarejestruj firmę</button>
        <a href="{{ route('login') }}" class="btn-hero-outline">Zaloguj się</a>
    </div>
</section>

<!-- JAK TO DZIALA -->
<section id="system" class="section-system">
    <div class="section-header">
        <div class="section-badge">Jak to działa</div>
        <h2 class="section-title">Prosty proces &mdash; od zapytania do raportu</h2>
        <p class="section-desc">Rejestrujesz firmę, wybierasz audyt, otrzymujesz ofertę i przystępujesz do audytu. Nasi specjaliści prowadzą Cię przez cały proces.</p>
    </div>
    <div class="grid-2">
        <div class="feature-card">
            <div class="feature-icon"><i class="ti ti-clipboard-list"></i></div>
            <div>
                <h3>Audyty online</h3>
                <p>Wypełniaj ankiety audytowe online we własnym czasie. Nasi specjaliści weryfikują każdy krok i są dostępni przez cały czas trwania audytu.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="ti ti-messages"></i></div>
            <div>
                <h3>Szybka komunikacja</h3>
                <p>Od oferty do audytu &mdash; bezpośredni kontakt z audytorem przez wbudowany system wiadomości. Żadnych maili, żadnego chaosu.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="ti ti-file-invoice"></i></div>
            <div>
                <h3>Przejrzyste oferty</h3>
                <p>Otrzymujesz szczegółową ofertę z automatycznie wyliczonymi kosztami. Akceptujesz jednym kliknięciem i przystępujesz do audytu.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="ti ti-shield-check"></i></div>
            <div>
                <h3>Niezależność</h3>
                <p>Nie sprzedajemy sprzętu. Nie mamy powiązań z dostawcami. Nasze rekomendacje służą wyłącznie Twojemu interesowi.</p>
            </div>
        </div>
    </div>
</section>

<!-- CYTAT -->
<section class="section-quote">
    <div class="quote-inner">
        <blockquote>
            <p class="quote-text">&bdquo;Mówię z pozycji osoby, która płaciła rachunki za energię w zakładzie &mdash; nie sprzedawcy.&rdquo;</p>
            <p class="quote-author">Bronisław Pytel &mdash; Założyciel ENESA, audytor energetyczny, 41 lat doświadczenia w energetyce przemysłowej</p>
        </blockquote>
    </div>
</section>

<!-- NASZA OFERTA -->
<section id="oferta" class="section-oferta">
    <div class="section-header">
        <div class="section-badge">Audyty energetyczne</div>
        <h2 class="section-title">Nasza oferta</h2>
        <p class="section-desc">Niezależne audyty i wdrożenia dla przemysłu &mdash; bez sprzedaży sprzętu.</p>
    </div>
    <div class="grid-3">
        <div class="offer-card">
            <div class="offer-number">01</div>
            <div class="offer-icon"><i class="ti ti-certificate"></i></div>
            <h3>Wdrożenie ISO 50001</h3>
            <p>System Zarządzania Energią, który płaci za siebie. Wdrażamy ISO 50001 w 12&ndash;18 miesięcy. Średnio 10&ndash;20% oszczędności energii i zwolnienie z obowiązkowego audytu co 4 lata. Od diagnozy po certyfikację.</p>
            <a href="#kontakt" class="offer-link">Dowiedz się więcej <i class="ti ti-arrow-right"></i></a>
        </div>
        <div class="offer-card">
            <div class="offer-number">02</div>
            <div class="offer-icon"><i class="ti ti-building"></i></div>
            <h3>Audyt Energetyczny Przedsiębiorstwa</h3>
            <p>Obowiązkowy audyt AEP &mdash; mapa Twoich oszczędności. Zgodnie z Ustawą o efektywności energetycznej i PN-EN 16247. Najbliższy termin: 11 października 2026 r. Pokrywamy min. 90% zużycia energii.</p>
            <a href="#kontakt" class="offer-link">Dowiedz się więcej <i class="ti ti-arrow-right"></i></a>
        </div>
        <div class="offer-card">
            <div class="offer-number">03</div>
            <div class="offer-icon"><i class="ti ti-solar-panel"></i></div>
            <h3>Audyt PV, BESS i microgrid</h3>
            <p>Zanim zainwestujesz miliony &mdash; sprawdź co Ci się opłaca. Niezależna analiza celowości PV, magazynu energii, kogeneracji i pomp ciepła. Dobór mocy, LCOE, model finansowy &mdash; bez powiązań z dostawcami sprzętu.</p>
            <a href="#kontakt" class="offer-link">Dowiedz się więcej <i class="ti ti-arrow-right"></i></a>
        </div>
        <div class="offer-card">
            <div class="offer-number">04</div>
            <div class="offer-icon"><i class="ti ti-award"></i></div>
            <h3>Białe Certyfikaty</h3>
            <p>Świadectwa efektywności energetycznej = realny przychód z TGE. Audyt szczegółowy, wniosek do URE, weryfikacja oszczędności. Redukcja kosztu inwestycji 20&ndash;40%.</p>
            <a href="#kontakt" class="offer-link">Dowiedz się więcej <i class="ti ti-arrow-right"></i></a>
        </div>
        <div class="offer-card">
            <div class="offer-number">05</div>
            <div class="offer-icon"><i class="ti ti-coin"></i></div>
            <h3>Audyt pod dotacje</h3>
            <p>Skrojony pod wymagania programu &mdash; FEnIKS, NFOŚiGW, KPO. Wykonujemy audyt zgodny z konkretnym naborem &mdash; gotowy do złożenia z wnioskiem o dofinansowanie.</p>
            <a href="#kontakt" class="offer-link">Dowiedz się więcej <i class="ti ti-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="section-cta">
    <h2>Gotowy na audyt energetyczny?</h2>
    <p>Zarejestruj firmę i otrzymaj ofertę dostosowaną do Twoich potrzeb.</p>
    <button class="btn-hero-filled" onclick="openModal()">Zarejestruj firmę</button>
</section>

<!-- FOOTER -->
<footer id="kontakt" class="footer">
    <div class="footer-grid">
        <div>
            <div class="footer-brand-row">
                <img src="{{ asset('Logo2.png') }}" alt="ENESA logo">
                <span class="footer-brand-name">ENESA</span>
            </div>
            <p class="footer-desc">Niezależny system zarządzania audytami energetycznymi dla przemysłu. Bez sprzedaży sprzętu, bez konfliktów interesów.</p>
            <ul class="footer-contact-list">
                <li><i class="ti ti-mail"></i><a href="mailto:biuro@enesa.pl">biuro@enesa.pl</a></li>
                <li><i class="ti ti-phone"></i><a href="tel:+48516500729">+48 516 500 729</a></li>
                <li><i class="ti ti-world"></i><a href="https://www.enesa.pl" target="_blank" rel="noopener">www.enesa.pl</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Nawigacja</h4>
            <ul>
                <li><a href="#oferta">Nasza oferta</a></li>
                <li><a href="#system">Jak to działa</a></li>
                <li><a href="{{ route('login') }}">Zaloguj się</a></li>
                <li><a href="#" onclick="openModal();return false;">Zarejestruj firmę</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Audyty</h4>
            <ul>
                <li><a href="#oferta">Wdrożenie ISO 50001</a></li>
                <li><a href="#oferta">Audyt Energetyczny Przedsiębiorstwa</a></li>
                <li><a href="#oferta">Audyt PV, BESS i microgrid</a></li>
                <li><a href="#oferta">Białe Certyfikaty</a></li>
                <li><a href="#oferta">Audyt pod dotacje</a></li>
            </ul>
        </div>
    </div>
</footer>
<div class="footer-copy">
    &copy; {{ date('Y') }} ENESA Energy Audit Systems. Wszelkie prawa zastrzeżone.
</div>

<!-- MODAL REJESTRACJI -->
<div id="modalOverlay" onclick="closeModalOutside(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()" title="Zamknij">&times;</button>
        <div class="modal-brand">
            <img src="{{ asset('Logo2.png') }}" alt="ENESA">
            <span>ENESA</span>
        </div>
        <h2>Zarejestruj firmę</h2>
        <p>Wypełnij formularz, a nasz zespół skontaktuje się z Tobą w ciągu jednego dnia roboczego z ofertą dostosowaną do Twoich potrzeb.</p>
        <form id="registerForm" onsubmit="return false;">
            <div class="form-group">
                <label for="regNip">NIP firmy</label>
                <div class="form-row">
                    <input id="regNip" type="text" class="form-input" placeholder="000-000-00-00" maxlength="13">
                    <button type="button" class="btn-gus" onclick="fetchGUS()">Pobierz z GUS</button>
                </div>
            </div>
            <div class="form-group">
                <label for="regNazwa">Nazwa firmy</label>
                <input id="regNazwa" type="text" class="form-input" placeholder="Pobrana automatycznie z GUS" readonly>
            </div>
            <div class="form-group">
                <label for="regAdres">Adres</label>
                <input id="regAdres" type="text" class="form-input" placeholder="Pobrana automatycznie z GUS" readonly>
            </div>
            <hr class="modal-divider">
            <div class="form-group">
                <label for="regImie">Imię i nazwisko osoby kontaktowej</label>
                <input id="regImie" type="text" class="form-input" placeholder="Jan Kowalski">
            </div>
            <div class="form-group">
                <label for="regEmail">Adres e-mail</label>
                <input id="regEmail" type="email" class="form-input" placeholder="jan.kowalski@firma.pl">
            </div>
            <div class="form-group">
                <label for="regTelefon">Telefon</label>
                <input id="regTelefon" type="tel" class="form-input" placeholder="+48 000 000 000">
            </div>
            <button type="submit" class="btn-submit">Wyślij zgłoszenie do ENESA</button>
        </form>
        <p class="modal-login-link">Masz już konto? <a href="{{ route('login') }}">Zaloguj się</a></p>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }
    function closeModalOutside(event) {
        if (event.target === document.getElementById('modalOverlay')) {
            closeModal();
        }
    }
    function fetchGUS() {
        alert('Funkcja pobierania danych z GUS zostanie uruchomiona wkrótce.');
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
</script>
</body>
</html>