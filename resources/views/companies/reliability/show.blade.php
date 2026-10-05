@extends('layouts.app')
@section('page-title', 'Raport wiarygodności firmy')
@section('content')
<link rel="stylesheet" href="{{asset('css/company-reliability.css')}}">
<div class="rel-wrap">
<a href="{{$company->company_type === 'supplier' ? route('suppliers.show',$company) : route('companies.show',$company)}}">← Karta firmy</a>
<h1>Raport wiarygodności firmy</h1><h2>{{$company->name}} <small class="rel-muted">NIP {{$company->nip ?: 'brak'}}</small></h2>
@include('companies.reliability.badge', ['report'=>$reports->first()])
@foreach(($automatic['checks'] ?? []) as $check)
@if($check['state'] === 'risk')
<div role="alert" style="margin:16px 0;padding:18px;border:2px solid #b42318;border-radius:9px;background:#fde7e7;color:#851b15"><strong>ZAGROŻENIE — {{$check['label']}}</strong><br>{{$check['message']}}<br><small>Wynik bieżącego sprawdzenia. Zapisz nowy raport, aby zaktualizować ocenę na dashboardzie.</small></div>
@endif
@endforeach
<p class="rel-muted">Ocena wewnętrzna, nie gwarancja wypłacalności. Zielona ocena starsza niż 30 dni zmienia się na ostrzeżenie o potrzebie aktualizacji.</p>
@if(session('success'))<div class="rel-card" role="status">{{session('success')}}</div>@endif
@if($errors->any())<div class="rel-errors" role="alert"><strong>Popraw dane przed zapisaniem:</strong><ul>@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif
@if(app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'create', $company))
<section class="rel-card"><h2>1. Dane rejestrowe i sprawozdania</h2>
@if($autoLookup && !$errors->any())
<div id="rel-auto-lookup" data-url="{{route('companies.reliability.lookup',$company)}}" data-token="{{csrf_token()}}" data-krs="{{data_get($lookup,'krs.number')}}" role="status">Pobieranie danych KRS i VAT…</div>
<noscript><p>Włącz JavaScript, aby dane rejestrowe pobierały się automatycznie.</p></noscript>
@endif
@if($lookup && data_get($lookup,'krs.state')!=='checked')
<form class="rel-lookup" method="POST" action="{{route('companies.reliability.lookup',$company)}}">@csrf
<label for="krs">Numer KRS (opcjonalnie, 10 cyfr)</label><input id="krs" name="krs" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" style="max-width:280px" value="{{old('krs',data_get($lookup,'krs.number'))}}">
<button type="submit">Uzupełnij numer KRS</button></form>
@endif
<div class="rel-import">
<div class="rel-rdf-heading">
<a class="rel-link" href="https://rdf-przegladarka.ms.gov.pl/" target="_blank" rel="noopener noreferrer">Pobierz sprawozdanie z RDF ↗</a>
@if(data_get($lookup,'krs.number'))<p>Numer KRS do wyszukania: <strong>{{data_get($lookup,'krs.number')}}</strong></p>@endif
</div>
<form class="rel-upload" method="POST" enctype="multipart/form-data" action="{{route('companies.reliability.files.store',$company)}}">@csrf
<label for="rdf-file">Sprawozdanie z RDF — XML, PDF lub XHTML (maks. 20 MB)</label>
<input id="rdf-file" type="file" name="file" accept=".xml,.pdf,.xhtml" required aria-invalid="{{$errors->has('file')?'true':'false'}}">
<button type="submit">Zapisz plik w aplikacji</button>
</form>
<p class="rel-muted">XML uzupełnia kwoty automatycznie. Dla PDF z tekstem AI analizuje tylko wybrane fragmenty (maks. 32 tys. znaków, jedno zapytanie na plik, 20 nowych analiz dziennie). Fragmenty są przekazywane do Anthropic. Kwoty PDF wymagają potwierdzenia przy dokumencie źródłowym. Skany i XHTML pozostają załącznikami. Przed importem zapisz rozpoczęty raport.</p>
</div>
@if($lookup)<p>Sprawdzono: {{\Carbon\Carbon::parse($lookup['checked_at'])->format('d.m.Y H:i')}}. Wynik można wykorzystać do zapisu przez 30 minut.</p>
<h3>Dane rejestrowe firmy</h3>
<dl class="rel-facts">
<div><dt>Status VAT</dt><dd>{{data_get($lookup,'vat.state')==='checked' ? data_get($lookup,'vat.status') : 'Brak potwierdzonego wyniku'}}</dd></div>
<div><dt>Nazwa w rejestrze</dt><dd>{{data_get($lookup,'krs.name') ?: data_get($lookup,'vat.name') ?: 'Brak danych'}}</dd></div>
<div><dt>Numer KRS</dt><dd>{{data_get($lookup,'krs.number') ?: 'Brak danych'}}</dd></div>
<div><dt>Rejestracja w KRS</dt><dd>@if(data_get($lookup,'krs.registered_on')){{\Carbon\Carbon::parse(data_get($lookup,'krs.registered_on'))->format('d.m.Y')}} · rok {{substr(data_get($lookup,'krs.registered_on'),0,4)}}@else Brak danych @endif</dd></div>
<div><dt>Rok założenia firmy</dt><dd>Nie ustalono — data rejestracji w KRS nie musi być datą założenia.</dd></div>
<div><dt>Weryfikacja KRS</dt><dd>{{data_get($lookup,'krs.state')==='checked' ? 'Odpis pobrany' : (data_get($lookup,'krs.state')==='identity_mismatch' ? 'NIP z KRS nie zgadza się z firmą — nie użyto danych.' : 'Brak potwierdzonego wyniku KRS. Sprawdź numer i rejestr.')}}</dd></div>
@foreach(collect($automatic['checks'])->filter(fn($check)=>str_contains($check['label'],'KRS')) as $check)
<div class="rel-state-{{$check['state']}}"><dt>{{$check['label']==='KRS dział 6 — sytuacja prawna'?'Sytuacja prawna (dział 6 KRS)':$check['label']}}</dt><dd>{{$check['message']}}</dd></div>
@endforeach
</dl>
@endif</section>
<form method="POST" action="{{route('companies.reliability.store',$company)}}">@csrf
<section class="rel-card"><h2>2. Automatyczna weryfikacja i zakres sprawdzenia</h2>
@if(isset($automatic))
<p><strong>{{$automatic['summary']}}</strong></p>
<p class="rel-muted">Podgląd dotyczy ostatniego sprawdzenia i danych z zaimportowanych plików. Przy zapisie wynik zostanie przeliczony z kwot formularza.</p>
<dl class="rel-facts">
@foreach($automatic['checks'] as $check)
@continue(str_starts_with($check['label'],'Finanse') || ($lookup && ($check['label']==='Wykaz VAT' || str_contains($check['label'],'KRS'))))
<div class="rel-state-{{$check['state']}}"><dt>{{$check['label']}}</dt><dd><strong>{{['clear'=>'Brak ostrzeżeń w sprawdzonym zakresie','warning'=>'Wymaga uwagi','risk'=>'Sygnał zagrożenia','unknown'=>'Nie potwierdzono'][$check['state']]}}</strong><br>{{$check['message']}}</dd></div>
@endforeach
</dl>
@endif

<div class="rel-manual"><h3>Dodatkowa ocena pracownika</h3>
<p><a href="https://prs.ms.gov.pl/krs" target="_blank" rel="noopener noreferrer">KRS i dokumenty finansowe</a> · <a href="https://krz.ms.gov.pl/" target="_blank" rel="noopener noreferrer">Krajowy Rejestr Zadłużonych</a></p>
<div class="rel-grid">@foreach(['legal'=>'Status prawny / likwidacja', 'krz'=>'KRZ — upadłość i restrukturyzacja', 'debt'=>'Zaległe płatności (w sprawdzonych źródłach)'] as $field=>$label)
<div><label for="{{$field}}">{{$label}}</label><select id="{{$field}}" name="{{$field}}">@foreach(['unknown'=>'Nie sprawdzono / brak danych','clear'=>'Sprawdzono — nie stwierdzono zagrożeń','risk'=>'Wykryto zagrożenie'] as $value=>$text)<option value="{{$value}}" @selected(old($field,'unknown')===$value)>{{$text}}</option>@endforeach</select></div>@endforeach</div>
<p><label for="verified_on">Data weryfikacji źródeł</label><input type="date" id="verified_on" name="verified_on" required max="{{now()->format('Y-m-d')}}" value="{{old('verified_on',now()->format('Y-m-d'))}}" aria-invalid="{{$errors->has('verified_on')?'true':'false'}}"></p>
</div>
</section>
<section class="rel-card"><h2>3. Dane ze sprawozdań finansowych</h2><p>Wpisz kwoty w PLN (przelicz dane podane w tysiącach). Puste pole oznacza brak danych, a nie zero. Zobowiązania bilansowe nie oznaczają zaległych płatności.</p>
<style>[data-financial-state="warning"]{background:#fff4d6!important;border-color:#b77c08!important}[data-financial-state="risk"]{background:#fde7e7!important;border-color:#b42318!important}</style>
<div id="financial-health" data-preview-url="{{route('companies.reliability.financial-preview',$company)}}" aria-live="polite">
@include('companies.reliability.financial-health', ['health' => $automatic['financial']])
</div>
<div class="rel-periods">@for($i=0;$i<3;$i++)<fieldset class="rel-period"><legend>Okres {{$i+1}}</legend><div class="rel-period-fields">
@foreach(['year'=>'Rok obrotowy','revenue'=>'Przychody PLN','profit'=>'Wynik netto PLN','equity'=>'Kapitał własny PLN','liabilities'=>'Zobowiązania PLN'] as $field=>$label)
@php($amountValue = old('finances.'.$i.'.'.$field, data_get($finances ?? [], $i.'.'.$field)))
<div><label for="f-{{$i}}-{{$field}}">{{$label}}</label><input id="f-{{$i}}-{{$field}}" data-financial-state="{{data_get($automatic,'financial.periods.'.$i.'.fields.'.$field,'clear')}}" type="{{$field==='year'?'number':'text'}}" @if($field!=='year') data-financial-amount inputmode="decimal" @endif name="finances[{{$i}}][{{$field}}]" value="{{$field==='year'?$amountValue:\App\Support\FinancialAmount::display($amountValue)}}" aria-invalid="{{$errors->has('finances.'.$i.'.'.$field)?'true':'false'}}"></div>@endforeach
<div><label for="source-{{$i}}">Źródło / dokument i okres sprawozdawczy</label><input id="source-{{$i}}" name="finances[{{$i}}][source]" maxlength="1000" value="{{old('finances.'.$i.'.source', data_get($finances ?? [], $i.'.source'))}}"></div></div></fieldset>@endfor</div>
</section>
<section class="rel-card"><h2>4. Ocena i zapis raportu</h2><label for="status">Sposób oceny</label><select id="status" name="status" aria-invalid="{{$errors->has('status')?'true':'false'}}"><option value="auto" @selected(old('status','auto')==='auto')>Automatyczna — na podstawie sprawdzonych źródeł</option>@foreach(\App\Models\CompanyReliabilityReport::LABELS as $value=>$label)<option value="{{$value}}" @selected(old('status','auto')===$value)>Ręczna: {{$label}}</option>@endforeach</select>
<p class="rel-muted">Zielony: komplet sprawdzeń i pozytywna ocena. Żółty: ostrożność, wątpliwości lub ograniczone dane. Czerwony: wykryte zagrożenie. Szary: brak oceny. System nie wydaje decyzji kredytowej.</p>
<p>Tryb automatyczny nie wymaga ręcznych potwierdzeń. Niepełny zakres daje ocenę ostrożną, nie potwierdzenie braku długów.</p>
<label for="notes">Uwagi i uzasadnienie oceny</label><textarea id="notes" name="notes" placeholder="Przy ręcznej ocenie opisz podstawę decyzji: sprawdzony rejestr lub dokument, datę i wynik. W trybie automatycznym uwagi są opcjonalne." maxlength="6000" aria-invalid="{{$errors->has('notes')?'true':'false'}}">{{old('notes')}}</textarea>
<p><button type="submit">Stwórz raport i zapisz PDF w dokumentach firmy</button></p></section>
</form>
@endif
@include('companies.reliability.list')
</div>
<script type="module" src="{{asset('js/financial-amount.js')}}"></script>
<script type="module" src="{{asset('js/financial-health.js')}}"></script>
<script src="{{asset('js/reliability-lookup.js')}}" defer></script>
@endsection
