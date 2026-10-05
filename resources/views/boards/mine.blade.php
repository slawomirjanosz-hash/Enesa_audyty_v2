@extends('layouts.app')
@section('page-title','Moja tablica')
@section('content')
<h1>{{$archivedBoard ? 'Archiwum zadań' : 'Moja tablica'}}</h1>
<nav class="board-tabs" aria-label="Widok zadań"><a href="{{route('board.mine')}}" @if(!$archivedBoard) aria-current="page" @endif>Aktywne zadania</a><a href="{{route('board.mine',['archive'=>1])}}" @if($archivedBoard) aria-current="page" @endif>Zarchiwizowane</a></nav>
<p>Zadania z dostępnych modułów w jednym miejscu. Domyślnie pokazujemy zadania przypisane do Ciebie.</p>
<form class="board-filters" method="get" action="{{route('board.mine')}}" aria-label="Filtry zadań">
@if($archivedBoard)<input type="hidden" name="archive" value="1">@endif
@foreach($options as $key=>$choices)
<details class="board-filter"><summary>{{$key==='users'?'Użytkownicy':$modules[$key]}} <span data-filter-count>{{count($selected[$key])===count($choices)&&$key!=='users'?'Wszystkie':count($selected[$key]).' wybr.'}}</span></summary>
<div class="board-filter-menu"><input type="hidden" name="{{$key}}[]" value="">
<label><input type="checkbox" data-filter-all @checked(count($selected[$key])===count($choices) && count($choices)>0)> Zaznacz wszystkie</label>
@forelse($choices as $id=>$label)<label><input type="checkbox" name="{{$key}}[]" value="{{$id}}" @checked(in_array((string)$id,$selected[$key],true))> {{$label}}</label>@empty<p>Brak dostępnych zadań.</p>@endforelse
</div></details>
@endforeach
<button class="board-primary" type="submit">Pokaż zadania</button><a class="board-reset" href="{{route('board.mine',$archivedBoard ? ['archive'=>1] : [])}}">Moje zadania · wszystkie źródła</a>
</form>
@include('boards.cards',['personalBoard'=>true,'boardManage'=>false])
@if($cards->isEmpty())<p>Nie masz przypisanych zadań w tym widoku.</p>@endif
<p>Wyświetlono {{$cards->count()}} z {{$cards->total()}} zadań.@if($cards->hasPages()) Liczniki kolumn dotyczą tej strony.@endif</p>{{$cards->links()}}
@endsection
