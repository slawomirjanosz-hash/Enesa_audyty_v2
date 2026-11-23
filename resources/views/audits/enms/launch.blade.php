@php
$enmsProfile=$card['profile'];
$enmsWorkspace=\App\Services\Enms\Workspace::for(auth()->user(),$audit,$enmsProfile,$clientView??false);
$enmsStatus=app(\App\Services\Enms\LeadershipWorkflow::class)->status($enmsWorkspace);
$enmsProgress=$card['leadership_progress'];
@endphp
@include('audits.partials.questionnaire-launch-heading', ['launchName'=>$card['name'], 'launchStatus'=>$enmsStatus['label'], 'launchLabel'=>'Otwórz ankietę 5.1', 'launchUrl'=>route(($clientView??false)?'client.audits.enms.show':'audits.enms.show',[$audit,$enmsProfile])])
<div class="iso-profile-meta">@include('audits.enms.status',['leadershipStatus'=>$enmsStatus])<span class="iso-profile-responsible">Odpowiedzialny: {{$card['responsible_name']??'Nieprzypisana'}}</span></div>
@include('partials.questionnaire-progress',['progress'=>$enmsProgress])
