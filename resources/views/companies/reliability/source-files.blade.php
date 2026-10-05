@if(app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'view', $company))
@php($sourceFiles = \App\Models\CompanyReliabilityFile::where('company_id', $company->id)->latest('id')->get())
<section class="rel-card rel-documents">
<h3>Poufne dokumenty źródłowe — sprawozdania</h3>
@if($sourceFiles->isEmpty())
<p>Nie zapisano jeszcze dokumentów źródłowych.</p>
@else
<div class="rel-table-scroll"><table style="width:100%">
<thead><tr><th>Nazwa dokumentu</th><th>Data dodania</th><th>Rozmiar</th><th data-sortable="false">Akcje</th></tr></thead>
<tbody>@foreach($sourceFiles as $sourceFile)<tr>
<td>{{$sourceFile->name}}</td><td data-sort-value="{{$sourceFile->created_at->timestamp}}">{{$sourceFile->created_at->format('d.m.Y H:i')}}</td>
<td data-sort-value="{{$sourceFile->size}}">{{number_format($sourceFile->size / 1024, 1, ',', ' ')}} KB</td>
<td><div class="rel-doc-actions"><a class="rel-action" href="{{route('companies.reliability.files.download',[$company,$sourceFile])}}">Pobierz</a>
@if(strtolower(pathinfo($sourceFile->stored_path, PATHINFO_EXTENSION)) === 'xml' && app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'create', $company))
<form method="POST" action="{{route('companies.reliability.files.import',[$company,$sourceFile])}}" style="display:inline" onsubmit="return confirm('Uzupełnić pola z tego XML? Niezapisane zmiany formularza zostaną utracone.')">@csrf<button class="rel-action" type="submit">Uzupełnij pola z XML</button></form>
@endif
@if(strtolower(pathinfo($sourceFile->stored_path, PATHINFO_EXTENSION)) === 'pdf' && app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'create', $company))
<form method="POST" action="{{route('companies.reliability.files.import',[$company,$sourceFile])}}" style="display:inline" onsubmit="return confirm('Odczytać PDF? Wybrane fragmenty trafią do Anthropic. Zapisz wcześniej zmiany raportu. Ponowny odczyt tego samego pliku korzysta z zapamiętanego wyniku.')">@csrf<button class="rel-action" type="submit">Odczytaj PDF</button></form>
@endif
@if(app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'delete', $company))
<form method="POST" action="{{route('companies.reliability.files.destroy',[$company,$sourceFile])}}" style="display:inline" onsubmit="return confirm('Usunąć dokument i jego plik z dysku?')">@csrf @method('DELETE')<button class="rel-action rel-action-danger" type="submit">Usuń</button></form>
@endif</div></td></tr>@endforeach</tbody></table></div>
@foreach($sourceFiles as $sourceFile)
@if(!empty($sourceFile->parsed_finances['pdf_proposals']))
<div style="margin-top:16px;padding:16px;border:1px solid #d6a544;background:#fff8e7;border-radius:10px">
<h4>Odczyt PDF — {{$sourceFile->name}}</h4>
<p>Identyfikacja: {{$sourceFile->parsed_finances['identity_basis'] ?? 'NIP'}}.</p>
<p>Sprawdź rok, jednostkę i kwoty z cytatami. Brak odczytu nie oznacza zera. Po potwierdzeniu możesz poprawić wartości w formularzu raportu.</p>
@foreach($sourceFile->parsed_finances['pdf_proposals'] as $proposal)
<h5>Rok {{$proposal['year']}} — kwoty przeliczone na PLN</h5>
<p>Jednostka, strona {{$proposal['unit_evidence']['page'] ?? ''}}: „{{$proposal['unit_evidence']['quote'] ?? ''}}”</p>
<dl>
@foreach(['revenue'=>'Przychody','profit'=>'Wynik netto','equity'=>'Kapitał własny','liabilities'=>'Zobowiązania'] as $field=>$label)
<dt><strong>{{$label}}: {{isset($proposal[$field]) ? \App\Support\FinancialAmount::display($proposal[$field]).' zł' : 'Brak odczytu — uzupełnij'}}</strong></dt>
<dd>@if(isset($proposal['evidence'][$field]))Strona {{$proposal['evidence'][$field]['page']}}: „{{$proposal['evidence'][$field]['quote']}}”@endif</dd>
@endforeach
</dl>
@endforeach
@if(app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'create', $company))
<form method="POST" action="{{route('companies.reliability.files.import',[$company,$sourceFile])}}" onsubmit="return confirm('Potwierdzasz sprawdzenie kwot, lat i jednostek? Pola zostaną uzupełnione, niezapisane zmiany raportu utracone.')">@csrf<input type="hidden" name="confirm_pdf" value="1"><button type="submit" class="rel-action">Potwierdź odczyt i uzupełnij pola</button></form>
@endif
</div>
@endif
@endforeach
@endif
</section>
@endif
