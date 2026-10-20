@extends('emails.layout')
@section('mail-title', 'Test poczty')
@section('mail-content')
@if($supportTest)<p>Mail testowy wysłany z panelu diagnostycznego dla pomocy technicznej Wizjanet.</p>
@else<p>To wiadomość testowa wysłana z ustawień firmy na Twoje żądanie.</p>@endif
<p>Jeśli ją widzisz, poczta z aplikacji dotarła do Twojej skrzynki.</p><p>Test nie uruchamia przypomnień o zadaniach ani innych powiadomień.</p>
@endsection
