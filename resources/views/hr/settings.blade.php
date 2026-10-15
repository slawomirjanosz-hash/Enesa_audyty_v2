@extends('layouts.app')
@section('title','Ustawienia HR')
@section('page-title','Ustawienia HR')
@section('content')
<style>
.hr-settings-head{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px}.hr-settings-head h1{margin:0;color:var(--green)}.hr-settings-head p{color:#6b7280;margin:6px 0 0}.hr-settings-grid{display:grid;grid-template-columns:220px minmax(0,1fr);gap:24px}.hr-settings-nav{display:flex;flex-direction:column;gap:6px}.hr-settings-nav a{padding:14px 16px;border-radius:8px;text-decoration:none;color:var(--green);background:white}.hr-settings-nav a.active{background:var(--green);color:white;font-weight:700}.hr-settings-card{background:white;padding:26px;border:1px solid #e5e1d8;border-radius:12px}.hr-settings-card h2{margin:0 0 16px}.hr-settings-card p{line-height:1.6}.hr-settings-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-bottom:20px}.hr-settings-field label{display:block;font-weight:600;margin-bottom:8px}.hr-input{padding:10px;border:1px solid #cec8bc;border-radius:7px;max-width:100%;box-sizing:border-box}.hr-settings-field input{width:100%}.hr-btn{display:inline-block;border:0;border-radius:7px;padding:10px 14px;background:var(--green);color:white;font-weight:700;text-decoration:none;cursor:pointer}.hr-btn.danger{background:#fee2e2;color:#991b1b}.hr-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.hr-table{width:100%;border-collapse:collapse}.hr-table th,.hr-table td{padding:12px 8px;text-align:left;border-bottom:1px solid #eee}.hr-settings-message{padding:14px;border-radius:8px;margin-bottom:18px;background:#ecfdf5;color:#166534}.hr-settings-message.error{background:#fef2f2;color:#991b1b}.hr-settings-note{padding:16px;background:#f5f7f5;border-radius:8px;margin-bottom:24px}@media(max-width:800px){.hr-settings-grid,.hr-settings-fields{grid-template-columns:1fr}.hr-settings-nav{flex-direction:row;flex-wrap:wrap}.hr-settings-card{padding:18px}.hr-settings-head{align-items:flex-start}}
</style>
<div class="hr-settings-head"><div><h1>Ustawienia HR</h1><p>Wybierz dział, którego ustawienia chcesz zmienić.</p></div><a class="hr-btn" href="{{route('hr.index',['tab'=>$section])}}">Wróć do HR</a></div>
@if(session('success'))<div class="hr-settings-message">{{session('success')}}</div>@endif
@if($errors->any())<div class="hr-settings-message error" role="alert">{{$errors->first()}}</div>@endif
<div class="hr-settings-grid">
<nav class="hr-settings-nav" aria-label="Sekcje ustawień HR">@foreach($sections as $key=>$label)<a href="{{route('hr.settings',['section'=>$key])}}" class="{{$section===$key?'active':''}}" @if($section===$key) aria-current="page" @endif>{{$label}}</a>@endforeach</nav>
<main class="hr-settings-card">
<h2>{{$sections[$section]}}</h2>
@if($section==='delegations')
<form method="POST" action="{{route('hr.settings.update')}}">@csrf @method('PUT')
<div class="hr-settings-fields"><div class="hr-settings-field"><label for="hr-km-rate">Stawka za km dla auta prywatnego (zł)</label><input class="hr-input" id="hr-km-rate" name="hr_km_rate" type="number" min="0" max="9999" step="0.0001" value="{{old('hr_km_rate',$settings?->hr_km_rate ?? 0)}}" required></div>
<div class="hr-settings-field"><label for="hr-diet-rate">Stawka pełnej diety (zł)</label><input class="hr-input" id="hr-diet-rate" name="hr_diet_rate" type="number" min="0" max="9999" step="0.01" value="{{old('hr_diet_rate',$settings?->hr_diet_rate ?? 45)}}" required></div></div><button class="hr-btn">Zapisz ustawienia delegacji</button></form>
@elseif($section==='vehicles')
<div class="hr-settings-note"><h3 style="margin-top:0">Odpowiedzialność za samochody</h3><p>Odbiorców powiadomień określa opcja <strong>Odpowiedzialność za samochody</strong> w sekcji HR ustawień roli użytkownika. Każdy aktywny użytkownik z taką rolą otrzymuje informacje o autach służbowych.</p>
@if(auth()->user()->hasRole('superadmin') || auth()->user()->can('settings.roles.manage'))<a class="hr-btn" href="{{route('settings.roles.index')}}">Przejdź do ustawień ról</a>@endif
<p>Ostrzeżenia o ubezpieczeniu i przeglądzie: do 14 i do 7 dni przed terminem. Po terminie: codziennie o 8:00. Brakujące dane: w poniedziałki o 8:00 (czas polski). Użytkownik przypisany do konkretnego auta otrzymuje także informacje o swoim samochodzie. Wpis ręczny osoby spoza bazy nie powoduje wysyłki do tej osoby.</p></div>
@include('hr.partials.location-settings')
@elseif($section==='leaves')
<p>Urlopy i nieobecności są rozliczane według danych pracownika i jego rocznego wymiaru urlopu. Wliczanie weekendów wybiera się w formularzu konkretnej nieobecności.</p><p>Ta sekcja nie wymaga obecnie dodatkowych ustawień wspólnych.</p>
@else
<p>Status, godziny i notatki uzupełnia się przy wpisie obecności pracownika.</p><p>Ta sekcja nie wymaga obecnie dodatkowych ustawień wspólnych.</p>
@endif
</main></div>
@endsection
