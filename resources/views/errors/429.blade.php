@extends(auth()->check() ? 'layouts.app' : 'errors.layout')
@section('title','Chwila przerwy') @section('page-title','Chwila przerwy')
@section('content') @include('errors.partials.card',['code'=>429,'label'=>'limit operacji','icon'=>'hourglass','tone'=>'#fef3c7','iconColor'=>'#92400e','heading'=>'Wykonano zbyt wiele operacji naraz','description'=>'System chwilowo ograniczył kolejne żądania, aby zachować stabilne i bezpieczne działanie.','hint'=>'Odczekaj kilkadziesiąt sekund i spróbuj ponownie.']) @endsection
