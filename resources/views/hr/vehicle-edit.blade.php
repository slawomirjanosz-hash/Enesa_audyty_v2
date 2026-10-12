@extends('layouts.app')
@section('page-title','HR — edycja samochodu')
@section('content')
<style>.vehicle-edit{max-width:1000px;margin:auto;background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:24px}.hr-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.hr-field label{display:block;font-size:12px;font-weight:700;margin-bottom:6px}.hr-field input,.hr-field select{width:100%;padding:10px;border:1px solid #cad6d0;border-radius:7px}.vehicle-error{color:#b91c1c}.vehicle-edit-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:20px}.vehicle-edit-actions a,.vehicle-edit-actions button{padding:10px 16px;border:1px solid #cad6d0;border-radius:7px;text-decoration:none}.vehicle-edit-actions button{background:var(--green);color:#fff;cursor:pointer}@media(max-width:850px){.hr-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:550px){.hr-grid{grid-template-columns:1fr}}</style>
<div class="vehicle-edit"><h1>Edytuj samochód</h1><p style="margin:8px 0 24px">{{$vehicle->name}} · {{$vehicle->registration_number}}</p>
@if($errors->getBag('vehicle')->any())<p role="alert" class="vehicle-error">Popraw zaznaczone pola. Dane nie zostały zapisane.</p>@endif
<form method="POST" action="{{route('hr.vehicles.update',$vehicle)}}">@csrf @method('PUT')
@include('hr.partials.vehicle-fields')
<div class="vehicle-edit-actions"><a href="{{route('hr.index',['tab'=>'vehicles'])}}">Anuluj</a><button type="submit">Zapisz samochód</button></div></form></div>
@endsection
