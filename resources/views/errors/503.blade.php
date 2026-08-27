@extends(auth()->check() ? 'layouts.app' : 'errors.layout')
@section('title','Aplikacja chwilowo niedostępna') @section('page-title','Aplikacja chwilowo niedostępna')
@section('content') @include('errors.partials.card',['code'=>503,'label'=>'przerwa techniczna','icon'=>'settings-automation','heading'=>'Trwają krótkie prace techniczne','description'=>'Aplikacja jest chwilowo niedostępna podczas aktualizacji lub prac serwisowych. Dane pozostają bezpieczne.','hint'=>'Spróbuj ponownie za kilka minut.']) @endsection
