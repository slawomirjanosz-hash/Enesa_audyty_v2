@extends('emails.layout')
@section('mail-title', 'Dostęp do systemu')
@section('mail-content')
<p>Dodano Cię jako użytkownika firmy <strong>{{$company->name}}</strong> w systemie {{$mailCompanyName}}. Poniżej znajdziesz dane logowania.</p>
<table role="presentation" width="100%" cellpadding="10" cellspacing="0" style="table-layout:fixed;border:1px solid {{$brandTint}};border-left:4px solid {{$brandColor}}">
<tr><td width="30%">Adres logowania</td><td style="overflow-wrap:anywhere"><a href="{{route('login')}}" style="color:{{$brandColor}}">{{route('login')}}</a></td></tr>
<tr><td>Login</td><td style="overflow-wrap:anywhere">{{$user->email}}</td></tr>
<tr><td>Hasło</td><td style="font-family:Consolas,monospace;overflow-wrap:anywhere">{{$temporaryPassword}}</td></tr>
</table><p>Po zalogowaniu zmień hasło w swoim profilu.</p>
@endsection
