<div class="ankbox" data-profile-cards><h3>{{$htmlTitle}}</h3>
@forelse($htmlCards as $card)
<div style="padding:14px 0">
@include('audits.partials.questionnaire-launch-heading', ['launchName'=>$card['name'], 'launchStatus'=>$card['status'], 'launchLabel'=>'Otwórz ankietę', 'launchProfileId'=>$card['profile_id']])
<p class="op">Wypełnienie: {{$card['progress']['percent']}}%</p>
<div class="tor"><div style="width:{{$card['progress']['percent']}}%"></div></div>
@include('audits.partials.questionnaire-status',['statusRecord'=>$card['review'],'statusStale'=>$card['stale']])
</div>
@empty
<p class="op">{{$htmlAudit?'Najpierw utwórz profil zakładu we Wstępie do ISO.':'Podgląd ankiety i specyfikacji bez zakładania klienta.'}}</p>
<button type="button" class="prim" onclick="widok('ankieta')">{{$htmlAudit?'Otwórz profile zakładów':'Otwórz podgląd ankiety'}}</button>
@endforelse</div>
