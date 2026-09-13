<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Potwierdź logowanie</title></head>
<body style="font-family:Arial,sans-serif;background:#f4f1ea;margin:0;padding:24px;box-sizing:border-box"><main style="max-width:400px;margin:8vh auto;background:white;padding:28px;border-radius:14px">
    <h1 style="font-size:24px;color:{{$appBrand?->primaryColor() ?? '#1a4d3a'}}">Potwierdź logowanie</h1>
    <p>Konto superadmina wymaga kodu SMS. Numer kończy się na {{substr((string)config('security.sms.phone'),-3)}}.</p>
    @if(session('status'))<p role="status">{{session('status')}}</p>@endif
    @if($errors->any())<p role="alert" style="color:#b91c1c">{{$errors->first()}}</p>@endif
    <form method="POST" action="{{route('auth.sms.verify')}}">@csrf<label for="sms-code">Kod z SMS-a</label><input id="sms-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus style="display:block;width:100%;box-sizing:border-box;font-size:24px;padding:12px;margin:10px 0"><button type="submit" style="padding:12px;width:100%">Potwierdź i wejdź</button></form>
    <form method="POST" action="{{route('auth.sms.send')}}" style="margin-top:18px">@csrf<button type="submit">{{session('sms_challenge') ? 'Wyślij ponownie' : 'Wyślij kod SMS'}}</button></form>
    <form method="POST" action="{{route('logout')}}" style="margin-top:18px">@csrf<button type="submit">Anuluj logowanie</button></form>
</main></body></html>
