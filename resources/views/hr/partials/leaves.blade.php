<nav class="hr-tabs" aria-label="Wnioski urlopowe">
<a class="hr-tab {{$leaveScope==='own'?'active':''}}" href="{{route('hr.index',['tab'=>'leaves','leave_scope'=>'own'])}}">Moje wnioski</a>
@if($hasReports)<a class="hr-tab {{$leaveScope==='team'?'active':''}}" href="{{route('hr.index',['tab'=>'leaves','leave_scope'=>'team'])}}">Urlopy pracowników — do rozpatrzenia</a>@endif
@if($canAllLeaves)<a class="hr-tab {{$leaveScope==='all'?'active':''}}" href="{{route('hr.index',['tab'=>'leaves','leave_scope'=>'all'])}}">Wszystkie wnioski — księgowość</a>@endif
</nav>
<div class="hr-card"><div class="hr-card-head"><strong>Wnioski urlopowe i L4</strong>@if(auth()->user()->hasRole('superadmin') || auth()->user()->canAny(['hr.leaves.view','system.full_access']))<button class="hr-btn" onclick="openNewLeave()">Złóż wniosek / zarejestruj L4</button>@endif</div><div style="overflow:auto"><table class="hr-table"><thead><tr><th>Pracownik</th><th>Rodzaj</th><th>Od</th><th>Do</th><th>Dni</th><th>Status</th><th>Przełożony</th><th>Uwagi / decyzja</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($leaves as $leave)
@php
 $editable = in_array($leave->approval_status,['legacy','recorded'],true) ? ($leave->user_id===auth()->id() || $canTeam) : ($leave->user_id===auth()->id() && in_array($leave->approval_status,['pending','rejected'],true));
 $leavePayload = $leave->only(['id','user_id','type','days','include_weekends','notes','approval_revision'])+['document_date'=>$leave->document_date?->toDateString(),'start_date'=>$leave->start_date->toDateString()];
@endphp
<tr id="leave-{{$leave->id}}"><td>{{$leave->user?->name}}</td><td>{{\App\Models\HrLeave::TYPES[$leave->type]}}</td><td data-sort-value="{{$leave->start_date->toDateString()}}">{{$leave->start_date->format('d.m.Y')}}</td><td data-sort-value="{{$leave->end_date->toDateString()}}">{{$leave->end_date->format('d.m.Y')}} ({{$leave->end_date->locale('pl')->dayName}})</td><td data-sort-value="{{$leave->days}}">{{$leave->days}}</td><td><strong>{{\App\Models\HrLeave::APPROVAL_STATUSES[$leave->approval_status]}}</strong>@if($leave->decided_at)<br>{{$leave->decided_at->format('d.m.Y H:i')}}@endif</td><td>{{$leave->approver_name ?: '—'}}</td><td>{{$leave->notes ?: '—'}}@if($leave->decision_note)<br>Decyzja: {{$leave->decision_note}}@endif</td><td><div class="hr-actions">
@if($editable)<button class="hr-btn" type="button" onclick="editLeave(@js($leavePayload))">{{$leave->approval_status==='rejected'?'Popraw i wyślij ponownie':'Edytuj'}}</button><form method="POST" action="{{route('hr.leaves.destroy',$leave)}}" onsubmit="return confirm('Usunąć ten wniosek lub wpis?')">@csrf @method('DELETE')<input type="hidden" name="approval_revision" value="{{$leave->approval_revision}}"><button class="hr-btn danger">Usuń</button></form>@endif
<a class="hr-btn" href="{{route('hr.leaves.pdf',$leave)}}" title="Pobierz PDF">PDF</a>
@if($leave->approval_status==='pending' && $leave->approver_id===auth()->id() && $leave->user_id!==auth()->id())
<form method="POST" action="{{route('hr.leaves.decide',$leave)}}" onsubmit="return confirm('Zapisać decyzję i zakończyć zadanie?')">@csrf<input type="hidden" name="approval_revision" value="{{$leave->approval_revision}}"><label>Uzasadnienie (opcjonalnie)<input class="hr-input" name="decision_note" maxlength="2000"></label><button class="hr-btn" name="decision" value="approved">Akceptuj</button><button class="hr-btn danger" name="decision" value="rejected">Odrzuć</button></form>
@endif
</div></td></tr>
@empty<tr><td colspan="9" class="hr-empty">Brak wniosków w tym widoku.</td></tr>@endforelse
</tbody></table></div></div>
