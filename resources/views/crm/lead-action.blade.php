@if($canManageCrm || auth()->user()->can('crm.leads.manage'))
@once
<style>
.lead-action summary,.lead-action label{font:700 11px 'Manrope',sans-serif;color:#555}
.lead-action summary{cursor:pointer;font-size:13px}
.lead-action label textarea,.lead-action label input,.lead-action label select{display:block;box-sizing:border-box;width:100%;margin-top:4px;padding:8px 10px;border:1px solid #D0CCC0;border-radius:7px;background:#FAFAF6;font:13px 'Lato',sans-serif}
.lead-action label :is(textarea,input,select):focus-visible{outline:2px solid var(--green);outline-offset:1px}
</style>
@endonce
@foreach(['action_description', 'action_due_date', 'action_assigned_to'] as $field)
    @error($field)<p role="alert" style="color:#b91c1c;">{{ $message }}</p>@enderror
@endforeach
<details class="lead-action" style="margin-bottom:20px;" @if(old('action_description')) open @endif>
    <summary>Dodaj działanie</summary>
    <p style="font-size:12px;color:#666;">Wpis do historii działań przy tej szansie CRM.</p>
    <div style="display:grid;gap:10px;">
        <label>Co — opis
            <textarea name="action_description" rows="3" maxlength="10000">{{ old('action_description') }}</textarea>
        </label>
        <label>Kiedy — data działania
            <input type="date" name="action_due_date" value="{{ old('action_due_date') }}">
        </label>
        <label>Kto wykonał — użytkownik
            <select name="action_assigned_to">
                <option value="">Wybierz użytkownika</option>
                @foreach($users as $person)
                    <option value="{{ $person->id }}" @selected((string) old('action_assigned_to') === (string) $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </label>
    </div>
</details>
@endif
