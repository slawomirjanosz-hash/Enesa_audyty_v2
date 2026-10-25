@extends('layouts.app')
@section('page-title','Rozliczenia faktur')
@section('content')
@include('expenses.style')
<div class="exp-head"><div><h1>Rozliczenia faktur</h1><p class="exp-muted">Zgłoszenia zakupów i faktury wybrane przez księgowość do rozpoznania.</p></div><a class="exp-btn primary" href="{{route('expenses.create')}}">+ Dodaj zakup / fakturę</a></div>
@if(session('success'))<div class="exp-message">{{session('success')}}</div>@endif
<nav class="exp-tabs" aria-label="Rejestry faktur">
<a class="exp-btn {{$tab==='own'?'active':''}}" href="{{route('expenses.index')}}">Moje faktury</a>
<a class="exp-btn {{$tab==='pool'?'active':''}}" href="{{route('expenses.index',['tab'=>'pool'])}}">Faktury do przypisania</a>
@if($accounting)<a class="exp-btn {{$tab==='all'?'active':''}}" href="{{route('expenses.index',['tab'=>'all'])}}">Wszystkie — księgowość</a>@endif
</nav>
@if($tab==='pool')<p class="exp-muted">Tutaj znajdują się wyłącznie faktury opublikowane przez księgowość. Rozpoznajesz swój zakup? Otwórz fakturę i przypisz ją do siebie. Zniknie wtedy z tej listy dla innych pracowników.</p>@endif
<form id="expense-filters" class="exp-filters" method="GET"><input type="hidden" name="tab" value="{{$tab}}"><label>Miesiąc <input name="month" type="month" value="{{request('month')}}"></label><label>Szukaj <input name="search" value="{{request('search')}}" maxlength="100" placeholder="Faktura, dostawca, kupujący, opis…"></label><button class="exp-btn">Filtruj</button><label class="exp-columns"><input id="expense-extra-columns" type="checkbox"> Więcej kolumn</label></form><p id="expense-filter-status" class="exp-muted" role="status"></p>
@if($errors->any())<div class="exp-message error">{{$errors->first()}}</div>@endif
<div id="expense-results" aria-live="polite">@include('expenses.results')</div>
@endsection

@push('scripts')<script src="{{asset('js/expense-filters.js')}}?v=1" defer></script>@endpush
