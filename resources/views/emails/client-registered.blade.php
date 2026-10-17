@extends('emails.layout')
@section('mail-title', 'Nowa rejestracja firmy')
@section('mail-content')
<p>W systemie {{$mailCompanyName}} pojawiła się nowa rejestracja wymagająca akceptacji.</p>
<table role="presentation" width="100%" cellpadding="10" cellspacing="0" style="table-layout:fixed;border:1px solid {{$brandTint}};border-left:4px solid {{$brandColor}}">
<tr><td width="30%">Firma</td><td>{{$company->name}}</td></tr><tr><td>NIP</td><td>{{$company->nip}}</td></tr>
<tr><td>Adres</td><td>{{$company->address ?? 'Nie podano'}}, {{$company->city ?? ''}}</td></tr>
<tr><td>Kontakt</td><td>{{$user->name}}</td></tr><tr><td>E-mail</td><td style="overflow-wrap:anywhere">{{$user->email}}</td></tr>
<tr><td>Telefon</td><td>{{$user->phone ?? 'Nie podano'}}</td></tr><tr><td>Data rejestracji</td><td>{{$company->created_at->format('d.m.Y, H:i')}}</td></tr>
</table><p><a href="{{url('/dashboard')}}" style="color:{{$brandColor}}">Przejdź do dashboardu</a></p><p>Zaloguj się i zaakceptuj lub odrzuć rejestrację w karcie klienta.</p>
@endsection
