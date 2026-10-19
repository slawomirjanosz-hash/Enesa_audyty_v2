<div class="ankbox" data-profile-cards><h3>{{$htmlTitle}}</h3>
@forelse($htmlCards as $card)
<div style="padding:14px 0"><strong>{{$card['name']}}</strong><p class="op">{{$card['status']}} · Wypełnienie: {{$card['progress']['percent']}}%</p>
<div class="tor"><div style="width:{{$card['progress']['percent']}}%"></div></div>
@include('audits.partials.questionnaire-status',['statusRecord'=>$card['review'],'statusStale'=>$card['stale']])
<button type="button" class="prim" data-profile="{{$card['profile_id']}}">Otwórz ankietę</button></div>
@empty
<p class="op">{{$htmlAudit?'Najpierw utwórz profil zakładu we Wstępie do ISO.':'Podgląd ankiety i specyfikacji bez zakładania klienta.'}}</p>
<button type="button" class="prim" onclick="widok('ankieta')">{{$htmlAudit?'Otwórz profile zakładów':'Otwórz podgląd ankiety'}}</button>
@endforelse</div>
