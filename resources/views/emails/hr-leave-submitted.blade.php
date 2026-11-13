@extends('emails.layout')
@section('mail-title', 'Nowy wniosek urlopowy')
@section('mail-content')
<p>Masz nowy wniosek urlopowy do rozpatrzenia. Zaloguj się, aby zobaczyć szczegóły i zapisać decyzję.</p>
<p><a href="{{route('hr.index', ['tab'=>'leaves', 'leave_scope'=>'team'])}}#leave-{{$leaveId}}">Otwórz wniosek urlopowy</a></p>
@endsection
