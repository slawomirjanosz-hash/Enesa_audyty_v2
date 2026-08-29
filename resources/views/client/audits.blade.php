@extends('layouts.client')
@section('title', 'Moje audyty')
@section('page-title', 'Moje audyty')
@section('content')
<div style="margin-bottom:22px"><h1 style="font-size:22px;color:var(--green);margin:0 0 5px"><i class="ti ti-clipboard-check"></i> Moje audyty</h1><p style="font-size:13px;color:#68766e;margin:0">Audyty prowadzone dla firmy <strong>{{$company->name}}</strong>.</p></div>
@include('client.partials.audits-list')
@endsection
