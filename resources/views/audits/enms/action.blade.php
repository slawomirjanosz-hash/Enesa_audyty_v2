@php
$acts=match($target['kind']){
 'rule'=>['resolve_rule'=>'Rozstrzygnij zasadę'],
 'document_draft'=>['issue_document'=>'Wydaj kontrolowaną wersję'],
 'task'=>['complete_task'=>'Zapisz wykonanie','approve_task_result'=>'Zatwierdź rezultat','assess_effectiveness'=>'Oceń skuteczność'],
 'decision'=>['resolve_decision'=>'Rozstrzygnij postanowienie'],
 'deadline'=>['approve_deadline'=>'Zatwierdź termin'],
 'authority'=>['revoke_authority'=>'Cofnij upoważnienie'],
 'document'=>['approve_document'=>'Zatwierdź wersję','activate_document'=>'Ustal wejście w życie','withdraw_document'=>'Wycofaj wersję'],
 'effectiveness'=>['approve_effectiveness'=>'Zatwierdź w ramach nadzoru'], default=>[]};
$myGrants=array_filter($w->records('authority'),fn($g)=>(int)$g['payload']['user_id']===$w->actor->id || (int)($g['payload']['substitute_id']??0)===$w->actor->id);
@endphp
@if($w->writable && $acts)<details><summary>Czynności wymagające upoważnienia</summary>
<form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@include('audits.enms.token')<input type="hidden" name="operation" value="authorized"><input type="hidden" name="record_id" value="{{ $target['id'] }}">
<label>Czynność<select name="action">@foreach($acts as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
<label>Upoważnienie<select name="grant_id" required><option value="">Wybierz podstawę</option>@foreach($myGrants as $g)<option value="{{ $g['id'] }}">{{ $g['symbol'] }} · {{ config('enms.action_classes.'.$g['payload']['class']) }} · {{ $g['payload']['activity']==='wykonanie'?'Wykonanie':'Zatwierdzenie rezultatu' }}</option>@endforeach</select></label>
<label>Dowód czynności / decyzji<select name="payload[evidence_id]" required><option value="">Wybierz dowód</option>@foreach($options['evidence']??[] as $id=>$label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></label>
<label>Uzasadnienie i zakres decyzji<textarea name="payload[text]" required></textarea></label>
@if($target['kind']==='decision')<label>Wynik decyzji<select name="payload[decision_state]"><option value="podjeta">Podjęta</option><option value="odroczona">Odroczona</option><option value="odrzucona">Odrzucona</option></select></label>@endif
@if($target['kind']==='rule')<label>Rozstrzygnięcie kierownictwa<select name="payload[rule_state]" required><option value="">Wybierz</option><option value="przyjeta">Zasada przyjęta</option><option value="nieprzyjeta">Zasada nieprzyjęta</option></select></label>@endif
@if($target['kind']==='document')<label>Data wejścia w życie<input type="date" name="payload[effective_from]"></label><label class="enms-check"><input type="checkbox" name="payload[document_hash]" value="{{ $target['payload']['file_hash'] }}" required>Dowód obejmuje treść tej wersji dokumentu (SHA‑256: {{ $target['payload']['file_hash'] }}).</label>@endif
@if($target['kind']==='task')<details><summary>Pola oceny skuteczności</summary>
<label>Wynik<select name="payload[wynik]"><option value="">Wybierz</option><option value="skuteczne">Skuteczne</option><option value="nieskuteczne">Nieskuteczne</option><option value="zawczesnie">Za wcześnie na ocenę</option></select></label>
<label>Metoda oceny<textarea name="payload[metoda]"></textarea></label>
<label>Dowód kompetencji oceniającego<select name="payload[competence_id]"><option value="">Wybierz</option>@foreach($options['evidence']??[] as $id=>$label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></label><p>Samoocena wymaga osobnego zatwierdzenia w ramach nadzoru. Ocena niezależna sprawdza konflikt właściciela, autora rekomendacji, wykonawcy i odpowiedzialnego za obszar.</p></details>@endif
<button>Zapisz czynność</button></form></details>@endif
@if($w->writable && $acts)
@php($pendingKeys=[])
@foreach($w->records('approval') as $pending)
@if($pending['payload']['target_id']===$target['id'] && $pending['payload']['target_version']===$target['version'] && !in_array($pending['payload']['key'],$pendingKeys,true) && !$service->one($w,'authorized_action','key',$pending['payload']['key']))
@php($pendingKeys[]=$pending['payload']['key'])
<details><summary>Kontynuuj zatwierdzenie wielostopniowe</summary><p>{{ $acts[$pending['payload']['operation']]??'Czynność' }}: {{ $pending['payload']['data']['text'] }}</p><p>Wymagane stopnie: {{ $pending['payload']['required_steps'] }}. Przedmiot i treść pozostają takie jak w pierwszej zgodzie.</p>
<form method="post" action="{{ route($prefix.'update',[$audit,$profile]) }}">@include('audits.enms.token')<input type="hidden" name="operation" value="authorized"><input type="hidden" name="record_id" value="{{ $target['id'] }}"><input type="hidden" name="action" value="{{ $pending['payload']['operation'] }}"><input type="hidden" name="pending_approval" value="{{ $pending['id'] }}"><label>Upoważnienie<select name="grant_id" required>@foreach($myGrants as $g)<option value="{{ $g['id'] }}">{{ $g['symbol'] }} · stopień {{ $g['payload']['step'] }}</option>@endforeach</select></label><button>Zapisz kolejny stopień zgody</button></form></details>
@endif @endforeach @endif
