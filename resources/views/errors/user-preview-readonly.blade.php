<!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Podgląd tylko do odczytu</title></head>
<body style="font:15px/1.6 sans-serif;color:#234c3b;padding:40px;">
    <h1 style="font-size:22px;">Podgląd tylko do odczytu</h1>
    <p>Zapisy i operacje konta są wyłączone w podglądzie serwisowym.</p>
    <a href="{{ route($previewUser->hasAnyRole(['client_admin','client_user']) ? 'client.dashboard' : 'board.mine') }}">Wróć do podglądu użytkownika</a>
    @include('partials.user-preview-banner')
</body></html>
