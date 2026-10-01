@extends('layouts.app')
@section('page-title','Moja tablica')
@section('content')
<h1>Moja tablica</h1>
<p>Zadania przypisane do Ciebie w dostępnych projektach i audytach.</p>
<nav class="board-groups" aria-label="Wybierz projekt lub audyt"><a href="{{route('board.mine')}}" @class(['active'=>!request('group')])>Wszystkie moje zadania</a>
@foreach(['project'=>'Projekty','audit'=>'Audyty'] as $type=>$label)
<details open><summary>{{$label}}</summary>@foreach($groups as $key=>$group)@if(str_starts_with($key,$type.'-'))@php($owner=$group->project ?? $group->audit)<a href="{{route('board.mine',['group'=>$key])}}" @class(['active'=>request('group')===$key])>{{$owner->number}} · {{$type==='project'?$owner->name:$owner->title}} ({{$group->card_count}})</a>@endif @endforeach</details>
@endforeach</nav>
@include('boards.cards',['personalBoard'=>true,'boardManage'=>false])
@if($cards->isEmpty())<p>Nie masz przypisanych zadań w tym widoku.</p>@endif
<p>Wyświetlono {{$cards->count()}} z {{$cards->total()}} zadań.@if($cards->hasPages()) Liczniki kolumn dotyczą tej strony.@endif</p>{{$cards->links()}}
@endsection
