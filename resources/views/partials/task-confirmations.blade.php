@php
    $confirmationPeople = $completionTask->participants;
    $responsible = $completionTask instanceof \App\Models\BoardTask ? $completionTask->assignee : $completionTask->assignedUser;
    $confirmationPeople = collect([$responsible])->filter()->merge($confirmationPeople)->unique('id');
    $confirmations = $completionTask->completion_confirmations ?? [];
@endphp
<span style="display:inline-flex;flex-wrap:wrap;gap:3px 8px;font-size:12px;" data-task-confirmations>
@forelse($confirmationPeople as $person)
    <span>{{ $person->name }}@if($confirmationPeople->count() > 1)<small title="{{ isset($confirmations[$person->id]) ? 'Potwierdzono wykonanie' : 'Oczekuje na potwierdzenie' }}" style="color:{{ isset($confirmations[$person->id]) ? '#15803d' : '#9ca3af' }};">{{ isset($confirmations[$person->id]) ? '✓' : '·' }}</small>@endif</span>
@empty<span>Nieprzypisane</span>@endforelse
</span>
@if($confirmationPeople->count() > 1)<small style="display:block;color:#7a8a80;font-size:10px;">Potwierdzenia: {{ $confirmationPeople->filter(fn ($person) => isset($confirmations[$person->id]))->count() }}/{{ $confirmationPeople->count() }}</small>@endif
