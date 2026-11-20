@once
<style>
.plant-profile-table{width:100%;border-collapse:collapse}.plant-profile-table th,.plant-profile-table td{padding:12px 8px;text-align:left;vertical-align:middle;border-bottom:1px solid #dce4df}.plant-profile-table th{background:#f3f7f4}.plant-profile-actions{display:flex;gap:6px;align-items:center;white-space:nowrap}.plant-profile-actions form{display:inline-flex;margin:0}.plant-profile-icon{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;width:32px;min-width:32px;height:32px;padding:0;border:1px solid #bfcfc5;border-radius:6px;background:#fff;color:var(--green,var(--brand,#1a4d3a));text-decoration:none;font-size:17px;cursor:pointer}.plant-profile-icon:hover{background:#edf3ef}.plant-profile-assignment{display:flex;gap:6px;align-items:center;margin:0}.plant-profile-assignment select{width:100%;min-width:130px;max-width:230px;padding:7px;margin:0;border:1px solid #bfcfc5;border-radius:6px;background:#fff;font:inherit}.plant-profile-table .app-table-sort{font:inherit;font-weight:700;border:0;background:transparent;padding:0;cursor:pointer;color:inherit}.plant-profile-table .questionnaire-progress{margin:8px 0}
</style>
@endonce
<div style="overflow-x:auto"><table class="plant-profile-table"><thead><tr><th>Profil zakładu</th><th>Status</th><th>Osoba odpowiedzialna</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($profileList as $listedProfile)
@php($profileName=$listedProfile->answers['site.name']['value']??'Zakład')
<tr><td data-sort-value="{{$profileName}}"><strong>{{$profileName}}</strong><small style="display:block">Wersja {{$listedProfile->revision}}</small>@include('partials.questionnaire-progress',['progress'=>app(\App\Services\QuestionnaireCompletion::class)->plant($listedProfile->definition,$listedProfile->answers,$profileList->unique('site_id')->count())])</td>
<td data-sort-value="{{\App\Models\IsoPlantProfile::STATUSES[$listedProfile->status]}}">@include('audits.plant-profile.status',['profile'=>$listedProfile])</td>
<td data-sort-value="{{$listedProfile->responsibleUser?->name??''}}">
@if($profilesCanWrite)
<form class="plant-profile-assignment" method="post" action="{{route($profilePrefix.'responsible',[$audit,$listedProfile])}}">@csrf @method('PATCH')
<select name="responsible_user_id" aria-label="Osoba odpowiedzialna: {{$profileName}}"><option value="">Nieprzypisana</option>@foreach($profileUsers as $person)<option value="{{$person->id}}" @selected($listedProfile->responsible_user_id===$person->id)>{{$person->name}}</option>@endforeach</select>
<button type="submit" class="plant-profile-icon" title="Zapisz osobę odpowiedzialną" aria-label="Zapisz osobę odpowiedzialną"><i class="ti ti-check" aria-hidden="true"></i></button></form>
@else {{$listedProfile->responsibleUser?->name??'Nieprzypisana'}} @endif
</td><td><div class="plant-profile-actions">
<a class="plant-profile-icon" href="{{route($profilePrefix.'show',[$audit,$listedProfile,'preview'=>1])}}" title="Podgląd profilu" aria-label="Podgląd profilu"><i class="ti ti-eye" aria-hidden="true"></i></a>
@if($profilesCanWrite)
<a class="plant-profile-icon" href="{{route($profilePrefix.'show',[$audit,$listedProfile])}}" title="Edytuj profil" aria-label="Edytuj profil"><i class="ti ti-pencil" aria-hidden="true"></i></a>
<form method="post" action="{{route($profilePrefix.'copy',[$audit,$listedProfile])}}">@csrf<button class="plant-profile-icon" type="submit" title="Kopiuj profil do nowego zakładu" aria-label="Kopiuj profil do nowego zakładu"><i class="ti ti-copy" aria-hidden="true"></i></button></form>
@endif
</div></td></tr>
@empty<tr data-sort-fixed><td colspan="4">Nie dodano jeszcze profilu zakładu.</td></tr>@endforelse
</tbody></table></div>
