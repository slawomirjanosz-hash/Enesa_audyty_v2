@extends('audits.plant-profile.layout')
@section('content')
<section class="plant-card"><h2>Zakłady i wersje profilu</h2><p>Każdy zakład ma własny profil. Zatwierdzone wersje pozostają dostępne w historii.</p>
<div class="plant-scroll"><table><thead><tr><th>Zakład</th><th>Stan na dzień</th><th>Wersja</th><th>Status</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@foreach($profiles as $profile)<tr><td>{{ $profile->answers['site.name']['value'] ?? $sites->firstWhere('id',$profile->site_id)?->name }}</td><td data-sort-value="{{ $profile->as_of_date->format('Y-m-d') }}">{{ $profile->as_of_date->format('d.m.Y') }}</td><td data-sort-value="{{ $profile->revision }}">{{ $profile->revision }}</td><td>{{ \App\Models\IsoPlantProfile::STATUSES[$profile->status] }}</td><td><a class="plant-button" href="{{ route($prefix.'show',[$audit,$profile]) }}">Otwórz profil</a></td></tr>@endforeach
</tbody></table></div>
@if($profiles->isEmpty())<p>Nie dodano jeszcze profilu zakładu.</p>@endif</section>
@if($canWrite)<section class="plant-card"><h2>Dodaj profil do audytu</h2><form method="post" action="{{ route($prefix.'create',$audit) }}">@csrf
<label>Istniejący zakład<select name="site_id"><option value="">Nowy zakład</option>@foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->name }}</option>@endforeach</select></label>
<label>Nazwa nowego zakładu<input name="name" maxlength="200" placeholder="Np. Zakład produkcyjny w Pile"></label>
<label class="plant-check"><input type="checkbox" name="copy_latest" value="1"> Skopiuj ostatni zatwierdzony profil wybranego zakładu. Daty danych pozostaną bez zmian; potrzebne będą nowe zatwierdzenia.</label>
<button class="primary">Dodaj / otwórz profil</button></form></section>@endif
@endsection
