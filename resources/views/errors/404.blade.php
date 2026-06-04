<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Strona nie istnieje — ENESA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 100%;
            height: 100%;
        }

        body {
            font-family: 'Manrope', 'Lato', sans-serif;
            background: #F4F1EA;
            color: #1e1e1e;
            display: flex;
            flex-direction: column;
        }

        /* NAVBAR */
        .navbar {
            background: #1A4D3A;
            height: 64px;
            padding: 0 48px;
            display: flex;
            align-items: center;
            gap: 40px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .navbar-brand img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .navbar-brand-text {
            font-family: 'Lato', sans-serif;
            font-weight: 700;
            font-size: 18px;
            color: #F5F0E8;
            letter-spacing: 0.04em;
        }

        /* MAIN CONTENT */
        .container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
        }

        .error-box {
            text-align: center;
            max-width: 580px;
            width: 100%;
        }

        .error-code {
            font-family: 'Lato', sans-serif;
            font-size: 120px;
            font-weight: 900;
            color: #1A4D3A;
            line-height: 1;
            margin-bottom: 16px;
            letter-spacing: -2px;
        }

        .error-title {
            font-family: 'Lato', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: #1A4D3A;
            margin-bottom: 12px;
        }

        .error-description {
            font-family: 'Manrope', 'Lato', sans-serif;
            font-size: 15px;
            color: #5a6a60;
            line-height: 1.7;
            margin-bottom: 32px;
        }

        .btn-home {
            display: inline-block;
            background: #1A4D3A;
            color: #F5F0E8;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-family: 'Manrope', sans-serif;
            font-size: 15px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-home:hover {
            background: #153d2e;
            transform: translateY(-2px);
        }

        .btn-home:active {
            transform: translateY(0);
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .navbar {
                padding: 0 24px;
                gap: 20px;
            }

            .error-code {
                font-size: 80px;
            }

            .error-title {
                font-size: 22px;
            }

            .error-description {
                font-size: 14px;
                margin-bottom: 24px;
            }

            .btn-home {
                padding: 10px 24px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="{{ route('home') }}" class="navbar-brand">
        <img src="{{ asset('Logo2.png') }}" alt="ENESA logo">
        <span class="navbar-brand-text">ENESA</span>
    </a>
</nav>

<!-- MAIN CONTENT -->
<div class="container">
    <div class="error-box">
        <div class="error-code">404</div>
        <h1 class="error-title">Strona w budowie lub nie istnieje</h1>
        <p class="error-description">
            Ta strona jest jeszcze w przygotowaniu lub podany adres nie istnieje. 
            Wróć na stronę główną i kontynuuj przeglądanie naszej oferty.
        </p>
        <a href="{{ route('home') }}" class="btn-home">
            <i class="ti ti-home" style="margin-right: 6px;"></i>Wróć na stronę główną
        </a>
    </div>
</div>

</body>
</html>
