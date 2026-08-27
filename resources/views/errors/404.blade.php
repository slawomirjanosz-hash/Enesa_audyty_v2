@extends(auth()->check() ? 'layouts.app' : 'errors.layout')
@section('title','Nie znaleziono zawartości') @section('page-title','Nie znaleziono zawartości')
@section('content') @include('errors.partials.card',['code'=>404,'label'=>'brak zawartości','icon'=>'file-search','heading'=>'Nie znaleźliśmy tej zawartości','description'=>'Wybrany element mógł zostać przeniesiony, usunięty albo nie jest jeszcze dostępny pod tym adresem.','hint'=>'Wróć do poprzedniego widoku i wybierz element ponownie. Jeżeli korzystasz ze starego linku, otwórz odpowiednią zakładkę z menu aplikacji.']) @endsection
