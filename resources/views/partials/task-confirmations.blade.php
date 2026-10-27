@php
    $confirmationPeople = $completionTask->participants;
    $responsible = $completionTask instanceof \App\Models\BoardTask ? $completionTask->assignee : $completionTask->assignedUser;
    $confirmationPeople = collect([$responsible])->filter()->merge($confirmationPeople)->unique('id');
    $confirmations = $completionTask->completion_confirmations ?? [];
@endphp
<span style="display:flex;flex-direction:column;gap:6px;font-size:12px;margin-bottom:6px;" data-task-confirmations>
@forelse($confirmationPeople as $person)
    @php
        $personDone = isset($confirmations[$person->id]) || ($confirmationPeople->count() === 1 && $completionTask->status === 'done');
        $personStatus = $personDone ? 'Zrobione' : ($completionTask->status === 'todo' ? 'Do zrobienia' : 'W trakcie');
    @endphp
    <span style="display:flex;align-items:baseline;gap:7px;" data-task-person="{{ $person->id }}" data-completion="{{ $personDone ? 'done' : 'pending' }}">
        <span aria-hidden="true" style="display:inline-block;width:10px;height:10px;flex:0 0 10px;border-radius:50%;background:{{ $personDone ? '#15803d' : '#d97706' }};"></span>
        <span>{{ $person->name }} <small style="color:{{ $personDone ? '#15803d' : '#92400e' }};">— {{ $personStatus }}</small></span>
    </span>
@empty<span>Nieprzypisane</span>@endforelse
</span>
<small style="display:block;color:#7a8a80;font-size:11px;">Wyznaczył(a): {{ $completionTask->createdBy?->name ?? 'Brak informacji' }}</small>
