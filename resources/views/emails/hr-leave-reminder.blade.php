@extends('emails.layout')
@section('mail-title', 'Wnioski urlopowe do rozpatrzenia')
@section('mail-content')
<p>Poniższe wnioski nadal oczekują na Twoją decyzję. Urlop rozpoczyna się jutro albo jego termin już nadszedł. Otwórz wniosek i wybierz „Akceptuj” lub „Odrzuć”.</p>
<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr><th style="text-align:left;padding:8px">Pracownik</th><th style="text-align:left;padding:8px">Termin urlopu</th><th style="text-align:left;padding:8px">Wniosek</th></tr></thead>
<tbody>
@foreach($leaves as $leave)
<tr><td style="padding:8px">{{$leave->user?->name ?? 'Pracownik'}}</td><td style="padding:8px">{{$leave->start_date->format('d.m.Y')}} – {{$leave->end_date->format('d.m.Y')}}</td><td style="padding:8px"><a href="{{route('hr.index',['tab'=>'leaves','leave_scope'=>'team'])}}#leave-{{$leave->id}}">Rozpatrz wniosek</a></td></tr>
@endforeach
</tbody></table>
<p>Przypomnienie będzie wysyłane codziennie, dopóki nie zapiszesz decyzji.</p>
@endsection
