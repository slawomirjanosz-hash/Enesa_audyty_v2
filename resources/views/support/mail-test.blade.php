<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Test poczty PRINŻ — pomoc techniczna</title>
    <style>
        body{margin:0;background:#f4f2ed;font-family:Arial,sans-serif;color:#20364b;padding:24px}
        main{max-width:600px;margin:8vh auto;background:white;padding:32px;border-radius:16px}
        h1{font-size:24px}p{line-height:1.6;overflow-wrap:anywhere}
        button{background:#203c60;color:white;border:0;border-radius:8px;padding:14px 22px;cursor:pointer;font-weight:bold}
        .notice{padding:16px;background:#eef4f6;border-radius:8px;margin:20px 0}
    </style>
</head>
<body><main>
    <h1>Test poczty PRINŻ</h1>
    <p>Panel diagnostyczny dla pomocy technicznej Wizjanet. Wiadomość zostanie wysłana przez usługę pocztową aplikacji.</p>
    <p><strong>Odbiorca:</strong> {{ $recipient }}</p>
    <p>Link jest czasowy. Limit: jedna próba na minutę, maksymalnie 20 prób dla tego linku.</p>
    @if(session('mail_test_result'))<div class="notice" role="status">{{ session('mail_test_result') }}</div>@endif
    <form method="post" action="{{ request()->fullUrl() }}">@csrf
        <button type="submit">Wyślij mail testowy</button>
    </form>
    <p>Przyjęcie przez usługę wysyłkową nie oznacza dostarczenia. Przy zgłoszeniu problemu podaj odbiorcę i dokładną godzinę próby.</p>
</main></body>
</html>
