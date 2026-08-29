@extends('layouts.client-zone')
@section('page-title', 'Moje audyty')
@section('content')
<div style="margin-bottom:20px"><h2 style="font-size:20px;color:var(--green);margin:0 0 5px"><i class="ti ti-clipboard-check"></i> Audyty firmy {{$company->name}}</h2><p style="font-size:13px;color:#68766e;margin:0">Taką listę audytów widzą użytkownicy tego klienta.</p></div>
@include('client.partials.audits-list')
@endsection
