@extends('layouts.app')
@section('page-title','Moja tablica')
@section('content')
<h1>Moja tablica</h1>
<p>Zadania z dostępnych modułów w jednym miejscu. Domyślnie pokazujemy zadania przypisane do Ciebie.</p>
<form class="board-filters" method="get" action="{{route('board.mine')}}" aria-label="Filtry zadań">
@foreach($options as $key=>$choices)
<details class="board-filter"><summary>{{$key==='users'?'Użytkownicy':$modules[$key]}} <span data-filter-count>{{count($selected[$key])===count($choices)&&$key!=='users'?'Wszystkie':count($selected[$key]).' wybr.'}}</span></summary>
<div class="board-filter-menu"><input type="hidden" name="{{$key}}[]" value="">
<label><input type="checkbox" data-filter-all @checked(count($selected[$key])===count($choices) && count($choices)>0)> Zaznacz wszystkie</label>
@forelse($choices as $id=>$label)<label><input type="checkbox" name="{{$key}}[]" value="{{$id}}" @checked(in_array((string)$id,$selected[$key],true))> {{$label}}</label>@empty<p>Brak dostępnych zadań.</p>@endforelse
</div></details>
@endforeach
<button class="board-primary" type="submit">Pokaż zadania</button><a class="board-reset" href="{{route('board.mine')}}">Moje zadania · wszystkie źródła</a>
</form>
@include('boards.cards',['personalBoard'=>true,'boardManage'=>false])
@if($cards->isEmpty())<p>Nie masz przypisanych zadań w tym widoku.</p>@endif
<p>Wyświetlono {{$cards->count()}} z {{$cards->total()}} zadań.@if($cards->hasPages()) Liczniki kolumn dotyczą tej strony.@endif</p>{{$cards->links()}}
@endsection
