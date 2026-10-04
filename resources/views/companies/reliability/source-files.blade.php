@if(app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'view', $company))
@php($sourceFiles = \App\Models\CompanyReliabilityFile::where('company_id', $company->id)->latest('id')->get())
<section class="rel-card">
<h3>Poufne dokumenty źródłowe — sprawozdania</h3>
@if($sourceFiles->isEmpty())
<p>Nie zapisano jeszcze dokumentów źródłowych.</p>
@else
<div style="overflow-x:auto"><table style="width:100%">
<thead><tr><th>Nazwa dokumentu</th><th>Data dodania</th><th>Rozmiar</th><th data-sortable="false">Akcje</th></tr></thead>
<tbody>@foreach($sourceFiles as $sourceFile)<tr>
<td>{{$sourceFile->name}}</td><td data-sort-value="{{$sourceFile->created_at->timestamp}}">{{$sourceFile->created_at->format('d.m.Y H:i')}}</td>
<td data-sort-value="{{$sourceFile->size}}">{{number_format($sourceFile->size / 1024, 1, ',', ' ')}} KB</td>
<td><a href="{{route('companies.reliability.files.download',[$company,$sourceFile])}}">Pobierz</a>
@if(strtolower(pathinfo($sourceFile->stored_path, PATHINFO_EXTENSION)) === 'xml' && app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'create', $company))
<form method="POST" action="{{route('companies.reliability.files.import',[$company,$sourceFile])}}" style="display:inline" onsubmit="return confirm('Uzupełnić pola z tego XML? Niezapisane zmiany formularza zostaną utracone.')">@csrf<button type="submit">Uzupełnij pola z XML</button></form>
@endif
@if(app(\App\Services\CompanyReliabilityAccess::class)->allows(auth()->user(), 'delete', $company))
<form method="POST" action="{{route('companies.reliability.files.destroy',[$company,$sourceFile])}}" style="display:inline" onsubmit="return confirm('Usunąć dokument i jego plik z dysku?')">@csrf @method('DELETE')<button type="submit">Usuń</button></form>
@endif</td></tr>@endforeach</tbody></table></div>
@endif
</section>
@endif
