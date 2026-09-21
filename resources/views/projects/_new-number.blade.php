@php
    $numberPrefix = \App\Models\Project::numberPrefix();
    $numberDate = old('number_date', now()->format('Y-m-d'));
    $numberSequence = old('number_sequence', \App\Models\Project::nextNumberSequence());
@endphp
<div class="field" style="grid-column:1/-1" data-project-number data-prefix="{{ $numberPrefix }}">
    <label>Numer nowego projektu</label>
    <output style="display:block;font-weight:700;margin:8px 0;overflow-wrap:anywhere" aria-live="polite" data-number-preview>{{ $numberPrefix.str_replace('-', '', $numberDate).'_'.str_pad((string) $numberSequence, 3, '0', STR_PAD_LEFT) }}</output>
    <div style="display:flex;gap:14px;flex-wrap:wrap">
        <label style="flex:1;min-width:180px">Data w numerze *<input type="date" name="number_date" value="{{ $numberDate }}" required data-number-date></label>
        <label style="flex:1;min-width:180px">Numer kolejny *<input type="number" name="number_sequence" value="{{ $numberSequence }}" min="1" max="9999999" step="1" required data-number-sequence></label>
    </div>
    <small>Datę i numer kolejny możesz zmienić. Skrót firmy pochodzi z ustawień platformy. Data w numerze nie zmienia dat harmonogramu. Numer musi być unikalny.</small>
</div>
@once
@push('scripts')
<script>
document.querySelectorAll('[data-project-number]').forEach(function (group) {
    group.addEventListener('input', function () {
        const date = group.querySelector('[data-number-date]').value.replaceAll('-', '');
        const sequence = String(Number(group.querySelector('[data-number-sequence]').value));
        group.querySelector('[data-number-preview]').textContent = group.dataset.prefix + date + '_' + sequence.padStart(3, '0');
    });
});
</script>
@endpush
@endonce
