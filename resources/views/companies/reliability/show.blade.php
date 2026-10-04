@extends('layouts.app')
@section('page-title', 'Raport wiarygodności firmy')
@section('content')
<style>
.rel-wrap{line-height:1.5}.rel-wrap p{margin:10px 0 14px}.rel-wrap h1{margin:10px 0}.rel-wrap h2{margin:0 0 12px}.rel-wrap h3{margin:12px 0 6px}.rel-wrap a:not(.rel-link){color:var(--green,#1b4e3c)}
.rel-wrap{max-width:1200px;margin:auto}.rel-card{background:#fff;border:1px solid #dfe5e1;border-radius:12px;padding:24px;margin:20px 0}.rel-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.rel-wrap label{display:block;font-weight:600;margin-bottom:6px}.rel-wrap input,.rel-wrap select,.rel-wrap textarea{box-sizing:border-box;width:100%;padding:11px;border:1px solid #bbcfc4;border-radius:7px;background:#fff;font:inherit}.rel-wrap textarea{min-height:110px}.rel-wrap button,.rel-link{display:inline-flex;padding:11px 16px;border:0;border-radius:7px;background:var(--green,#1b4e3c);color:#fff;cursor:pointer;text-decoration:none}.rel-wrap th{text-align:left;padding:10px 8px;background:#f4f7f5}.rel-muted{color:#65746c;font-size:13px}.rel-errors{padding:18px;background:#ffeded;border:1px solid #b42318;color:#8c1a13;border-radius:9px}.rel-wrap [aria-invalid=true]{border:2px solid #b42318;background:#fff4f4}.rel-wrap fieldset{border:1px solid #dfe5e1;padding:16px;margin:14px 0;border-radius:8px}.rel-wrap pre{white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px}@media(max-width:800px){.rel-grid{grid-template-columns:1fr}.rel-card{padding:16px}}
</style>
<div class="rel-wrap">
<a href="{{$company->company_type === 'supplier' ? route('suppliers.show',$company) : route('companies.show',$company)}}">← Karta firmy</a>
<h1>Raport wiarygodności firmy</h1><h2>{{$company->name}} <small class="rel-muted">NIP {{$company->nip ?: 'brak'}}</small></h2>
@include('companies.reliability.badge', ['report'=>$reports->first()])
<p class="rel-muted">Ocena wewnętrzna, nie gwarancja wypłacalności. Zielona ocena starsza niż 30 dni zmienia się na ostrzeżenie o potrzebie aktualizacji.</p>
@if(session('success'))<div class="rel-card" role="status">{{session('success')}}</div>@endif
@if($errors->any())<div class="rel-errors" role="alert"><strong>Popraw dane przed zapisaniem:</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@if(app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'create', $company))
<section class="rel-card"><h2>1. Sprawdź rejestry online</h2>
<p>KRS i wykaz VAT: automatyczne sprawdzenie po NIP. Numer KRS możesz uzupełnić, gdy wykaz VAT go nie zwróci. Dane są buforowane przez 15 minut. Dashboard nie wykonuje zapytań do rejestrów.</p>
<form method="POST" action="{{route('companies.reliability.lookup',$company)}}">@csrf
<label for="krs">Numer KRS (opcjonalnie, 10 cyfr)</label><input id="krs" name="krs" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" style="max-width:280px" value="{{old('krs',data_get($lookup,'krs.number'))}}">
<button type="submit">Sprawdź KRS i VAT</button></form>
<div style="margin-top:16px;padding:16px;background:#f4f7f5;border-radius:8px">
<a class="rel-link" href="https://rdf-przegladarka.ms.gov.pl/" target="_blank" rel="noopener noreferrer">Pobierz sprawozdanie z RDF ↗</a>
@if(data_get($lookup,'krs.number'))<p>Numer KRS do wyszukania: <strong>{{data_get($lookup,'krs.number')}}</strong></p>@endif
<p class="rel-muted">1. Otwórz RDF, przejdź zabezpieczenie i pobierz dokument na komputer. 2. Wybierz pobrany plik poniżej i zapisz go w aplikacji. Portal RDF nie przekazuje plików bezpośrednio do naszej aplikacji.</p>
<form method="POST" enctype="multipart/form-data" action="{{route('companies.reliability.files.store',$company)}}">@csrf
<label for="rdf-file">Sprawozdanie z RDF — XML, PDF lub XHTML (maks. 20 MB)</label>
<input id="rdf-file" type="file" name="file" accept=".xml,.pdf,.xhtml" required aria-invalid="{{$errors->has('file')?'true':'false'}}">
<p><button type="submit">Zapisz plik w aplikacji</button></p>
</form>
<p class="rel-muted">XML JednostkaInna automatycznie uzupełni kwoty w sekcji 3 po sprawdzeniu NIP. PDF i XHTML zostaną zapisane jako załączniki. Import nie zmienia oceny firmy. Przed importem zapisz rozpoczęty raport — formularz zostanie ponownie otwarty.</p>
</div>
@if($lookup)<p>Sprawdzono: {{\Carbon\Carbon::parse($lookup['checked_at'])->format('d.m.Y H:i')}}. Wynik można wykorzystać do zapisu przez 30 minut.</p>
<div class="rel-grid"><div><h3>Wykaz VAT</h3>@if(data_get($lookup,'vat.state')==='checked')<p>{{data_get($lookup,'vat.name')}}</p><strong>{{data_get($lookup,'vat.status')}}</strong><p class="rel-muted">Identyfikator: {{data_get($lookup,'vat.request_id')}}</p>@else<p>Brak potwierdzonego wyniku — rejestr niedostępny, brak wpisu lub niepoprawny NIP.</p>@endif</div>
<div><h3>KRS</h3>@if(data_get($lookup,'krs.state')==='checked')<p>{{data_get($lookup,'krs.name')}} · {{data_get($lookup,'krs.number')}}</p><details><summary>Dział 6 — informacje do oceny</summary><pre>{{json_encode(data_get($lookup,'krs.section6'), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)}}</pre></details>@else<p>{{data_get($lookup,'krs.state')==='identity_mismatch' ? 'NIP z KRS nie zgadza się z firmą — nie użyto danych.' : 'Brak potwierdzonego wyniku KRS. Sprawdź numer i rejestr.'}}</p>@endif</div></div>
@if(data_get($lookup,'krs.registered_on'))<p><strong>Rok rejestracji w KRS: {{substr(data_get($lookup,'krs.registered_on'),0,4)}}</strong> · {{\Carbon\Carbon::parse(data_get($lookup,'krs.registered_on'))->format('d.m.Y')}}</p><p class="rel-muted">Data rejestracji w KRS nie musi być datą powstania firmy.</p>@endif
@endif</section>
<form method="POST" action="{{route('companies.reliability.store',$company)}}">@csrf
<section class="rel-card"><h2>2. Weryfikacja i źródła</h2>
<p>Te kontrole wykonuje upoważniony pracownik. Nie pobieramy automatycznie KRZ, raportów BIG/KRD ani sprawozdań RDF. Brak wpisu nie dowodzi braku wszystkich długów.</p>
<p><a href="https://prs.ms.gov.pl/krs" target="_blank" rel="noopener noreferrer">KRS i dokumenty finansowe</a> · <a href="https://krz.ms.gov.pl/" target="_blank" rel="noopener noreferrer">Krajowy Rejestr Zadłużonych</a></p>
<div class="rel-grid">@foreach(['legal'=>'Status prawny / likwidacja', 'krz'=>'KRZ — upadłość i restrukturyzacja', 'debt'=>'Zaległe płatności (w sprawdzonych źródłach)'] as $field=>$label)
<div><label for="{{$field}}">{{$label}}</label><select id="{{$field}}" name="{{$field}}">@foreach(['unknown'=>'Nie sprawdzono / brak danych','clear'=>'Sprawdzono — nie stwierdzono zagrożeń','risk'=>'Wykryto zagrożenie'] as $value=>$text)<option value="{{$value}}" @selected(old($field,'unknown')===$value)>{{$text}}</option>@endforeach</select></div>@endforeach</div>
<p><label for="verified_on">Data weryfikacji źródeł</label><input type="date" id="verified_on" name="verified_on" required max="{{now()->format('Y-m-d')}}" value="{{old('verified_on',now()->format('Y-m-d'))}}" aria-invalid="{{$errors->has('verified_on')?'true':'false'}}"></p>
<label for="evidence">Źródła i dowody sprawdzeń</label><textarea id="evidence" name="evidence" placeholder="Dla każdej kontroli podaj rejestr, identyfikator sprawdzenia lub dokumentu, datę i wynik. Nie wklejaj tajnych linków dostępowych." aria-invalid="{{$errors->has('evidence')?'true':'false'}}">{{old('evidence')}}</textarea>
</section>
<section class="rel-card"><h2>3. Dane ze sprawozdań finansowych</h2><p>Wpisz kwoty w PLN (przelicz dane podane w tysiącach). Puste pole oznacza brak danych, a nie zero. Zobowiązania bilansowe nie oznaczają zaległych płatności.</p>
@for($i=0;$i<3;$i++)<fieldset><legend>Okres {{$i+1}} (opcjonalny)</legend><div class="rel-grid">
@foreach(['year'=>'Rok obrotowy','revenue'=>'Przychody PLN','profit'=>'Wynik netto PLN','equity'=>'Kapitał własny PLN','liabilities'=>'Zobowiązania PLN'] as $field=>$label)
@php($amountValue = old('finances.'.$i.'.'.$field, data_get($finances ?? [], $i.'.'.$field)))
<div><label for="f-{{$i}}-{{$field}}">{{$label}}</label><input id="f-{{$i}}-{{$field}}" type="{{$field==='year'?'number':'text'}}" @if($field!=='year') data-financial-amount inputmode="decimal" @endif name="finances[{{$i}}][{{$field}}]" value="{{$field==='year'?$amountValue:\App\Support\FinancialAmount::display($amountValue)}}" aria-invalid="{{$errors->has('finances.'.$i.'.'.$field)?'true':'false'}}"></div>@endforeach
<div><label for="source-{{$i}}">Źródło / dokument i okres sprawozdawczy</label><input id="source-{{$i}}" name="finances[{{$i}}][source]" maxlength="1000" value="{{old('finances.'.$i.'.source', data_get($finances ?? [], $i.'.source'))}}"></div></div></fieldset>@endfor
</section>
<section class="rel-card"><h2>4. Ocena i zapis raportu</h2><label for="status">Ocena upoważnionego pracownika</label><select id="status" name="status" aria-invalid="{{$errors->has('status')?'true':'false'}}">@foreach(\App\Models\CompanyReliabilityReport::LABELS as $value=>$label)<option value="{{$value}}" @selected(old('status','unassessed')===$value)>{{$label}}</option>@endforeach</select>
<p class="rel-muted">Zielony: komplet sprawdzeń i pozytywna ocena. Żółty: ostrożność, wątpliwości lub ograniczone dane. Czerwony: wykryte zagrożenie. Szary: brak oceny. System nie wydaje decyzji kredytowej.</p>
<label for="notes">Uzasadnienie oceny i zalecenia *</label><textarea id="notes" name="notes" required maxlength="6000" aria-invalid="{{$errors->has('notes')?'true':'false'}}">{{old('notes')}}</textarea>
<p><button type="submit">Stwórz raport i zapisz PDF w dokumentach firmy</button></p></section>
</form>
@endif
@include('companies.reliability.list')
</div>
<script type="module" src="{{asset('js/financial-amount.js')}}"></script>
@endsection
