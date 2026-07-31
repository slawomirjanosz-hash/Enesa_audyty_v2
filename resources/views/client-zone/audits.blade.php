@extends('layouts.client-zone')

@section('page-title', 'Moje audyty')

@section('content')
<div style="background:#fff;border-radius:12px;padding:40px;box-shadow:0 2px 8px rgba(0,0,0,.07);text-align:center;color:#6b7a72;">
    <i class="ti ti-clipboard-check" style="font-size:56px;color:#c8d5cf;display:block;margin-bottom:16px;"></i>
    <h2 style="font-size:20px;font-weight:700;color:var(--green);margin-bottom:8px;">Moje audyty</h2>
    <p style="font-size:14px;">W budowie — Audyty firmy <strong>{{ $company->name }}</strong></p>
</div>
@endsection
