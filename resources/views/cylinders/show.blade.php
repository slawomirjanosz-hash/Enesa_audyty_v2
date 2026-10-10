@extends($layout)
@section('title', 'Urządzenie '.$cylinder->serial_number)
@section('page-title', 'Karta urządzenia')
@section('content')
<div class="cyl-module">
@include('cylinders.style')
<div class="cyl-head"><div><h1>{{ $cylinder->name }} · {{ $cylinder->serial_number }}</h1><p>{{ $cylinder->company?->name ?? 'Bez przypisanego klienta' }}</p></div><div class="cyl-actions"><a class="cyl-btn" href="{{ route($routePrefix.'index') }}">Wróć do rejestru</a>@if($canManage)<a class="cyl-btn" href="{{ route('cylinders.edit', $cylinder) }}">Edytuj dane</a><form method="post" action="{{ route('cylinders.archive', $cylinder) }}">@csrf @method('PATCH')<input type="hidden" name="archived" value="{{ $cylinder->archived_at ? 0 : 1 }}"><button class="cyl-btn">{{ $cylinder->archived_at ? 'Przywróć' : 'Archiwizuj' }}</button></form>@endif</div></div>
<div class="cyl-card cyl-facts">
@include('cylinders.photo-thumb')
<span class="cyl-status-chip cyl-status-{{ $cylinder->conditionStatus() }}">{{ $cylinder->conditionLabel() }}</span>
@foreach(['manufacturer_mark'=>'Znak wytwórczy', 'manufactured_year'=>'Rok', 'capacity_litres'=>'Poj. [dm³]', 'working_pressure_bar'=>'Ciśnienie [bar]'] as $field=>$label)<span class="cyl-fact"><span class="cyl-muted">{{ $label }}:</span><strong>{{ $cylinder->$field ?? '—' }}</strong></span>@endforeach
</div>
<details class="cyl-card"><summary>Parametry stałe urządzenia</summary><div class="cyl-table"><table><thead><tr><th>Parametr</th><th>Wartość</th></tr></thead><tbody>
@foreach(\App\Models\Cylinder::PARAMETER_LABELS as $field=>$label)<tr><td>{{ $label }}</td><td>{{ $cylinder->$field ?? '—' }}</td></tr>@endforeach
<tr><td>Uwagi</td><td>{{ $cylinder->notes ?? '—' }}</td></tr></tbody></table></div></details>
@if($canManage && !$cylinder->archived_at)
<details class="cyl-photo-upload" @if($errors->has('photo')) open @endif><summary class="cyl-btn">{{ $cylinder->photo ? 'Zmień zdjęcie urządzenia' : '+ Dodaj zdjęcie urządzenia' }}</summary><form action="{{ route('cylinders.photo.store', $cylinder) }}" method="post" enctype="multipart/form-data">@csrf<label for="cylinder-photo">Zdjęcie ogólne JPG, PNG lub WebP</label><input id="cylinder-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><button class="cyl-btn cyl-btn-primary">Zapisz zdjęcie</button><small class="cyl-muted">Do 8 MB i 12 megapikseli. Nie zastępuje zdjęć przeglądów.</small></form></details>
<details class="cyl-card" @if($errors->hasAny(['inspected_at','next_due_at','result','observations','weight_kg','working_pressure_bar','inspection_video','inspection_photos','inspection_photos.*'])) open @endif><summary class="cyl-btn cyl-btn-primary">Zrób przegląd</summary>
<form method="post" enctype="multipart/form-data" action="{{ route('cylinders.inspections.store', $cylinder) }}">@csrf
<p class="cyl-muted">Nowy wpis w historii. Wykonujący: {{ auth()->user()->name }}.</p>
@include('cylinders.inspection-fields')
<p><button class="cyl-btn cyl-btn-primary">Zapisz przegląd</button></p></form></details>
@endif
<div class="cyl-card"><h2>Historia przeglądów</h2><div class="cyl-table"><table class="cyl-inspections" data-server-sort="date,inspector,result,weight,pressure,notes,due,"><thead><tr><th>Data</th><th>Wykonujący</th><th>Wynik</th><th>Waga [kg]</th><th>Ciśnienie [bar]</th><th>Uwagi</th><th>Następny termin</th><th data-sortable="false">Załączniki / akcje</th></tr></thead><tbody>
@forelse($inspections as $inspection)
<tr><td class="cyl-date">{{ $inspection->inspected_at->format('d.m.Y') }}<span class="cyl-secondary">#{{ $inspection->id }} · rewizja {{ $inspection->revision }}</span></td><td>{{ $inspection->inspector_name }}</td><td>{{ $results[$inspection->result] ?? $inspection->result }}</td>
<td>{{ $inspection->weight_kg === null ? '—' : number_format($inspection->weight_kg, 3, ',', ' ') }}</td><td>{{ $inspection->working_pressure_bar === null ? '—' : number_format($inspection->working_pressure_bar, 3, ',', ' ') }}</td><td class="cyl-observations">{{ $inspection->observations }}</td>
<td class="cyl-date"><span class="cyl-due cyl-due-{{ $inspection->next_due_at?->lt(today()) ? 'late' : ($inspection->next_due_at?->lte(today()->addMonthNoOverflow()) ? 'soon' : 'neutral') }}">{{ $inspection->next_due_at?->format('d.m.Y') ?? 'Nie ustalono' }}</span></td>
<td><div class="cyl-entry-actions">
@include('cylinders.media-icons', ['mediaInspection'=>$inspection])
@if($canManage && !$cylinder->archived_at)<a class="cyl-btn" href="{{ route('cylinders.inspections.edit', [$cylinder, $inspection]) }}">Edytuj / dodaj pliki</a>@if(!$inspection->videos_count)<a class="cyl-btn" href="{{ route('cylinders.show', ['cylinder'=>$cylinder,'attach'=>$inspection->id,'page'=>$inspections->currentPage()]) }}#cylinder-video-add">+ Film / link</a>@endif @endif
</div></td></tr>
@empty<tr><td colspan="8">Nie zapisano jeszcze przeglądów tego urządzenia.</td></tr>@endforelse
</tbody></table></div>{{ $inspections->links() }}</div>
@include('cylinders.videos')
@include('cylinders.photo-viewer')
@include('cylinders.media-dialog')
</div>
@endsection
