@extends('emails.layout')
@section('mail-title', 'Twoje konto zostało zaakceptowane')
@section('mail-content')
<p>Twoje konto firmowe w systemie <strong>{{$mailCompanyName}}</strong> zostało zaakceptowane. Możesz teraz zalogować się do portalu klienta.</p>
<p><strong>Firma:</strong> {{$company->name}}<br><strong>NIP:</strong> {{$company->nip}}</p>
<p>Możesz przeglądać dostępne typy audytów, wysyłać zapytania, śledzić postępy audytów i komunikować się z audytorem.</p>
<p><a href="{{route('login')}}" style="color:{{$brandColor}}">Zaloguj się do portalu</a></p>
@endsection
