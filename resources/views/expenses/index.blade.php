@extends('layouts.app')
@section('page-title','Rozliczenia faktur')
@include('expenses.style')
@section('content')
<div class="exp-head"><div><h1>Rozliczenia faktur</h1><p class="exp-muted">Zgłoszenia zakupów i faktury wybrane przez księgowość do rozpoznania.</p></div>@if($tab === 'all' && $accounting)<a class="exp-btn primary" href="{{route('expenses.create', ['accounting_entry' => 1])}}">+ Dodaj fakturę jako księgowość</a>@else<a class="exp-btn primary" href="{{route('expenses.create')}}">+ Dodaj zakup / fakturę</a>@endif</div>
@if(session('success'))<div class="exp-message">{{session('success')}}</div>@endif
<nav class="exp-tabs" aria-label="Rejestry faktur">
<a class="exp-btn {{$tab==='own'?'active':''}}" href="{{route('expenses.index')}}">Moje faktury</a>
<a class="exp-btn {{$tab==='pool'?'active':''}}" href="{{route('expenses.index',['tab'=>'pool'])}}">Faktury do przypisania @if($poolCount > 0)({{ $poolCount }})@endif</a>
@if($accounting)<a class="exp-btn {{$tab==='all'?'active':''}}" href="{{route('expenses.index',['tab'=>'all'])}}">Wszystkie — księgowość</a>@endif
</nav>
@if($tab==='pool')<p class="exp-muted">Tutaj znajdują się wyłącznie faktury opublikowane przez księgowość. Rozpoznajesz swój zakup? Kliknij „Moja”, aby przypisać fakturę do siebie.</p>@endif
<form id="expense-filters" class="exp-filters" method="GET"><input type="hidden" name="tab" value="{{$tab}}"><label>Status <select name="workflow"><option value="">Wszystkie</option><option value="pending" @selected(request("workflow")==="pending")>Do obsłużenia</option>@foreach($workflowLabels as $value=>$label)<option value="{{$value}}" @selected(request("workflow")===$value)>{{$label}}</option>@endforeach</select></label><label>Miesiąc <input name="month" type="month" value="{{request('month')}}"></label><label>Szukaj <input name="search" value="{{request('search')}}" maxlength="100" placeholder="Numer faktury, kupujący, koszt…"></label><label>Projekt <select name="project_id"><option value="">Wszystkie</option>@foreach($filterProjects as $project)<option value="{{$project->id}}" @selected((string)request('project_id')===(string)$project->id)>{{$project->number}} — {{$project->name}}</option>@endforeach</select></label>
<label>Kwota brutto od <input title="Jedna kwota oznacza dokładne dopasowanie. Podaj obie kwoty, aby wyszukać zakres włącznie." name="amount_from" type="number" min="0" step="0.01" value="{{request('amount_from')}}"></label>
<label>Kwota brutto do <input name="amount_to" type="number" min="0" step="0.01" value="{{request('amount_to')}}"></label>
<label>Data zakupu od <input title="Jedna data oznacza dokładne dopasowanie. Podaj obie daty, aby wyszukać zakres włącznie." name="date_from" type="date" value="{{request('date_from')}}"></label>
<label>Data zakupu do <input name="date_to" type="date" value="{{request('date_to')}}"></label>
<div class="exp-filter-actions"><button class="exp-btn" type="submit">Filtruj</button><a class="exp-btn" href="{{route('expenses.index',['tab'=>$tab])}}">Wyczyść</a></div></form><p id="expense-filter-status" class="exp-muted" role="status"></p>
@if($errors->any())<div class="exp-message error">{{$errors->first()}}</div>@endif
<div id="expense-results" aria-live="polite">@include('expenses.results')</div>
@endsection

@push('scripts')<script src="{{asset('js/expense-filters.js')}}?v=4" defer></script>@endpush
