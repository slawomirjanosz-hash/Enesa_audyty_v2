@extends('emails.layout')
@section('mail-title', $passwordChanged ? 'Twoje hasło zostało zmienione' : 'Twoje konto zostało utworzone')
@section('mail-content')
<p style="margin:0 0 24px">{{$passwordChanged ? 'administrator zmienił Twoje hasło w systemie' : 'utworzono dla Ciebie konto w systemie'}} <strong>{{$companyName}}</strong>. Poniżej znajdziesz {{$passwordChanged ? 'aktualne' : ''}} dane logowania.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border:1px solid {{$brandTint}};border-left:4px solid {{$brandColor}};border-radius:8px;table-layout:fixed"><colgroup><col width="30%" style="width:30%"><col width="70%" style="width:70%"></colgroup>
<tr><td colspan="2" style="padding:15px 16px 8px;font-size:13px;font-weight:bold;color:{{$brandColor}}">TWOJE DANE LOGOWANIA</td></tr>
<tr><td width="115" style="width:115px;padding:10px 16px;vertical-align:top;font-size:14px;color:#657186">Adres logowania</td><td style="padding:10px 16px 10px 0;vertical-align:top;font-size:14px;word-wrap:break-word"><a href="{{$loginUrl}}" style="color:{{$brandColor}};text-decoration:underline">{{$loginUrl}}</a></td></tr>
<tr><td width="115" style="width:115px;padding:10px 16px;vertical-align:top;font-size:14px;color:#657186">Login</td><td style="padding:10px 16px 10px 0;vertical-align:top;font-size:14px;word-wrap:break-word">{{$login}}</td></tr>
<tr><td width="115" style="width:115px;padding:10px 16px 16px;vertical-align:top;font-size:14px;color:#657186">Hasło</td><td style="padding:10px 16px 16px 0;vertical-align:top;font-size:14px;word-wrap:break-word"><span style="font-family:Consolas,'Courier New',monospace;white-space:pre-wrap">{{$temporaryPassword}}</span></td></tr>
</table>
<p style="margin:18px 0 26px;font-size:13px;color:#657186">Po zalogowaniu zmień hasło w swoim profilu.</p>

@endsection
