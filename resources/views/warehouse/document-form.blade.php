@extends('layouts.app')
@section('page-title','Magazyn — nowy dokument')
@section('content')
@include('warehouse._layout')
<div class="wh-card"><h2>{{\App\Models\WarehouseDocument::TYPES[$type]}}</h2><form id="wh-document" method="POST" action="{{route('warehouse.documents.store')}}" data-lookup="{{route('warehouse.lookup')}}" data-type="{{$type}}">@csrf<input type="hidden" name="type" value="{{$type}}"><input type="hidden" name="submission_token" value="{{old('submission_token',$token)}}">
<div class="wh-grid"><div class="wh-field"><label for="wh-date">Data dokumentu *</label><input id="wh-date" type="date" name="document_date" required max="{{now()->toDateString()}}" value="{{old('document_date',now()->toDateString())}}" aria-invalid="{{$errors->has('document_date')?'true':'false'}}"></div><div class="wh-field"><label for="wh-reference">Odniesienie / numer faktury / dokument źródłowy</label><input id="wh-reference" name="reference" maxlength="200" value="{{old('reference')}}"></div>
@if($type==='issue')<div class="wh-field wh-wide"><label for="wh-project">Projekt (opcjonalnie)</label><select id="wh-project" name="project_id"><option value="">Bez przypisania — wpisz cel poniżej</option>@foreach($projects as $project)<option value="{{$project->id}}" @selected(old('project_id')==$project->id)>{{$project->number}} — {{$project->name}}</option>@endforeach</select><small class="wh-muted">Widoczne są tylko projekty, do których masz dostęp. Wydanie nie księguje kosztu automatycznie.</small></div>@endif
<div class="wh-field wh-wide"><label for="wh-notes">{{$type==='adjustment'?'Przyczyna korekty / opis spisu *':'Opis / cel operacji *'}}</label><textarea id="wh-notes" name="notes" rows="2" required maxlength="3000" aria-invalid="{{$errors->has('notes')?'true':'false'}}">{{old('notes')}}</textarea></div></div>
<div class="wh-info">@if($type==='adjustment')Wpisz rzeczywistą ilość po przeliczeniu towaru, nie różnicę. System zapisze różnicę jako korektę. Jeśli stan zmieni się w czasie spisu, zapis zostanie zatrzymany.@else Wpisz ilość {{$type==='receipt'?'przyjmowaną':'wydawaną'}}. Cały dokument zapisuje się razem — błąd jednej pozycji nie zmieni żadnego stanu.@endif Wszystkie ceny netto w PLN. Maksymalnie 50 pozycji. Zapisane dokumenty pozostają w historii.</div>
<section aria-labelledby="wh-catalog-title">
<h2 id="wh-catalog-title">Wybierz towary z magazynu</h2>
<div class="wh-field"><label for="wh-live-search">Szukaj po kodzie lub nazwie</label><input id="wh-live-search" type="search" maxlength="200" placeholder="Zacznij pisać — tabela filtruje się automatycznie" autocomplete="off"></div>
<p id="wh-picker-status" role="status" aria-live="polite" class="wh-muted"></p>
<p class="wh-muted">Wszystkie aktywne towary. Uzupełnij ilość@if($type==='receipt'), cenę i dostawcę (podpowiedź z ostatniego przyjęcia)@endif, następnie kliknij „Dodaj”.</p>
<div class="wh-table-wrap wh-picker-scroll"><table id="wh-catalog" class="wh-table wh-picker-table"><thead><tr><th data-sort-type="text">Kod</th><th>Nazwa</th><th>Jednostka</th><th>Stan</th><th>{{$type==='adjustment'?'Ilość rzeczywista':'Ilość'}}</th>@if($type==='receipt')<th>Cena netto PLN</th><th>Dostawca</th>@endif<th>Akcje</th></tr></thead><tbody>
@foreach($catalogItems as $rowItem)
@include('warehouse._document-row', ['rowItem'=>$rowItem,'chosen'=>false,'line'=>[],'index'=>$loop->index])
@endforeach
</tbody></table></div>
<p id="wh-no-results" class="wh-muted" @if($catalogItems->isNotEmpty()) hidden @endif>Brak aktywnych towarów pasujących do wyszukiwania.</p>
</section>
<section aria-labelledby="wh-chosen-title">
<h2 id="wh-chosen-title">Pozycje dokumentu (<span id="wh-chosen-count">{{count($formLines)}}</span>/50)</h2>
<p class="wh-muted">Możesz jeszcze zmienić dane lub usunąć wybrane pozycje. Stan magazynu zmieni się po zapisaniu dokumentu.</p>
<div class="wh-table-wrap"><table id="wh-chosen" class="wh-table wh-picker-table"><thead><tr><th data-sort-type="text">Kod</th><th>Nazwa</th><th>Jednostka</th><th>Stan przy wyborze</th><th>{{$type==='adjustment'?'Ilość rzeczywista':'Ilość'}}</th>@if($type==='receipt')<th>Cena netto PLN</th><th>Dostawca</th>@endif<th>Akcje</th></tr></thead><tbody id="wh-lines">
@foreach($formLines as $index=>$line)
@include('warehouse._document-row', ['rowItem'=>$selectedItems->get($line['item_id'] ?? null),'chosen'=>true,'line'=>$line,'index'=>$index])
@endforeach
</tbody></table></div>
<p id="wh-none-selected" class="wh-muted" @if(count($formLines)) hidden @endif>Wybierz towary z tabeli powyżej.</p>
</section>
<div class="wh-actions wh-sticky"><a class="wh-btn" href="{{route('warehouse.index')}}">Anuluj</a><button class="wh-btn primary" type="submit">Zapisz dokument i rozlicz stan</button></div>
</form></div><script src="{{asset('js/warehouse.js')}}?v=2" defer></script>
@endsection
