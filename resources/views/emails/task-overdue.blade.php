@extends('emails.layout')
@section('mail-title', 'Przekroczone terminy ('.$tasks->count().')')
@section('mail-content')
            <p style="color:#555; font-size:14px; margin:0 0 18px;">
                Minął termin poniższych zadań lub etapów prac. Poniżej podajemy ich aktualny postęp.
            </p>
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="background:#FAFAF6;">
                        <th style="text-align:left; padding:8px 10px; color:#888; font-family:'Manrope',sans-serif; font-size:11px; text-transform:uppercase;">Zadanie</th>
                        <th style="text-align:left; padding:8px 10px; color:#888; font-family:'Manrope',sans-serif; font-size:11px; text-transform:uppercase;">Termin</th>
                        <th style="text-align:left; padding:8px 10px; color:#888; font-family:'Manrope',sans-serif; font-size:11px; text-transform:uppercase;">Firma</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $task)
                    @php
                        $isBoard = $task instanceof \App\Models\BoardTask;
                        $isStage = ! $isBoard && $task->project_id;
                        $taskTab = $isBoard ? 'tasks' : ($task->project_id ? 'gantt' : 'schedule');
                        $taskCompany = $isBoard ? ($task->project?->company ?? $task->audit?->company) : $task->company;
                    @endphp
                    <tr style="border-bottom:1px solid #F0EDE6;">
                        <td style="padding:10px; font-weight:600; color:#1A1A1A;"><a href="{{ $task->project_id ? route('projects.show', ['project' => $task->project_id, 'tab' => $taskTab]) : ($task->audit_id ? route('audits.show', ['audit' => $task->audit_id, 'tab' => $taskTab, 'task' => $task->id]) : route('crm.index', ['tab' => 'tasks'])) }}">{{ $task->title }}</a>@if($isStage)<div style="margin-top:6px;font-weight:400">Minął termin etapu prac w projekcie: {{ $task->project?->number }} · {{ $task->project?->name }}.<br>Wykonanie etapu: {{ (int) $task->progress }}%.</div>@endif</td>
                        <td style="padding:10px; color:#B91C1C; font-weight:600;">{{ $task->due_date->format('d.m.Y') }}</td>
                        <td style="padding:10px; color:#555;">{{ $taskCompany?->name ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <p style="margin-top:20px;">Kliknij nazwę etapu, aby otworzyć harmonogram projektu i zaktualizować wykonanie zgodnie ze swoimi uprawnieniami. Linki do pozostałych zadań prowadzą do ich list.</p>

@endsection
