@extends('emails.layout')
@section('mail-title', 'Samochody — informacje wymagające uwagi')
@section('mail-content')
<p>Poniższe samochody wymagają aktualizacji terminów lub uzupełnienia danych:</p>
@foreach($items->groupBy(fn($item) => $item['vehicle']->id) as $vehicleItems)
@php $vehicle = $vehicleItems->first()['vehicle']; @endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;border:1px solid {{$brandTint}};border-left:4px solid {{$brandColor}};border-radius:8px"><tr><td style="padding:16px">
<h2 style="margin:0 0 10px;font-size:18px;color:{{$brandColor}}">{{$vehicle->name}} · {{$vehicle->registration_number}}</h2>
<ul style="margin:0;padding-left:20px">@foreach($vehicleItems as $item)<li style="margin:6px 0">{{$item['message']}}</li>@endforeach</ul>
</td></tr></table>
@endforeach
<p>Uzupełnij dane lub zaktualizuj termin w rejestrze samochodów po rozwiązaniu problemu. Przypomnienia o przekroczonych terminach wysyłamy codziennie; raport brakujących danych w poniedziałki.</p>
<p><a href="{{route('hr.index',['tab'=>'vehicles'])}}" style="display:inline-block;padding:12px 20px;background-color:{{$brandColor}};color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold">Otwórz samochody w HR</a></p>

@endsection
