<!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('mail-title')</title></head>
<body style="margin:0;padding:0;background-color:#f3f5f7;font-family:Arial,Helvetica,sans-serif;color:#25344a;line-height:1.6">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f5f7"><tr><td align="center" style="padding:28px 12px">
<table role="presentation" width="620" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:620px;background-color:#ffffff;border:1px solid #e2e7ed;border-radius:12px">
<tr><td bgcolor="{{$brandColor}}" height="7" style="height:7px;line-height:7px;border-radius:12px 12px 0 0;font-size:1px">&nbsp;</td></tr>
<tr><td style="padding:26px 30px 22px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
@if($logoContent && isset($message))<td width="140" style="width:140px;vertical-align:middle;padding-right:18px"><img src="{{$message->embedData($logoContent, 'company-logo', $logoMime)}}" alt="Logo {{$mailCompanyName}}" width="120" style="display:block;width:120px;max-width:120px;height:auto;border:0"></td>@endif
<td style="vertical-align:middle;color:{{$brandColor}};font-size:16px;line-height:1.4;font-weight:bold">{{$mailCompanyName}}</td>
</tr></table>
</td></tr>
<tr><td style="padding:0 30px 28px">
<h1 style="font-size:24px;line-height:1.3;margin:0 0 24px;color:{{$brandColor}}">@yield('mail-title')</h1>
<p style="margin:0 0 16px">{{$greeting}},</p>
@yield('mail-content')
<p style="margin:26px 0 8px">Życzymy miłego użytkowania!</p>
<p style="margin:0">W razie potrzeby skontaktuj się z administratorem systemu.</p>
</td></tr>
<tr><td style="padding:18px 30px;background-color:#f7f9fb;border-radius:0 0 12px 12px;font-size:12px;color:{{$brandColor}};font-weight:bold">{{$mailCompanyName}}<br><a href="{{url('/')}}" style="color:{{$brandColor}}">{{url('/')}}</a></td></tr>
</table></td></tr></table>
</body></html>
