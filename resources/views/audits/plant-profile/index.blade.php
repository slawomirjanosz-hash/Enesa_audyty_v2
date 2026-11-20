@extends('audits.plant-profile.layout')
@section('content')
<section class="plant-card"><h2>Zakłady i wersje profilu</h2><p>Każdy zakład ma własny profil. Zatwierdzone wersje pozostają dostępne w historii.</p>
@include('audits.plant-profile.list',['profileList'=>$profiles,'profileUsers'=>$responsibleUsers,'profilesCanWrite'=>$canWrite,'profilePrefix'=>$prefix])
</section>
@if($canWrite)<section class="plant-card" id="add-profile"><h2>Dodaj profil zakładu</h2><form method="post" action="{{ route($prefix.'create',$audit) }}">@csrf
<label>Istniejący zakład<select name="site_id"><option value="">Nowy zakład</option>@foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->name }}</option>@endforeach</select></label>
<label>Nazwa nowego zakładu<input name="name" maxlength="200" placeholder="Np. Zakład produkcyjny w Pile"></label>
<label>Osoba odpowiedzialna<select name="responsible_user_id"><option value="">Nieprzypisana</option>@foreach($responsibleUsers as $person)<option value="{{$person->id}}">{{$person->name}}</option>@endforeach</select></label>
<label class="plant-check"><input type="checkbox" name="copy_latest" value="1"> Skopiuj ostatni zatwierdzony profil wybranego zakładu. Daty danych pozostaną bez zmian; potrzebne będą nowe zatwierdzenia.</label>
<button class="primary">Dodaj profil zakładu</button></form></section>@endif
@endsection
