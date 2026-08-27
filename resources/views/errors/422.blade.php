@extends(auth()->check() ? 'layouts.app' : 'errors.layout')
@section('title','Dane wymagają poprawy') @section('page-title','Dane wymagają poprawy')
@section('content') @include('errors.partials.card',['code'=>422,'label'=>'nieprawidłowe dane','icon'=>'forms','tone'=>'#fef3c7','iconColor'=>'#92400e','heading'=>'Nie udało się przetworzyć danych','description'=>'Niektóre informacje są niepełne albo mają format, którego system nie może zaakceptować.','hint'=>'Wróć do formularza, sprawdź oznaczone pola i spróbuj ponownie.']) @endsection
