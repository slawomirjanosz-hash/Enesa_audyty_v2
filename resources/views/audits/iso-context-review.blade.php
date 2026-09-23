<!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ISO 50001 · 4.1–4.2</title>
<style>
:root{--brand:{{ $appBrand?->primaryColor() ?: '#1a4d3a' }}}*{box-sizing:border-box}body{margin:0;background:#f5f3ee;color:#203930;font:15px/1.6 Arial,sans-serif}header{background:var(--brand);color:white;padding:20px 3vw;display:flex;justify-content:space-between;align-items:center;gap:16px}header a{color:white}h1{font-size:24px;margin:0}h2{font-size:21px}h3{font-size:17px}main{padding:24px 3vw 80px}section,.card{background:white;border:1px solid #dce3df;border-radius:12px;padding:22px;margin-bottom:20px}nav{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0}nav a,button,.button{display:inline-block;font:inherit;border:1px solid #cbd7cf;border-radius:7px;padding:9px 15px;background:white;color:var(--brand);text-decoration:none;cursor:pointer}button.primary{background:var(--brand);color:white}button:disabled{opacity:.5;cursor:default}label.field{display:block;padding:15px 0;border-bottom:1px solid #eee}label.field>span{display:block;font-weight:bold;margin-bottom:7px}input:not([type=checkbox]),select,textarea{font:inherit;border:1px solid #bfccc3;border-radius:6px;padding:10px;width:100%;color:inherit;background:white}textarea{min-height:85px;resize:vertical}input[type=checkbox]{width:19px;height:19px;vertical-align:middle}small{color:#67786e}.notice{background:#fff3d9;padding:14px;border-radius:8px}.success{background:#e7f4e9;padding:14px}.errors{background:#ffe9e6;padding:16px}.bar{display:flex;gap:12px;flex-wrap:wrap;align-items:center}.bar form{display:flex;align-items:center;gap:8px}.badge{padding:4px 10px;border-radius:30px;background:#edf3ef;font-size:13px}.factor{border-top:1px solid #ddd;padding:18px 0}.factor>label{display:flex;gap:12px;font-weight:bold}.factor small{display:block}.pending{border-left:4px solid #d39d36;padding-left:16px}.scroll{overflow:auto}table{border-collapse:collapse;width:100%}th,td{padding:10px;text-align:left;border-bottom:1px solid #ddd;vertical-align:top}th{background:#f2f6f3}.sticky{position:sticky;bottom:0;background:#fff;padding:14px;border:1px solid #ddd;border-radius:10px;z-index:2}.readonly{background:#f6f8f6;padding:12px;white-space:pre-wrap}fieldset{border:0;padding:0;margin:0}fieldset:disabled input,fieldset:disabled textarea,fieldset:disabled select{background:#f6f8f6}summary{cursor:pointer;font-weight:bold}.error-note{color:#a33020}section{scroll-margin-top:16px}@media(max-width:650px){header{display:block}main{padding:15px}section{padding:15px}.sticky{position:static}}
.review-section-nav{position:sticky;top:0;z-index:10;padding:12px 0;background:#f5f3ee;border-bottom:1px solid #dce3df;box-shadow:0 5px 8px -8px #20393080}
.review-section-nav a:focus-visible{outline:3px solid var(--brand);outline-offset:2px}
section{scroll-margin-top:var(--review-nav-offset,90px)}
@media(max-width:650px){.review-section-nav{gap:6px;padding:8px 0}.review-section-nav a{padding:7px 10px;font-size:13px}}
</style></head><body>
@php
    $statuses=['draft'=>'W trakcie uzupełniania','submitted'=>'Przekazana konsultantowi','reviewing'=>'W weryfikacji','returned'=>'Do uzupełnienia','approved'=>'Dane zatwierdzone'];
    $editable=in_array($review->status,$client?['draft','returned']:['draft','returned','reviewing']);
    $actions=['save'=>'Zapisz i przelicz propozycje','submit'=>'Przekaż do weryfikacji','withdraw'=>'Wycofaj do edycji','review'=>'Rozpocznij weryfikację','return'=>'Zwróć do uzupełnienia','approve'=>'Zatwierdź dane','reopen'=>'Otwórz ponownie','request_reopen'=>'Poproś o ponowne otwarcie','copy'=>'Skopiuj poprzedni rok'];
@endphp
<header><div><h1>4.1–4.2 · Kontekst i strony zainteresowane</h1><div>{{ $audit->company->name }} · {{ $client?'Strefa klienta':'Panel konsultanta' }}</div></div><a href="{{ route($client?'client.audits.show':'audits.show',['audit'=>$audit,'tab'=>'iso50001','section'=>'4-1']) }}">← Wróć do audytu</a></header>
<main>
@include('partials.field-validation')
@include('partials.questionnaire-navigation')
@include('partials.questionnaire-progress', ['progress'=>app(\App\Services\QuestionnaireCompletion::class)->fields(array_column($questions,'kod'),$answers['facts']??[]), 'progressForm'=>'#review-form', 'progressMode'=>'fields', 'progressFields'=>array_map(fn($q)=>'answers[facts]['.$q['kod'].']',$questions), 'progressLabel'=>'Odpowiedzi na pytania o zakład (4.1–4.2)'])
@if(session('success'))<p class="success" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="errors" role="alert"><strong>Nie zapisano zmian:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="bar"><span class="badge">{{ $statuses[$review->status] }}</span><span>Rewizja {{ $review->revision }} · Biblioteka 1.3 · Tryb: {{ $mode }}</span><form method="get"><label for="review-year">Rok</label><input id="review-year" style="width:105px" type="number" name="year" min="2020" max="2100" value="{{ $review->year }}"><button>Otwórz rok</button></form></div>
<nav class="review-section-nav" aria-label="Części ankiety"><a href="#facts">1. Dane zakładu (44)</a><a href="#factors">2. Czynniki 4.1</a><a href="#parties">3. Strony 4.2</a><a href="#consultant">4. Konsultant</a><a href="#documents">5. Dokumenty i historia</a></nav>
<form id="review-form" method="post" action="{{ route($routePrefix.'update',$audit) }}">@csrf
<input type="hidden" name="year" value="{{ $review->year }}"><input type="hidden" name="revision" value="{{ old('revision', $review->revision) }}">
<fieldset @disabled(!$editable)>
<section id="facts"><h2>1. Dane o zakładzie</h2>
<h3>Dane do dokumentu</h3>
<label class="field"><span>Zakres systemu zarządzania energią</span><small>Opisz zakłady, lokalizacje i działalność objęte systemem. To pole uzupełnia nagłówek dokumentu i nie należy do numerowanych pytań biblioteki.</small><textarea name="answers[scope]">{{ $answers['scope']??'' }}</textarea></label>
<h3>Pytania z biblioteki audytora</h3>
@foreach($questions as $q)<label class="field"><span>{{ $loop->iteration }}. {{ $q['pytanie'] }}</span><small>{{ $q['kod'] }}</small>
@if($q['kod']==='ZUZYCIE_TJ')<input readonly data-completion-name="answers[facts][ZUZYCIE_TJ]" value="{{ data_get($answers,'facts.ZUZYCIE_TJ') }}"><small>Suma z tabeli nośników poniżej; przeliczana przy zapisie. Okres: {{ $review->year-1 }}.</small>
@elseif($q['numeric'])<input type="text" inputmode="decimal" name="answers[facts][{{ $q['kod'] }}]" value="{{ data_get($answers,'facts.'.$q['kod']) }}" placeholder="Liczba (przecinek lub kropka) lub: nie wiem">
@else<select name="answers[facts][{{ $q['kod'] }}]"><option value="">— wybierz odpowiedź —</option>@foreach($q['options'] as $value=>$label)<option value="{{ $value }}" @selected((string)data_get($answers,'facts.'.$q['kod'])===(string)$value)>{{ $label }}</option>@endforeach</select>@endif</label>@endforeach
<h3>Nośniki energii — {{ $review->year-1 }}</h3><p>Wpisz zużycie przeliczone na TJ, bez podwójnego liczenia energii produkowanej wewnątrz zakładu. Definicja progów prawnych oczekuje na potwierdzenie i nie jest podstawą automatycznej oceny obowiązków.</p>
<input type="hidden" name="answers[energy_unknown]" value="0"><label><input type="checkbox" name="answers[energy_unknown]" value="1" @checked($answers['energy_unknown']??false)> Nie znam jeszcze pełnego zużycia</label>
<div class="scroll"><table><thead><tr><th>Nośnik</th><th>Zużycie [TJ]</th><th>Źródło / przeliczenie</th></tr></thead><tbody>@for($i=0;$i<max(6,count($answers['energy']??[]));$i++)<tr><td><input aria-label="Nośnik {{ $i+1 }}" name="answers[energy][{{ $i }}][name]" value="{{ data_get($answers,'energy.'.$i.'.name') }}"></td><td><input aria-label="Zużycie TJ {{ $i+1 }}" type="number" min="0" step="any" name="answers[energy][{{ $i }}][tj]" value="{{ data_get($answers,'energy.'.$i.'.tj') }}"></td><td><input aria-label="Źródło {{ $i+1 }}" name="answers[energy][{{ $i }}][source]" value="{{ data_get($answers,'energy.'.$i.'.source') }}"></td></tr>@endfor</tbody></table></div>
@foreach(['audit_year'=>'Rok wykonania audytu energetycznego (opcjonalnie)','csrd_year'=>'Rok rozpoczęcia raportowania CSRD (opcjonalnie)','contract_end'=>'Data końca umowy na energię (opcjonalnie)'] as $key=>$label)<label class="field"><span>{{ $label }}</span><input type="{{ $key==='contract_end'?'date':'number' }}" name="answers[{{ $key }}]" value="{{ $answers[$key]??'' }}"></label>@endforeach
<label class="field"><span>Dokumenty istniejącego systemu — nazwy, numery i wersje</span><textarea name="answers[base_documents]">{{ $answers['base_documents']??'' }}</textarea></label>
<label class="field"><span>Uzasadnienie oceny istotności zmiany klimatu</span><textarea name="answers[climate_reason]">{{ $answers['climate_reason']??'' }}</textarea></label>
</section>
<section id="factors"><h2>2. Czynniki kontekstowe · 4.1</h2><p>AUTO wynika z danych. PROPOZYCJA jest wstępnie zaznaczona — potwierdź jej prawdziwość. OCENA wymaga świadomego wyboru. Zmiana danych nie usuwa ręcznych wyborów; nieaktualne pozycje wymagają rozstrzygnięcia.</p>
@forelse($factors as $f)<article @class(['factor','pending'=>$f['pending']||$f['stale']])><small>{{ $f['kod'] }} · {{ $f['wymiar'] }} · {{ $f['rodzaj'] }}</small>
@if($f['pending'])<p class="notice">Pozycja wymaga potwierdzenia treści. Do tego czasu nie jest uwzględniana w dokumencie jako ustalenie.</p>@unless($client)<details><summary>Treść źródłowa do konsultacji</summary><p>{{ $f['sformulowanie'] }}</p><p>{{ $f['warunek'] }}</p></details>@endunless
@else
@if($f['stale'])<p class="error-note">Zmieniły się dane — ten wybór nie spełnia już warunku. Odznacz go lub skoryguj odpowiedzi.</p>@endif
@if($f['rodzaj']==='AUTO')<p><strong>{{ $f['text'] }}</strong></p><small>Ustalone z aktualnych danych; poprzedni wynik pozostaje w historii.</small>
@else<input type="hidden" name="answers[factors][{{ $f['kod'] }}][selected]" value="0"><label><input type="checkbox" name="answers[factors][{{ $f['kod'] }}][selected]" value="1" @checked($f['selected'])> Uwzględnij czynnik</label><textarea aria-label="Treść {{ $f['kod'] }}" name="answers[factors][{{ $f['kod'] }}][text]">{{ $f['text'] }}</textarea>@endif
<small>Wpływ: {{ $f['wplyw'] }} · Skutek: {{ $f['skutek_dla_systemu'] }}</small>
@endif</article>@empty<p>Uzupełnij i zapisz dane zakładu, aby zobaczyć dopasowane propozycje.</p>@endforelse
</section>
<section id="parties"><h2>3. Strony zainteresowane · 4.2</h2><p>Propozycje wynikają z odpowiedzi oraz wybranych czynników 4.1. Wymagania z różnych źródeł pozostają widoczne osobno. Odrzucenie strony wymaga uzasadnienia.</p>
@foreach($stakeholders as $p)<article class="factor"><small>{{ $p['kod'] }} · {{ $p['typ'] }}</small><input type="hidden" name="answers[stakeholders][{{ $p['kod'] }}][selected]" value="0"><label><input type="checkbox" name="answers[stakeholders][{{ $p['kod'] }}][selected]" value="1" @checked($p['selected'])>{{ $p['strona'] }}</label>
@unless($p['matches'])<p class="error-note">Wybór z wcześniejszych danych — wymaga ponownej weryfikacji.</p>@endunless
<label class="field"><span>Wymagania i oczekiwania</span><textarea name="answers[stakeholders][{{ $p['kod'] }}][text]">{{ $p['text'] }}</textarea></label>
@foreach($p['requirements'] as $requirement)<p class="readonly">{{ $requirement['source'] }} → {{ $requirement['text'] }}<br><small>Oznaczenie źródłowe: {{ $requirement['compliance'] }} — wymaga rozstrzygnięcia konsultanta.</small></p>@endforeach
<label class="field"><span>Źródło informacji / dokument</span><input name="answers[stakeholders][{{ $p['kod'] }}][source]" value="{{ $p['source'] }}"></label>
<label class="field"><span>Uzasadnienie wyboru / odrzucenia / decyzji</span><textarea name="answers[stakeholders][{{ $p['kod'] }}][reason]">{{ $p['reason'] }}</textarea></label>
@unless($client)<label class="field"><span>Ocena konsultanta: czy przyjęto wymagania jako wymagania zgodności?</span><select name="answers[stakeholders][{{ $p['kod'] }}][compliance]">@foreach(['pending'=>'Do rozstrzygnięcia','yes'=>'Tak','no'=>'Nie'] as $value=>$label)<option value="{{ $value }}" @selected($p['compliance']===$value)>{{ $label }}</option>@endforeach</select></label>@else<small>Ocena konsultanta: {{ ['pending'=>'Do rozstrzygnięcia','yes'=>'Tak','no'=>'Nie'][$p['compliance']]??'Do rozstrzygnięcia' }}</small>@endunless
</article>@endforeach</section>
<section id="consultant"><h2>4. Uzupełnienie konsultanta</h2><p>{{ $client?'Tę część uzupełnia konsultant. Możesz przeglądać zapisane ustalenia.':'Zapisz ustalenia przed zatwierdzeniem. Minimum czterech wniosków jest zasadą metodyki ENESA, nie deklarowanym wymaganiem normy.' }}</p>
@foreach(['strengths'=>'Mocne strony','weaknesses'=>'Słabe strony','opportunities'=>'Szanse','threats'=>'Zagrożenia'] as $key=>$label)<label class="field"><span>SWOT · {{ $label }}</span>@if($client)<div class="readonly">{{ data_get($answers,'swot.'.$key)?:'Do uzupełnienia przez konsultanta' }}</div>@else<textarea name="answers[swot][{{ $key }}]">{{ data_get($answers,'swot.'.$key) }}</textarea>@endif</label>@endforeach
@for($i=0;$i<max(4,count($answers['conclusions']??[]));$i++)<h3>Wniosek {{ $i+1 }}</h3>@foreach(['finding'=>'Wniosek','decision'=>'Decyzja projektowa','document'=>'Dokument powiązany'] as $key=>$label)<label class="field"><span>{{ $label }}</span>@if($client)<div class="readonly">{{ data_get($answers,'conclusions.'.$i.'.'.$key)?:'Do uzupełnienia przez konsultanta' }}</div>@else<textarea name="answers[conclusions][{{ $i }}][{{ $key }}]">{{ data_get($answers,'conclusions.'.$i.'.'.$key) }}</textarea>@endif</label>@endforeach@endfor
</section>
</fieldset>
<div class="sticky"><div class="bar">@if($editable)<button class="primary" name="operation" value="save">Zapisz i przelicz propozycje</button><button name="operation" value="submit">Przekaż do weryfikacji</button>@endif
@php($operations = $client ? match($review->status){'submitted'=>['withdraw'],'reviewing','approved'=>['request_reopen'],default=>[]} : match($review->status){'submitted'=>['review'],'reviewing'=>['return','approve'],'approved'=>['reopen'],default=>[]})
@foreach($operations as $op)<button name="operation" value="{{ $op }}">{{ $actions[$op] }}</button>@endforeach
@if($review->revision===0)<button name="operation" value="copy">Skopiuj poprzedni rok</button>@endif</div><label class="field"><span>Komentarz / powód zwrotu lub ponownego otwarcia</span><input name="note" maxlength="4000" value="{{ old('note') }}"></label></div>
</form>
<section id="documents"><h2>5. Dokumenty i historia</h2><p>Eksport obejmuje ostatnie zapisane dane. Najpierw zapisz zmiany w formularzu. Plik zostanie również dodany do dokumentacji klienta w punkcie 4.1.</p>
@if($review->exists)<form data-export method="post" action="{{ route($routePrefix.'export',$audit) }}">@csrf<input type="hidden" name="year" value="{{ $review->year }}"><div class="bar"><button name="format" value="pdf">Zapisz PDF</button><button name="format" value="docx">Zapisz Word</button></div></form>
<form data-export method="post" target="_blank" action="{{ route($routePrefix.'export',$audit) }}">@csrf<input type="hidden" name="year" value="{{ $review->year }}"><input type="hidden" name="format" value="pdf"><input type="hidden" name="preview" value="1"><button>Podgląd PDF (bez zapisu)</button></form>@endif
<details><summary>Do zatwierdzenia danych: {{ count($blockers) }} uwag</summary><ul>@foreach($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul></details>
<h3>Historia zmian — rok {{ $review->year }}</h3><div class="scroll"><table><thead><tr><th>Rewizja</th><th>Data</th><th>Osoba</th><th>Operacja</th><th>Komentarz</th></tr></thead><tbody>@forelse($history as $event)<tr><td>{{ $event->revision }}</td><td>{{ $event->created_at }}</td><td>{{ $event->name??'—' }}</td><td>{{ $actions[$event->action]??$event->action }}</td><td>{{ $event->note }}</td></tr>@empty<tr><td colspan="5">Brak zapisanych zmian.</td></tr>@endforelse</tbody></table></div>
</section>
</main>
<script>
(() => {
    const navigation = document.querySelector('.review-section-nav');
    const updateNavigationOffset = () => document.documentElement.style.setProperty(
        '--review-nav-offset', `${Math.ceil(navigation.getBoundingClientRect().height) + 16}px`
    );
    updateNavigationOffset();
    new ResizeObserver(updateNavigationOffset).observe(navigation);
    const form = document.getElementById('review-form');
    let dirty = false;
    form.addEventListener('input', event => {
        if (event.target.name?.startsWith('answers[')) dirty = true;
    });
    form.addEventListener('submit', event => {
        if (dirty && !['save', 'submit'].includes(event.submitter?.value)) {
            event.preventDefault();
            alert('Najpierw zapisz zmienione odpowiedzi przyciskiem „Zapisz i przelicz propozycje”.');
            return;
        }
        dirty = false;
    });
    document.querySelectorAll('[data-export]').forEach(exportForm => exportForm.addEventListener('submit', event => {
        if (dirty) {
            event.preventDefault();
            alert('Eksport obejmuje zapisane dane. Najpierw zapisz zmiany w ankiecie.');
        }
    }));
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
})();
</script></body></html>
