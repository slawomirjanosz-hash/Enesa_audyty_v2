<div class="ankbox" data-profile-cards>@include('audits.partials.questionnaire-point-heading',['pointTitle'=>$htmlTitle,'pointHelp'=>'ENESA_SOURCE_QUESTIONNAIRE_HELP'])
@forelse($htmlCards as $card)
<div class="iso-profile-entry">
@include('audits.partials.questionnaire-launch-heading', ['launchName'=>$card['name'], 'launchStatus'=>$card['status'], 'launchLabel'=>'Otwórz ankietę', 'launchProfileId'=>$card['profile_id']])
<div class="iso-profile-meta">@include('audits.partials.questionnaire-status',['statusRecord'=>$card['review'],'statusStale'=>$card['stale']])<span class="iso-profile-responsible">Odpowiedzialny: {{$card['responsible_name']??'Nieprzypisana'}}</span></div>
<p class="op">Wypełnienie: {{$card['progress']['percent']}}%</p>
<div class="tor"><div style="width:{{$card['progress']['percent']}}%"></div></div>
</div>
@empty
<p class="op">{{$htmlAudit?'Najpierw utwórz profil zakładu we Wstępie do ISO.':'Podgląd ankiety i specyfikacji bez zakładania klienta.'}}</p>
<button type="button" class="prim" onclick="widok('ankieta')">{{$htmlAudit?'Otwórz profile zakładów':'Otwórz podgląd ankiety'}}</button>
@endforelse</div>
