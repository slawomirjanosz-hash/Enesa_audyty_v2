@php
    $financeEditorEntries = $project->effectiveFinancialEntries()->mapWithKeys(fn ($entry) => [$entry->id => [
        'type' => $entry->type,
        'name' => $entry->name,
        'document_number' => $entry->document_number,
        'supplier_company_id' => $entry->supplier_company_id,
        'supplier' => $entry->supplier,
        'entry_date' => $entry->entry_date->format('Y-m-d'),
        'payment_date' => $entry->payment_date?->format('Y-m-d'),
        'settled_on' => $entry->settled_on?->format('Y-m-d'),
        'settled_amount' => $entry->settled_amount,
        'amount' => $entry->amount,
        'status' => $entry->status,
        'finance_group_id' => $entry->finance_group_id,
        'notes' => $entry->notes,
        'update_url' => route('projects.finances.update', [$project, $entry]),
    ]]);
    $financeEditorRecovery = session()->hasOldInput('_finance_editor_mode') ? [
        'mode' => old('_finance_editor_mode'),
        'id' => old('_finance_editor_entry'),
        'values' => collect(session()->getOldInput())->only(['type', 'name', 'document_number', 'supplier_company_id', 'supplier', 'entry_date', 'payment_date', 'settled_on', 'settled_amount', 'amount', 'status', 'finance_group_id', 'notes'])->all(),
    ] : null;
    $financeEditorConfig = ['entries' => $financeEditorEntries, 'store_url' => route('projects.finances.store', $project), 'recovery' => $financeEditorRecovery];
@endphp
<link rel="stylesheet" href="{{ asset('css/project-finance-editor.css') }}">
<dialog id="project-finance-dialog" class="project-finance-dialog" aria-labelledby="project-finance-dialog-title">
    <form id="project-finance-editor-form" method="POST" action="{{ route('projects.finances.store', $project) }}" class="finance-entry-form">
        @csrf
        <input type="hidden" name="_method" value="PATCH" disabled>
        <input type="hidden" name="_finance_editor_mode">
        <input type="hidden" name="_finance_editor_entry">
        <header class="project-finance-dialog-head">
            <div><h2 id="project-finance-dialog-title">Edytuj pozycję finansową</h2><p>{{ $project->number }} · {{ $project->name }}</p></div>
            <button type="button" class="modal-close" data-finance-editor-close aria-label="Zamknij okno finansów">×</button>
        </header>
        <div class="project-finance-dialog-body">
            <p class="finance-invoice-note" id="project-finance-copy-note" hidden>Kopia jest nową pozycją. Numer dokumentu jest pusty, a status ustawiony na „Planowana”. Sprawdź daty, kwotę i pozostałe dane przed zapisem.</p>
            <div class="project-finance-dialog-grid">
                <label class="field">Rodzaj<select name="type" class="finance-entry-type" required><option value="cost">Koszt</option><option value="invoice">Faktura dla klienta</option></select></label>
                <label class="field">Nazwa<input name="name" maxlength="255" required autofocus></label>
                <label class="field">Numer dokumentu<input name="document_number" maxlength="100"></label>
                <label class="field" data-finance-supplier-field>Dostawca z bazy<select name="supplier_company_id"><option value="">Niepowiązany</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label>
                <label class="field" data-finance-supplier-field>Dostawca spoza bazy<input name="supplier" maxlength="255"></label>
                <label class="field">Data dokumentu<input type="date" name="entry_date" required></label>
                <label class="field">Termin płatności<input type="date" name="payment_date"></label>
                <label class="field">Kwota netto<input type="number" step="0.01" min="0" name="amount" required></label>
                <label class="field">Status<select name="status">@foreach($financeStatusLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="field" data-finance-cost-group>Grupa<select name="finance_group_id"><option value="">Bez grupy</option>@foreach($project->financeGroups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select></label>
                <div class="finance-invoice-note project-finance-dialog-full" data-finance-invoice-note hidden>Faktura klienta pozostaje w grupie „Wystawione” i nie ma dostawcy.</div>
                <label class="field">Faktyczna data zapłaty<input type="date" name="settled_on" max="{{ now('Europe/Warsaw')->toDateString() }}"></label>
                <label class="field">Faktycznie zapłacono / otrzymano (PLN)<input type="number" step="0.01" min="0" max="999999999.99" name="settled_amount"><small>Kwota z przelewu, z VAT jeśli dotyczy. Stan konta działu uwzględnia ją tylko przy statusie „Opłacona”.</small></label>
                <label class="field project-finance-dialog-full">Uwagi<textarea name="notes" rows="3"></textarea></label>
            </div>
        </div>
        <footer class="project-finance-dialog-foot"><button type="button" class="btn btn-soft" data-finance-editor-close>Anuluj</button><button type="submit" class="btn" id="project-finance-editor-save">Zapisz zmiany</button></footer>
    </form>
</dialog>
<script type="application/json" id="project-finance-editor-config">@json($financeEditorConfig)</script>
<script type="module" src="{{ asset('js/project-finance-editor.js') }}?v={{ filemtime(public_path('js/project-finance-editor.js')) }}"></script>
