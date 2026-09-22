@if($canManage)<div class="aw-actions"><button class="aw-btn" onclick="openAwModal('survey-modal')"><i class="ti ti-plus"></i> Dodaj audyt</button></div>@endif
<div class="aw-card"><h2>Audyt</h2>
<div style="overflow-x:auto"><table class="aw-table"><thead><tr><th>Nazwa</th><th>Rodzaj audytu</th><th>Status</th><th>Notatki</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($audit->surveys as $survey)
<tr><td><strong>{{$survey->title}}</strong></td><td>{{$survey->auditType?->name ?? '—'}}</td><td>{{['draft'=>'Robocza','ready'=>'Gotowa','completed'=>'Wypełniona'][$survey->status]??$survey->status}}</td><td>{{$survey->notes}}</td><td><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
@if($survey->auditType?->slug==='iso50001')
<a class="aw-btn" href="{{route($clientView ? 'client.audits.show' : 'audits.show',['audit'=>$audit,'tab'=>'iso50001'])}}" onclick="event.preventDefault();showAuditTab('iso50001')">Otwórz audyt</a>
@elseif($survey->auditType?->slug==='energy-passports')
<a class="aw-btn" href="{{route($clientView ? 'client.audits.show' : 'audits.show',['audit'=>$audit,'tab'=>'passports'])}}" onclick="event.preventDefault();showAuditTab('passports')">Otwórz audyt</a>
@else
<button type="button" class="aw-btn" onclick="openAwModal('survey-view-{{$survey->id}}')">Otwórz audyt</button>
@endif
@if($canManage)<form method="POST" action="{{route('audits.surveys.destroy',[$audit,$survey])}}">@csrf @method('DELETE')<button class="aw-btn red">Usuń</button></form>@endif
</div></td></tr>
@empty<tr><td colspan="5" class="aw-empty">Dodaj pierwszy audyt i wybierz jego rodzaj.</td></tr>@endforelse
</tbody></table></div></div>
@foreach($audit->surveys->filter(fn($survey)=>$survey->auditType?->slug!=='iso50001') as $survey)
<div id="survey-view-{{$survey->id}}" class="aw-modal"><div class="aw-modal-box"><h2>{{$survey->title}}</h2><p>{{$survey->auditType?->name}}</p>
@if($canManage)<form method="POST" action="{{route('audits.surveys.update',[$audit,$survey])}}">@csrf @method('PUT')<div class="aw-field"><label>Status</label><select class="aw-input" name="status">@foreach(['draft'=>'Robocza','ready'=>'Gotowa','completed'=>'Wypełniona'] as $value=>$label)<option value="{{$value}}" @selected($survey->status===$value)>{{$label}}</option>@endforeach</select></div><div class="aw-field"><label>Notatki z audytu</label><textarea class="aw-input" name="notes" maxlength="10000" rows="8">{{$survey->notes}}</textarea></div><button class="aw-btn">Zapisz</button></form>
@else<p style="white-space:pre-wrap">{{$survey->notes ?: 'Brak notatek.'}}</p>@endif
<button type="button" class="aw-btn soft" onclick="closeAwModal('survey-view-{{$survey->id}}')">Zamknij</button></div></div>
@endforeach
