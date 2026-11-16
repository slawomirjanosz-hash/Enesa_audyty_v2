<div class="field-group">
    <label class="field-label" for="assigned_user_id">Osoba odpowiedzialna</label>
    <select class="field-input" id="assigned_user_id" name="assigned_user_id">
        <option value="">Bez przydziału</option>
        @foreach($assignees as $assignee)
            <option value="{{ $assignee->id }}" @selected(old('assigned_user_id', $offerRequest->assigned_user_id ?? null) == $assignee->id)>{{ $assignee->name }}</option>
        @endforeach
    </select>
</div>
