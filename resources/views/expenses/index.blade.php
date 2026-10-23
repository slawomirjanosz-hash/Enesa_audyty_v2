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
<form class="exp-filters" method="GET"><input type="hidden" name="tab" value="{{$tab}}"><label>Miesiąc <input name="month" type="month" value="{{request('month')}}"></label><label>Szukaj <input name="search" value="{{request('search')}}" placeholder="Numer faktury, dostawca, miejsce kosztu"></label><button class="exp-btn">Filtruj</button></form>
@if($errors->any())<div class="exp-message error">{{$errors->first()}}</div>@endif
<div class="exp-card exp-table-wrap"><table class="exp-table" data-server-sort="id,purchase_date,invoice_number,wz_number,ksef_number,supplier_name,net_amount,gross_amount,owner_name,payment_method,payment_source,payment_status,paid_by,cost_center,description,status,"><thead><tr>
<th>ID</th><th>Data zakupu</th><th>Nr faktury</th><th>Nr WZ</th><th>Nr KSeF</th><th>Dostawca</th><th>Netto PLN</th><th>Brutto PLN</th><th>Kupujący</th><th>Płatność</th><th>Środki</th><th>Status zapłaty</th><th>Kto zapłacił</th><th>Miejsce kosztu</th><th>Opis</th><th>Rozliczenie</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($invoices as $invoice)<tr>
<td>{{$invoice->id}}</td><td data-sort-value="{{$invoice->purchase_date->toDateString()}}">{{$invoice->purchase_date->format('d.m.Y')}}</td><td><a href="{{route('expenses.edit',$invoice)}}">{{$invoice->invoice_number ?: 'Bez numeru'}}</a></td><td>{{$invoice->wz_number ?: '—'}}</td><td>{{$invoice->ksef_number ?: '—'}}</td><td>{{$invoice->supplier_name}}</td>
@foreach(['net_amount','gross_amount'] as $amount)<td class="exp-number" data-sort-value="{{$invoice->$amount}}">{{$invoice->$amount !== null ? number_format((float)$invoice->$amount,2,',',' ') : '—'}}</td>@endforeach
<td>{{$invoice->owner_name ?: 'Nieprzypisana'}}</td><td>{{\App\Models\ExpenseInvoice::METHODS[$invoice->payment_method]}}</td><td>{{\App\Models\ExpenseInvoice::SOURCES[$invoice->payment_source]}}</td><td>{{\App\Models\ExpenseInvoice::PAYMENTS[$invoice->payment_status]}}</td><td>{{$invoice->paid_by ?: '—'}}</td><td>{{$invoice->cost_center ?: '—'}}</td><td>{{$invoice->description ?: '—'}}</td><td><span class="exp-badge">{{$invoice->statusLabel()}}</span></td><td><a class="exp-btn" href="{{route('expenses.edit',$invoice)}}">Otwórz</a></td>
</tr>@empty<tr><td colspan="17">Brak faktur spełniających wybrane kryteria.</td></tr>@endforelse
</tbody></table></div>{{$invoices->links()}}
@endsection
