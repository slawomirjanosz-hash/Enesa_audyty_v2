@extends('emails.layout')
@section('mail-title', 'Wniosek urlopowy zaakceptowany')
@section('mail-content')
<p>Twój wniosek urlopowy został zaakceptowany przez <strong>{{$leave->approver_name}}</strong>.</p>
<p><strong>{{\App\Models\HrLeave::TYPES[$leave->type]}}</strong><br>
Termin: {{$leave->start_date->format('d.m.Y')}} – {{$leave->end_date->format('d.m.Y')}}<br>
Liczba dni: {{$leave->days}}</p>
<p><a href="{{route('hr.index', ['tab'=>'leaves', 'leave_scope'=>'own'])}}">Zobacz swoje wnioski urlopowe</a></p>
@endsection
