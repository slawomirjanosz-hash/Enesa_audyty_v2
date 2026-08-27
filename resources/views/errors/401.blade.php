@extends(auth()->check() ? 'layouts.app' : 'errors.layout')
@section('title','Wymagane logowanie') @section('page-title','Wymagane logowanie')
@section('content') @include('errors.partials.card',['code'=>401,'label'=>'wymagane logowanie','icon'=>'login','heading'=>'Zaloguj się, aby kontynuować','description'=>'Ta zawartość jest dostępna po zalogowaniu. Po potwierdzeniu tożsamości będzie można wrócić do pracy w aplikacji.','hint'=>'Jeżeli sesja zakończyła się automatycznie, zaloguj się ponownie.']) @endsection
