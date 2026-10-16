<!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{$passwordChanged ? 'Twoje hasło zostało zmienione' : 'Twoje konto zostało utworzone'}}</title></head>
<body style="margin:0;padding:0;background-color:#f3f5f7;font-family:Arial,Helvetica,sans-serif;color:#25344a;line-height:1.6">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f5f7"><tr><td align="center" style="padding:28px 12px">
<table role="presentation" width="620" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:620px;background-color:#ffffff;border:1px solid #e2e7ed;border-radius:12px">
<tr><td bgcolor="{{$brandColor}}" height="7" style="height:7px;line-height:7px;border-radius:12px 12px 0 0;font-size:1px">&nbsp;</td></tr>
<tr><td style="padding:26px 30px 22px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
@if($logoContent)<td width="140" style="width:140px;vertical-align:middle;padding-right:18px"><img src="{{$message->embedData($logoContent, 'company-logo', $logoMime)}}" alt="Logo {{$companyName}}" width="120" style="display:block;width:120px;max-width:120px;height:auto;border:0"></td>@endif
<td style="vertical-align:middle;color:{{$brandColor}};font-size:16px;line-height:1.4;font-weight:bold">{{$companyName}}</td>
</tr></table>
</td></tr>
<tr><td style="padding:0 30px 28px">
<h1 style="font-size:24px;line-height:1.3;margin:0 0 24px;color:{{$brandColor}}">{{$passwordChanged ? 'Twoje hasło zostało zmienione' : 'Twoje konto zostało utworzone'}}</h1>
<p style="margin:0 0 16px">{{$greeting}},</p>
<p style="margin:0 0 24px">{{$passwordChanged ? 'administrator zmienił Twoje hasło w systemie' : 'utworzono dla Ciebie konto w systemie'}} <strong>{{$companyName}}</strong>. Poniżej znajdziesz {{$passwordChanged ? 'aktualne' : ''}} dane logowania.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border:1px solid {{$brandTint}};border-left:4px solid {{$brandColor}};border-radius:8px;table-layout:fixed"><colgroup><col width="30%" style="width:30%"><col width="70%" style="width:70%"></colgroup>
<tr><td colspan="2" style="padding:15px 16px 8px;font-size:13px;font-weight:bold;color:{{$brandColor}}">TWOJE DANE LOGOWANIA</td></tr>
<tr><td width="115" style="width:115px;padding:10px 16px;vertical-align:top;font-size:14px;color:#657186">Adres logowania</td><td style="padding:10px 16px 10px 0;vertical-align:top;font-size:14px;word-wrap:break-word"><a href="{{$loginUrl}}" style="color:{{$brandColor}};text-decoration:underline">{{$loginUrl}}</a></td></tr>
<tr><td width="115" style="width:115px;padding:10px 16px;vertical-align:top;font-size:14px;color:#657186">Login</td><td style="padding:10px 16px 10px 0;vertical-align:top;font-size:14px;word-wrap:break-word">{{$login}}</td></tr>
<tr><td width="115" style="width:115px;padding:10px 16px 16px;vertical-align:top;font-size:14px;color:#657186">Hasło</td><td style="padding:10px 16px 16px 0;vertical-align:top;font-size:14px;word-wrap:break-word"><span style="font-family:Consolas,'Courier New',monospace;white-space:pre-wrap">{{$temporaryPassword}}</span></td></tr>
</table>
<p style="margin:18px 0 26px;font-size:13px;color:#657186">Po zalogowaniu zmień hasło w swoim profilu.</p>
<p style="margin:0 0 8px">Życzymy miłego użytkowania!</p>
<p style="margin:0">W razie potrzeby skontaktuj się z administratorem systemu.</p>
</td></tr>
<tr><td style="padding:18px 30px;background-color:#f7f9fb;border-radius:0 0 12px 12px;font-size:12px;color:{{$brandColor}};font-weight:bold">{{$companyName}}</td></tr>
</table></td></tr></table>
</body></html>
