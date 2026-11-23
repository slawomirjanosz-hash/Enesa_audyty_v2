@once<link rel="stylesheet" href="{{asset('css/iso-questionnaire-overview.css')}}?v={{filemtime(public_path('css/iso-questionnaire-overview.css'))}}">@endonce
<div class="plant-profile-list"><table class="plant-profile-table {{ ($clientView??false) ? 'plant-profile-table-client' : '' }}"><thead><tr><th data-sortable="false">Lp.</th><th>Profil zakładu</th><th>Status</th><th>Osoba odpowiedzialna</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($profileList as $listedProfile)
@php($profileName=$listedProfile->answers['site.name']['value']??'Zakład')
<tr><td class="iso-profile-number" data-sort-value="{{$loop->iteration}}">{{$loop->iteration}}</td><td class="profile-name-cell" data-sort-value="{{$profileName}}"><div class="profile-identity"><strong>{{$profileName}}</strong><small style="display:block">Wersja {{$listedProfile->revision}}</small></div><div class="profile-completion">@include('partials.questionnaire-progress',['progress'=>app(\App\Services\QuestionnaireCompletion::class)->plant($listedProfile->definition,$listedProfile->answers,$profileList->unique('site_id')->count())])</div></td>
<td class="profile-status-cell" data-sort-value="{{\App\Models\IsoPlantProfile::STATUSES[$listedProfile->status]}}">@include('audits.plant-profile.status',['profile'=>$listedProfile])</td>
<td class="profile-responsible-cell" data-sort-value="{{$listedProfile->responsibleUser?->name??''}}">
@if($profilesCanWrite)
<form class="plant-profile-assignment" method="post" action="{{route($profilePrefix.'responsible',[$audit,$listedProfile])}}">@csrf @method('PATCH')
<select name="responsible_user_id" aria-label="Osoba odpowiedzialna: {{$profileName}}"><option value="">Nieprzypisana</option>@foreach($profileUsers as $person)<option value="{{$person->id}}" @selected($listedProfile->responsible_user_id===$person->id)>{{$person->name}}</option>@endforeach</select>
<button type="submit" class="plant-profile-icon" title="Zapisz osobę odpowiedzialną" aria-label="Zapisz osobę odpowiedzialną"><i class="ti ti-check" aria-hidden="true"></i></button></form>
@else {{$listedProfile->responsibleUser?->name??'Nieprzypisana'}} @endif
</td><td class="profile-actions-cell"><div class="plant-profile-actions">
@if($clientView??false)
<a class="iso-action-btn primary" data-action-icons="false" href="{{route($profilePrefix.'show',[$audit,$listedProfile])}}" title="Wypełnij ankietę — {{$profileName}}">Wypełnij ankietę</a>
@else
<a class="plant-profile-icon" href="{{route($profilePrefix.'show',[$audit,$listedProfile,'preview'=>1])}}" title="Podgląd profilu" aria-label="Podgląd profilu"><i class="ti ti-eye" aria-hidden="true"></i></a>
@if($profilesCanWrite)
<a class="plant-profile-icon" href="{{route($profilePrefix.'show',[$audit,$listedProfile])}}" title="Edytuj profil" aria-label="Edytuj profil"><i class="ti ti-pencil" aria-hidden="true"></i></a>
<form method="post" action="{{route($profilePrefix.'copy',[$audit,$listedProfile])}}">@csrf<button class="plant-profile-icon" type="submit" title="Kopiuj profil do nowego zakładu" aria-label="Kopiuj profil do nowego zakładu"><i class="ti ti-copy" aria-hidden="true"></i></button></form>
@endif
@endif
</div></td></tr>
@empty<tr data-sort-fixed><td colspan="5">Nie dodano jeszcze profilu zakładu.</td></tr>@endforelse
</tbody></table></div>
