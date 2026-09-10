<!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>4.1 · Ankieta kontekstu organizacji</title>
<style>
:root{--green:{{ $appBrand?->primaryColor() ?: '#1A4D3A' }}}*{box-sizing:border-box}body{margin:0;background:#f4f1ea;color:#26352d;font:15px/1.6 Arial,sans-serif}header{background:var(--green);color:white;padding:18px 3vw;display:flex;align-items:center;justify-content:space-between;gap:20px}header a{color:inherit}h1{font-size:24px;margin:0}h2{font-size:23px}h3{font-size:18px}main{padding:24px 3vw 100px;width:100%}button,input,select,textarea{font:inherit}button,.button{cursor:pointer;border:1px solid #b9cbc0;border-radius:7px;padding:10px 16px;background:white;color:var(--green);text-decoration:none}button.primary{background:var(--green);color:white}.steps{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px}.steps button[aria-current=step]{background:var(--green);color:white}fieldset,.panel{border:1px solid #dbe3dc;border-radius:10px;background:white;padding:22px;margin:0 0 20px;width:100%}legend{font-size:18px;font-weight:bold;padding:0 10px}label.question{display:block;border-bottom:1px solid #e6ebe7;padding:14px 0;width:100%;counter-increment:question}label.question>span{display:block;font-weight:bold;margin-bottom:8px}label.question>span:before{content:counter(question) ". ";color:var(--green)}.facts{counter-reset:question}input:not([type=checkbox]),select,textarea{width:100%;padding:11px;border:1px solid #bacbc0;border-radius:6px;background:white;color:inherit}textarea{min-height:100px;resize:vertical}.field{display:block;margin:12px 0}.field>span{display:block;font-weight:bold;margin-bottom:7px}[hidden]{display:none!important}.factor{margin:12px 0;border:1px solid #d9e2db;border-radius:9px;padding:16px;background:#fff}.factor label{display:flex;gap:12px;align-items:flex-start}.factor input[type=checkbox]{width:20px;height:20px;flex-shrink:0;margin-top:4px}.factor small{display:block;color:#63746a;margin:7px 0}.factor textarea{margin-top:10px}.factor:has(input:checked){border-color:var(--green);background:#f4f8f5}.hint{padding:12px 16px;background:#eaf2ed;border-radius:8px}.conclusion{border-top:1px solid #dce5df;margin-top:20px;padding-top:15px}.suggestions{display:flex;gap:6px;flex-wrap:wrap}.suggestions button{font-size:13px;text-align:left}.footer{position:fixed;bottom:0;left:0;right:0;display:flex;justify-content:space-between;gap:12px;padding:12px 3vw;background:white;border-top:1px solid #d5dfd8;z-index:10}.footer>div{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.document-actions{display:flex;gap:10px;flex-wrap:wrap}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d8e1db;padding:9px;text-align:left;vertical-align:top;overflow-wrap:anywhere}th{background:#edf3ef}.table-wrap{overflow-x:auto}td{white-space:pre-wrap}.error{background:#fff0eb;color:#9d3022;padding:15px}.status{margin:16px 0;color:#586d60}@media(max-width:650px){header{display:block}.footer{position:sticky}.steps button{flex:1 1 45%}main{padding-bottom:20px}fieldset,.panel{padding:15px}}
</style></head><body>
<header><div><h1>4.1 · Ankieta kontekstu organizacji</h1><span>{{ $appBrand?->name }} @isset($audit) · {{ $audit->company->name }} @endisset</span></div><a href="{{ $backUrl }}" data-leave>← Wróć do punktu 4.1</a></header>
<main>
@if(session('success'))<p class="hint">{{ session('success') }}</p>@endif
@if($errors->any())<div class="error"><strong>Popraw dane formularza:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($isTemplatePreview)<p class="hint">Podgląd formularza wzorcowego. Możesz przetestować wszystkie etapy; wpisy nie są zapisywane jako dokumentacja klienta.</p>@endif
<nav class="steps" aria-label="Etapy ankiety">@foreach(['Dane o zakładzie','Wybór czynników','Uzupełnienie konsultanta','Dokument'] as $i=>$label)<button type="button" data-step="{{ $i }}">{{ $i+1 }}. {{ $label }}</button>@endforeach</nav>
<form id="context-form" method="POST" @unless($isTemplatePreview) action="{{ route($baseRoute.'store', $audit) }}" @endunless>@csrf
<section data-panel="0" class="facts"><h2>Dane o zakładzie</h2><p>Odpowiedzi dopasowują czynniki w kolejnym etapie. Puste odpowiedzi nie są traktowane jako „nie”.</p>
@foreach(collect(config('iso50001-context.questions'))->groupBy('group', true) as $group=>$questions)<fieldset><legend>{{ $group }}</legend>
@foreach($questions as $key=>$question)<label class="question"><span>{{ $question['label'] }}</span>
@if(isset($question['options']))<select name="answers[facts][{{ $key }}]" data-fact="{{ $key }}"><option value="">— wybierz —</option>@foreach($question['options'] as $value=>$label)<option value="{{ $value }}" @selected((string) data_get($answers,'facts.'.$key) === (string) $value)>{{ $label }}</option>@endforeach</select>
@else<input type="{{ $question['type'] }}" name="answers[facts][{{ $key }}]" data-fact="{{ $key }}" value="{{ data_get($answers,'facts.'.$key, $key === 'organization' ? ($audit->company->name ?? '') : '') }}" @if($question['type']==='number') min="0" step="any" @endif>
@endif</label>@endforeach</fieldset>@endforeach
</section>
<section data-panel="1" hidden><h2>Wybór czynników</h2><p>Wybierz prawdziwe czynniki spośród dopasowanych propozycji. Treść zaznaczonych pozycji możesz doprecyzować.</p><p class="hint" data-factor-count></p>
@foreach(config('iso50001-context.dimensions') as $dimension=>$label)<fieldset data-dimension="{{ $dimension }}"><legend>{{ $label }}</legend>
@foreach(config('iso50001-context.factors') as $factor)@if($factor['dimension']===$dimension)<article class="factor" data-factor="{{ $factor['id'] }}">
<label><input type="checkbox" name="answers[selected][]" value="{{ $factor['id'] }}" @checked(in_array($factor['id'],$answers['selected'] ?? [], true)) @disabled($factor['automatic'])><span>{{ $factor['text'] }}</span></label>
<small>{{ $factor['id'] }} · Wpływ: {{ $factor['impact'] === 'o' ? '○' : $factor['impact'] }} · {{ $factor['effect'] }}</small>
@if($factor['automatic'])<small>Ustalone automatycznie z danych</small>@else<textarea aria-label="Treść czynnika {{ $factor['id'] }}" name="answers[edits][{{ $factor['id'] }}]" hidden>{{ data_get($answers,'edits.'.$factor['id']) ?? $factor['text'] }}</textarea>@endif
</article>@endif@endforeach</fieldset>@endforeach
</section>
<section data-panel="2" hidden><h2>Uzupełnienie konsultanta</h2><p>Przełóż wybrane czynniki na analizę SWOT i decyzje dotyczące systemu.</p>
<fieldset><legend>Analiza SWOT</legend>@foreach(config('iso50001-context.swot') as $key=>$label)<label class="field"><span>{{ $label }}</span><textarea name="answers[swot][{{ $key }}]" data-swot="{{ $key }}">{{ data_get($answers,'swot.'.$key) }}</textarea><small data-swot-hint="{{ $key }}"></small></label>@endforeach</fieldset>
<fieldset><legend>Wnioski — kontekst przełożony na decyzje projektowe</legend><p>Cztery miejsca na wnioski, decyzje i dokumenty powiązane — zgodnie z generatorem.</p>
@for($i=0;$i<4;$i++)<div class="conclusion" data-conclusion="{{ $i }}"><h3>Wniosek {{ $i+1 }}</h3>@foreach(['finding'=>'Wniosek z analizy kontekstu','decision'=>'Decyzja projektowa w systemie','document'=>'Dokument powiązany'] as $key=>$label)<label class="field"><span>{{ $label }}</span><textarea name="answers[conclusions][{{ $i }}][{{ $key }}]" data-conclusion-field="{{ $key }}">{{ data_get($answers,'conclusions.'.$i.'.'.$key) }}</textarea></label>@endforeach<div class="suggestions" data-suggestions="{{ $i }}"></div></div>@endfor</fieldset>
</section>
<section data-panel="3" hidden><h2>Dokument</h2><p class="hint" data-completeness></p><div class="panel" id="context-document-preview"></div>
@unless($isTemplatePreview)
<div class="panel">
    <h3>Zapisz w dokumentacji klienta</h3>
    <p>Dokument zostanie zapisany w punkcie 4.1 tego audytu. Każdy kolejny zapis utworzy nową wersję.</p>
    <div class="document-actions">
        <button type="submit" class="primary" formaction="{{ route($baseRoute.'pdf',$audit) }}" data-export data-save-document="PDF">Zapisz PDF</button>
        <button type="submit" class="primary" formaction="{{ route($baseRoute.'docx',$audit) }}" data-export data-save-document="Word">Zapisz Word</button>
        <button type="submit" formaction="{{ route($baseRoute.'pdf-preview',$audit) }}" formtarget="_blank" data-export>Podgląd PDF</button>
    </div>
    <p class="hint" data-document-saved role="status" hidden><span></span> <a href="{{ $backUrl }}#iso-documents-4-1" data-leave>Przejdź do dokumentacji klienta →</a></p>
</div>
@endunless
</section>
<div class="footer"><div><button type="button" data-back>← Wstecz</button><button type="button" class="primary" data-next>Dalej →</button></div><div><span data-status role="status"></span>@unless($isTemplatePreview)<button type="submit" data-save>Zapisz roboczo</button>@endunless</div></div>
</form>
</main>
<script>window.isoContextDefinition = {{ Illuminate\Support\Js::from(config('iso50001-context')) }};</script>
<script src="{{ asset('js/iso-context.js') }}?v={{ filemtime(public_path('js/iso-context.js')) }}" defer></script>
</body></html>
