@php
    $definition = config('iso50001-context');
    $response = isset($audit) ? ($isoImplementationResponses->get('4-1|context_generator') ?? null) : null;
    $answers = old('answers', $response?->answers ?? []);
    $isTemplatePreview = !isset($audit);
    $baseRoute = $isTemplatePreview ? null : (($clientView ?? false) ? 'client.audits.iso50001.context.' : 'audits.iso50001.context.');
@endphp
<section class="iso-context-generator" data-context-generator>
    <div class="iso-context-heading"><div><h3><i class="ti ti-clipboard-check"></i> Ankieta kontekstu organizacji</h3><p>Uzupełnij dane zakładu, wybierz istotne czynniki, przygotuj analizę SWOT i wnioski. Formularz tworzy dokument D-EnMS-KON-01.</p></div><span>ISO 50001 · 4.1</span></div>
    @if($isTemplatePreview)<div class="iso-template-banner">To jest podgląd formularza wzorcowego. Dane klienta są zapisywane dopiero wewnątrz konkretnego audytu.</div>@endif
    <form method="POST" @if(!$isTemplatePreview) action="{{ route($baseRoute.'store', $audit) }}" @endif data-context-form>@csrf
        @foreach(collect($definition['questions'])->groupBy('group') as $group => $questions)
            <fieldset><legend>{{ $group }}</legend><div class="iso-context-grid">
            @foreach($questions as $key => $question)<label class="iso-context-field"><span>{{ $question['label'] }}</span>
                @if($question['type'] === 'select' || $question['type'] === 'boolean')
                    @php($options = $question['type'] === 'boolean' ? ['nie' => 'Nie', 'tak' => 'Tak'] : $question['options'])
                    <select name="answers[facts][{{ $key }}]" @disabled($isTemplatePreview)><option value="">— wybierz —</option>@foreach($options as $value => $label)<option value="{{ $value }}" @selected(data_get($answers, 'facts.'.$key) === $value)>{{ $label }}</option>@endforeach</select>
                @else<input type="{{ $question['type'] }}" name="answers[facts][{{ $key }}]" value="{{ data_get($answers, 'facts.'.$key) }}" @disabled($isTemplatePreview)></input>@endif
            </label>@endforeach
            </div></fieldset>
        @endforeach
        <fieldset><legend>Analiza SWOT</legend><div class="iso-context-grid">@foreach($definition['swot'] as $key => $label)<label class="iso-context-field"><span>{{ $label }}</span><textarea name="answers[swot][{{ $key }}]" @disabled($isTemplatePreview)>{{ data_get($answers, 'swot.'.$key) }}</textarea></label>@endforeach</div></fieldset>
        <fieldset><legend>Wnioski — przełożenie kontekstu na decyzje</legend><p class="iso-context-help">Wpisz co najmniej cztery najważniejsze wnioski. Dla każdego wskaż decyzję projektową i dokument, w którym zostanie wdrożona.</p>
            <div class="iso-conclusions">@for($i=0;$i<4;$i++)<div class="iso-conclusion"><b>{{ $i+1 }}</b><textarea name="answers[conclusions][{{ $i }}][finding]" placeholder="Wniosek z analizy kontekstu" @disabled($isTemplatePreview)>{{ data_get($answers, 'conclusions.'.$i.'.finding') }}</textarea><textarea name="answers[conclusions][{{ $i }}][decision]" placeholder="Decyzja projektowa w systemie" @disabled($isTemplatePreview)>{{ data_get($answers, 'conclusions.'.$i.'.decision') }}</textarea><input name="answers[conclusions][{{ $i }}][document]" value="{{ data_get($answers, 'conclusions.'.$i.'.document') }}" placeholder="Dokument powiązany" @disabled($isTemplatePreview)></div>@endfor</div>
        </fieldset>
        @unless($isTemplatePreview)<div class="iso-context-actions">
            <button type="submit" class="secondary"><i class="ti ti-device-floppy"></i> Zapisz roboczo</button>
            <button type="submit" formaction="{{ route($baseRoute.'docx', $audit) }}"><i class="ti ti-file-type-docx"></i> Utwórz Word</button>
            <button type="submit" formaction="{{ route($baseRoute.'pdf-preview', $audit) }}" formtarget="_blank"><i class="ti ti-eye"></i> Podgląd PDF</button>
            <button type="submit" formaction="{{ route($baseRoute.'pdf', $audit) }}"><i class="ti ti-file-type-pdf"></i> Generuj PDF</button>
        </div>@endunless
    </form>
</section>
@once
<style>
.iso-context-generator{margin-top:14px;border:1px solid #d8e3dc;border-radius:12px;background:#fff;overflow:hidden}.iso-context-heading{display:flex;justify-content:space-between;gap:20px;padding:18px 20px;background:#f1f7f3}.iso-context-heading h3{margin:0;color:var(--green);font:800 16px Manrope}.iso-context-heading p{margin:6px 0 0;font-size:13px}.iso-context-heading>span{white-space:nowrap;font:800 12px Manrope;color:var(--green)}.iso-context-generator form{padding:18px}.iso-context-generator fieldset{border:0;border-top:1px solid #e4e9e5;margin:0 0 22px;padding:18px 0 0}.iso-context-generator legend{padding-right:10px;color:#263b31;font:800 14px Manrope}.iso-context-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.iso-context-field{display:flex;flex-direction:column;gap:6px}.iso-context-field span{font:700 12px Manrope;color:#405148}.iso-context-field input,.iso-context-field select,.iso-context-field textarea,.iso-conclusion input,.iso-conclusion textarea{width:100%;border:1px solid #ccd7d0;border-radius:7px;padding:9px 10px;background:#fff;font:13px Lato}.iso-context-field textarea{min-height:90px}.iso-context-help{font-size:12px;color:#647169}.iso-conclusions{display:grid;gap:9px}.iso-conclusion{display:grid;grid-template-columns:28px 1.4fr 1.4fr 1fr;gap:8px;align-items:start}.iso-conclusion b{display:grid;place-items:center;height:36px;border-radius:50%;background:#e8f2ec;color:var(--green)}.iso-conclusion textarea{min-height:66px}.iso-context-actions{display:flex;gap:8px;flex-wrap:wrap;position:sticky;bottom:10px;padding:12px;border-radius:9px;background:#eef4f0;box-shadow:0 5px 18px #2346}.iso-context-actions button{border:0;border-radius:7px;background:var(--green);color:#fff;padding:9px 12px;font:800 12px Manrope;cursor:pointer}.iso-context-actions button.secondary{background:#fff;color:var(--green);border:1px solid #b8cabf}@media(max-width:800px){.iso-context-grid{grid-template-columns:1fr}.iso-conclusion{grid-template-columns:28px 1fr}.iso-conclusion>*:not(b){grid-column:2}}
</style>
@endonce
