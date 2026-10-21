@if($canManageOwnTasks)
@foreach(['action_description', 'action_due_date', 'action_assigned_to'] as $field)
    @error($field)<p role="alert" style="color:#b91c1c;">{{ $message }}</p>@enderror
@endforeach
<details class="lead-action" style="margin-bottom:20px;" @if(old('action_description')) open @endif>
    <summary style="cursor:pointer;font-weight:700;">Dodaj działanie</summary>
    <p style="font-size:12px;color:#666;">Działanie zostanie zapisane jako zadanie CRM powiązane z leadem.</p>
    <div style="display:grid;gap:10px;">
        <label>Co — opis
            <textarea name="action_description" rows="3" maxlength="10000" style="display:block;width:100%;box-sizing:border-box;">{{ old('action_description') }}</textarea>
        </label>
        <label>Kiedy — data
            <input type="date" name="action_due_date" value="{{ old('action_due_date') }}" style="display:block;">
        </label>
        <label>Kto — użytkownik
            <select name="action_assigned_to" style="display:block;width:100%;">
                <option value="">Wybierz użytkownika</option>
                @foreach($users as $person)
                    <option value="{{ $person->id }}" @selected((string) old('action_assigned_to') === (string) $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </label>
    </div>
</details>
@endif
