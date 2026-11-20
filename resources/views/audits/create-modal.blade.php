@include('audits.partials.create-dialog-styles')
<dialog id="audit-create-dialog" class="audit-create-dialog" aria-labelledby="audit-create-title">
    <div style="display:flex;justify-content:space-between;align-items:center"><h2 id="audit-create-title">Nowy audyt</h2><button type="button" aria-label="Zamknij" onclick="this.closest('dialog').close()" style="border:0;background:none;font-size:24px;cursor:pointer">×</button></div>
    @if($errors->auditCreate->any())<div role="alert" style="color:#b91c1c">@foreach($errors->auditCreate->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($companies->isEmpty())<p>Brak dostępnych aktywnych firm klientów.</p>@endif
    @if($auditTypes->isEmpty())<p>Brak rodzajów audytu. Dodaj je w System audytów → Typy audytów.</p>@endif
    <form method="POST" action="{{ route('audits.store') }}">@csrf
    <div class="audit-create-fields">
        <div class="audit-create-field"><label for="create-audit-company">Firma / klient *</label><select id="create-audit-company" name="company_id" required><option value="">Wybierz firmę</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>@endforeach</select></div>
        <div class="audit-create-field"><label for="create-audit-type">Rodzaj audytu *</label><select id="create-audit-type" name="audit_type_id" required><option value="">Wybierz rodzaj</option>@foreach($auditTypes as $type)<option value="{{ $type->id }}" @selected(old('audit_type_id') == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
        <div class="audit-create-field"><label for="create-audit-number">Numer audytu *</label><input id="create-audit-number" name="number" value="{{ old('number') }}" maxlength="80" required></div>
        <div class="audit-create-field"><label for="create-audit-name">Nazwa audytu *</label><input id="create-audit-name" name="title" value="{{ old('title') }}" maxlength="255" required></div>
        <div class="audit-create-field"><label for="create-audit-manager">Kierownik audytu *</label><select id="create-audit-manager" name="manager_id" required><option value="">Wybierz</option>@foreach($auditUsers as $person)<option value="{{ $person->id }}" @selected(old('manager_id') == $person->id)>{{ $person->name }}</option>@endforeach</select></div>
        <div class="audit-create-field"><label for="create-audit-status">Status</label><select id="create-audit-status" name="status">@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="audit-create-field"><label for="create-audit-start">Data rozpoczęcia</label><input id="create-audit-start" type="date" name="start_date" value="{{ old('start_date') }}"></div>
        <div class="audit-create-field"><label for="create-audit-end">Data zakończenia</label><input id="create-audit-end" type="date" name="end_date" value="{{ old('end_date') }}"></div>
        <div class="audit-create-field"><label for="create-audit-value">Wartość audytu netto</label><input id="create-audit-value" type="number" step="0.01" min="0" name="contract_value" value="{{ old('contract_value', 0) }}"></div>
        <div class="audit-create-field"><label>Zespół audytu</label><div class="audit-create-members">@foreach($auditUsers->loadMissing('roles')->reject(fn ($person) => $person->hasRole('superadmin')) as $person)<label><input type="checkbox" name="member_ids[]" value="{{ $person->id }}" @checked(in_array($person->id, (array)old('member_ids', [])))> {{ $person->name }}</label>@endforeach</div></div>
        <div class="audit-create-field full"><label for="create-audit-description">Opis</label><textarea id="create-audit-description" name="description" rows="3">{{ old('description') }}</textarea></div>
    </div>
    <div class="audit-create-actions"><button type="button" onclick="this.closest('dialog').close()">Anuluj</button><button class="audit-create-button" @disabled($companies->isEmpty() || $auditTypes->isEmpty() || $auditUsers->isEmpty())>Utwórz audyt</button></div>
    </form>
</dialog>
@if($errors->auditCreate->any())<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('audit-create-dialog').showModal());</script>@endif
