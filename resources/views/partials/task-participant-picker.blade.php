@php
    $taskParticipantOptions = $taskParticipantOptions ?? \App\Http\Controllers\BoardCollaborationController::candidates(new \App\Models\Task);
@endphp
<fieldset data-task-participant-picker style="border:1px solid #D0CCC0;border-radius:7px;padding:10px;margin:12px 0;max-height:180px;overflow:auto">
    <legend style="font:inherit;font-size:12px;padding:0 4px">Dodatkowi uczestnicy</legend>
    @foreach($taskParticipantOptions as $person)
        <label style="display:flex;align-items:center;gap:7px;margin:6px 0;font:inherit;font-size:13px">
            <input type="checkbox" name="participants[]" value="{{ $person->id }}" @checked(in_array((string)$person->id, array_map('strval', (array) old('participants', [])), true)) style="width:auto;margin:0"> {{ $person->name }}
        </label>
    @endforeach
</fieldset>
