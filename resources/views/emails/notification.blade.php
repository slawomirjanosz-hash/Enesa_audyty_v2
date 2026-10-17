@extends('emails.layout')
@section('mail-title', $notificationTitle)
@section('mail-content')
<p>{{$notificationText}}</p>
<p><a href="{{$actionUrl}}" style="display:inline-block;padding:12px 20px;background-color:{{$brandColor}};color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold">{{$actionText}}</a></p>
<p>{{$notificationNote}}</p>
<p style="font-size:12px;overflow-wrap:anywhere">Jeśli przycisk nie działa, skopiuj ten adres do przeglądarki:<br><a href="{{$actionUrl}}" style="color:{{$brandColor}}">{{$actionUrl}}</a></p>
@endsection
