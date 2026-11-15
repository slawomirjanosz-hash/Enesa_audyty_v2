@php
$enmsProfile=\App\Models\IsoPlantProfile::find($card['profile_id']);
$enmsWorkspace=\App\Services\Enms\Workspace::for(auth()->user(),$audit,$enmsProfile,$clientView??false);
$enmsStatus=app(\App\Services\Enms\LeadershipWorkflow::class)->status($enmsWorkspace);
$enmsProgress=app(\App\Services\Enms\LeadershipProgress::class)->forAudits([$audit->id])[$audit->id.':'.$enmsProfile->site_id]??['answered'=>0,'total'=>13,'percent'=>0];
@endphp
<div class="plant-notice" style="margin-top:12px"><strong>Nowy model 5.1 · {{ $enmsStatus['label'] }}</strong>
@include('partials.questionnaire-progress',['progress'=>$enmsProgress])
<p>Wspólne dowody, ocena kryteriów, upoważnienia i kontrolowane wydania dokumentów.</p><a class="iso-action-btn primary" href="{{ route(($clientView??false)?'client.audits.enms.show':'audits.enms.show',[$audit,$enmsProfile]) }}">Otwórz ankietę 5.1 — nowy model</a>
@if($enmsStatus['changed'])<p>Dane lub źródła zmieniły się — wymagają ponownego przeglądu i zatwierdzenia.</p>@endif</div>
