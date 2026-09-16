@extends($layout)
@section('title', 'Butla '.$cylinder->serial_number)
@section('page-title', 'Karta butli')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><div><h1>Butla {{ $cylinder->serial_number }}</h1><p>{{ $cylinder->company?->name }}</p></div><div class="cyl-actions"><a class="cyl-btn" href="{{ route($routePrefix.'index') }}">Wróć do rejestru</a>@if($canManage)<a class="cyl-btn" href="{{ route('cylinders.edit', $cylinder) }}">Edytuj dane</a><form method="post" action="{{ route('cylinders.archive', $cylinder) }}">@csrf @method('PATCH')<input type="hidden" name="archived" value="{{ $cylinder->archived_at ? 0 : 1 }}"><button class="cyl-btn">{{ $cylinder->archived_at ? 'Przywróć' : 'Archiwizuj' }}</button></form>@endif</div></div>
<div class="cyl-card cyl-facts">
@include('cylinders.photo-thumb')
<span class="cyl-status-chip cyl-status-{{ $cylinder->conditionStatus() }}"><span class="cyl-status-dot" aria-hidden="true"></span>{{ $cylinder->conditionLabel() }}</span>
@foreach(['type'=>'Typ', 'manufacturer'=>'Producent', 'manufactured_year'=>'Rok', 'capacity_litres'=>'Poj. [l]', 'working_pressure_bar'=>'Ciśn. [bar]'] as $field=>$label)<span class="cyl-fact"><span class="cyl-muted">{{ $label }}:</span><strong>{{ $cylinder->$field ?? '—' }}</strong></span>@endforeach
@if($cylinder->notes)<details class="cyl-detail-note"><summary>Uwagi o butli</summary><p style="white-space:pre-wrap">{{ $cylinder->notes }}</p></details>@endif
</div>
@if($canManage && !$cylinder->archived_at)
<details class="cyl-photo-upload" @if($errors->has('photo')) open @endif><summary class="cyl-btn">{{ $cylinder->photo ? 'Zmień zdjęcie butli' : '+ Dodaj zdjęcie butli' }}</summary><form action="{{ route('cylinders.photo.store', $cylinder) }}" method="post" enctype="multipart/form-data">@csrf<label for="cylinder-photo">Zdjęcie JPG, PNG lub WebP</label><input id="cylinder-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><button class="cyl-btn cyl-btn-primary">Zapisz zdjęcie</button><small class="cyl-muted">Do 8 MB i 12 megapikseli. Nowe zdjęcie zastąpi poprzednie.</small></form></details>
@endif
<div class="cyl-card"><h2>Historia przeglądów</h2><div class="cyl-table"><table class="cyl-inspections"><thead><tr><th>Wpis / data</th><th>Inspektor</th><th>Wynik</th><th>Zakres, pomiary i uwagi</th><th>Następny termin</th><th>Filmy i akcje</th></tr></thead><tbody>
@forelse($inspections as $inspection)
<tr><td class="cyl-date"><strong>#{{ $inspection->id }}</strong> · {{ $inspection->inspected_at->format('d.m.Y') }}<span class="cyl-secondary">Zapis {{ $inspection->created_at->format('d.m.Y H:i') }}@if($inspection->revision > 1)<br>Edycja {{ $inspection->updated_at->format('d.m.Y H:i') }} · wersja {{ $inspection->revision }}@endif</span></td><td>{{ $inspection->inspector_name }}</td><td>{{ $results[$inspection->result] ?? $inspection->result }}</td><td class="cyl-observations">{{ $inspection->observations }}</td><td class="cyl-date"><span class="cyl-due cyl-due-{{ $inspection->next_due_at?->lt(today()) ? 'late' : ($inspection->next_due_at?->lte(today()->addMonthNoOverflow()) ? 'soon' : 'neutral') }}">{{ $inspection->next_due_at?->format('d.m.Y') ?? 'Nie ustalono' }}</span></td><td><div class="cyl-entry-actions">
@if($inspection->videos_count)<a class="cyl-btn" href="{{ route($routePrefix.'show', ['cylinder'=>$cylinder,'inspection'=>$inspection->id,'page'=>$inspections->currentPage()]) }}#cylinder-videos">Filmy ({{ $inspection->videos_count }})</a>@endif
@if($canManage && !$cylinder->archived_at)<a class="cyl-btn" href="{{ route('cylinders.inspections.edit', [$cylinder, $inspection]) }}">Edytuj</a><a class="cyl-btn" href="{{ route('cylinders.show', ['cylinder'=>$cylinder,'attach'=>$inspection->id,'page'=>$inspections->currentPage()]) }}#cylinder-video-add">+ Film</a>@endif
</div></td></tr>
@empty<tr><td colspan="6">Nie zapisano jeszcze przeglądów tej butli.</td></tr>@endforelse
</tbody></table></div>{{ $inspections->links() }}</div>
@if($canManage && !$cylinder->archived_at)
<details class="cyl-card" @if($errors->hasAny(['inspected_at','next_due_at','result','observations'])) open @endif><summary>+ Zapisz przegląd</summary><form method="post" action="{{ route('cylinders.inspections.store', $cylinder) }}">@csrf<p class="cyl-muted">Wpis zostanie przypisany do Twojego konta. Późniejsze edycje są zapisywane w historii zmian.</p><div class="cyl-grid"><div><label for="inspected_at">Data przeglądu *</label><input id="inspected_at" type="date" name="inspected_at" value="{{ old('inspected_at', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required></div><div><label for="next_due_at">Następny termin według właściwej procedury</label><input id="next_due_at" type="date" name="next_due_at" value="{{ old('next_due_at') }}"></div><div class="cyl-wide"><label for="result">Wynik *</label><select id="result" name="result" required><option value="">Wybierz wynik</option>@foreach($results as $value=>$label)<option value="{{ $value }}" @selected(old('result') === $value)>{{ $label }}</option>@endforeach</select></div><div class="cyl-wide"><label for="observations">Zakres, pomiary i uwagi *</label><textarea id="observations" name="observations" rows="5" maxlength="20000" required>{{ old('observations') }}</textarea></div></div><p><button class="cyl-btn cyl-btn-primary">Zapisz w historii</button></p></form></details>
@endif
@include('cylinders.videos')
@include('cylinders.photo-viewer')
</div>
@endsection
