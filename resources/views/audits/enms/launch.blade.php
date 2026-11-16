@php
$enmsProfile=\App\Models\IsoPlantProfile::find($card['profile_id']);
$enmsWorkspace=\App\Services\Enms\Workspace::for(auth()->user(),$audit,$enmsProfile,$clientView??false);
$enmsStatus=app(\App\Services\Enms\LeadershipWorkflow::class)->status($enmsWorkspace);
$enmsProgress=app(\App\Services\Enms\LeadershipProgress::class)->forAudits([$audit->id])[$audit->id.':'.$enmsProfile->site_id]??['answered'=>0,'total'=>13,'percent'=>0];
@endphp
<strong>{{ $card['name'] }}</strong> · {{ $enmsStatus['label'] }}
@include('partials.questionnaire-progress',['progress'=>$enmsProgress])
<p>Wersja 0.4. Wcześniejsza ankieta jest dostępna w zakładce Historia.</p>
@if($enmsStatus['changed'])<p>Dane lub źródła zmieniły się — wymagają ponownego przeglądu i zatwierdzenia.</p>@endif
<div class="iso-action-buttons" style="margin-top:12px">
    <a class="iso-action-btn primary" style="display:inline-block;text-decoration:none" href="{{ route(($clientView??false)?'client.audits.enms.show':'audits.enms.show',[$audit,$enmsProfile]) }}">Otwórz ankietę 5.1</a>
</div>
