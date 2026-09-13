<!DOCTYPE html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Bezpieczne logowanie</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f1ea;color:#263c36;font:16px/1.55 Arial,sans-serif;padding:24px}.auth-card{max-width:560px;margin:5vh auto;background:white;padding:32px;border-radius:16px;box-shadow:0 8px 32px #0000000d;border-top:5px solid var(--brand)}h1{font-size:26px;line-height:1.25;color:var(--brand);margin:8px 0 20px}h2{font-size:18px}.eyebrow{font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#64756e}label{display:block;font-weight:600;margin:16px 0 6px}input{width:100%;padding:12px;border:1px solid #bbc7c1;border-radius:7px;font:inherit}button,.button{display:block;width:100%;padding:12px;border:0;border-radius:7px;background:var(--brand);color:#fff;font:inherit;font-weight:600;cursor:pointer;text-align:center;text-decoration:none;margin-top:16px}.secondary{background:#eef2f0;color:#263c36}.error{background:#fff0f0;color:#9d2020;padding:12px;border-radius:8px}.help{font-size:14px;color:#64756e}.qr{display:block;width:280px;max-width:100%;margin:12px auto}.secret{overflow-wrap:anywhere;background:#f1f5f3;padding:10px;display:block}.codes{background:#f1f5f3;padding:16px;border-radius:8px}.codes code{display:block;margin:8px 0;overflow-wrap:anywhere;font-size:15px}@media(max-width:480px){body{padding:12px}.auth-card{padding:20px;margin:16px auto}}
</style></head>
<body style="--brand:{{$appBrand?->primaryColor() ?? '#1a4d3a'}}"><main class="auth-card">
<div class="eyebrow">Konto superadministratora</div>
@if($errors->any())<p class="error" role="alert">{{$errors->first()}}</p>@endif
@yield('content')
<form method="POST" action="{{route('logout')}}">@csrf<button class="secondary" type="submit">Wyloguj się</button></form>
</main></body></html>
