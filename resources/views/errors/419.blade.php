@extends(auth()->check() ? 'layouts.app' : 'errors.layout')
@section('title','Sesja wygasła') @section('page-title','Sesja wygasła')
@section('content') @include('errors.partials.card',['code'=>419,'label'=>'wygasła sesja','icon'=>'clock-exclamation','tone'=>'#fef3c7','iconColor'=>'#92400e','heading'=>'Formularz czekał zbyt długo','description'=>'Ze względów bezpieczeństwa sesja formularza wygasła i dane nie zostały przesłane.','hint'=>'Odśwież stronę, sprawdź dane i wykonaj operację ponownie.']) @endsection
