@php
    $today = now('Europe/Warsaw')->toDateString();
    $all = $vehicle->locationPeriods->sortBy('starts_on');
    $history = $all->filter(fn($p) => $p->ends_on && $p->ends_on->toDateString() < $today);
    $planned = $all->filter(fn($p) => !$p->ends_on || $p->ends_on->toDateString() >= $today)->map(fn($p) => ['id'=>$p->id,'location'=>$p->location,'starts_on'=>$p->starts_on->toDateString(),'ends_on'=>$p->ends_on?->toDateString()])->values()->all();
    if (!(int)$vehicle->location_revision) $planned = [['location'=>$vehicle->usage_location ?? '', 'starts_on'=>now('Europe/Warsaw')->startOfMonth()->toDateString(), 'ends_on'=>now('Europe/Warsaw')->endOfMonth()->toDateString()]];
    $planned = old('periods', $planned);
    $locationConfig = ['periods'=>$planned,'today'=>$today,'start'=>now('Europe/Warsaw')->startOfMonth()->toDateString(),'end'=>now('Europe/Warsaw')->endOfMonth()->toDateString()];
@endphp
<style>.location-plan-fields{grid-template-columns:2fr 1fr 1fr}.location-plan-fields label{display:grid;gap:6px;font-size:12px}.location-plan-fields input{width:100%}@media(max-width:700px){.location-plan-fields{grid-template-columns:1fr}}</style>
<div class="hr-card" data-location-plan-content>
<div class="hr-card-head"><h2>Lokalizacje: {{$vehicle->name}} · {{$vehicle->registration_number}}</h2>@if($modal ?? false)<button class="hr-btn danger" type="button" data-close-location-modal aria-label="Zamknij lokalizacje">×</button>@else<a class="hr-btn" href="{{route('hr.index',['tab'=>'vehicles'])}}">Wróć do samochodów</a>@endif</div>
<div style="padding:20px">
@if(session('success'))<p role="status">{{session('success')}}</p>@endif
@if($errors->any())<p role="alert" style="color:#b91c1c">{{$errors->first()}}</p>@endif
<p role="alert" data-location-error style="color:#b91c1c"></p>
<p>Obecna lokalizacja: <strong>{{$vehicle->usage_location ?: 'Nieokreślona'}}</strong></p>
@if(!(int)$vehicle->location_revision && $vehicle->usage_location)<p>Dotychczasowa lokalizacja obowiązuje bezterminowo do zapisania planu. Data jej rozpoczęcia nie była rejestrowana.</p>@endif
@if($canEditLocation)
<p>Domyślny okres obejmuje bieżący miesiąc. Daty są włączne. Przy zmianie aktualnego przypisania dotychczasowa lokalizacja zostaje zachowana do wczoraj, a nowa obowiązuje od dzisiaj. Nie można nadpisywać zakończonej historii.</p>
<form method="POST" action="{{route('hr.vehicles.location-plan.save',$vehicle)}}" id="location-plan" data-form-draft="false">
@csrf @method('PUT')
<input type="hidden" name="revision" value="{{old('revision',$vehicle->location_revision)}}">
<div id="location-periods"></div>
<div class="hr-actions"><button class="hr-btn" type="button" id="add-location-period">+ Dodaj okres</button><button class="hr-btn" type="submit">Zapisz przypisania</button></div>
</form>

<template id="location-period-template">
<fieldset style="border:1px solid #cad6d0;border-radius:8px;padding:14px;margin:12px 0">
<div class="hr-grid location-plan-fields">
<label>Lokalizacja
<select class="hr-input" data-location-select data-location-field="location" required>
<option value="">Wybierz lokalizację</option>
@foreach(\App\Models\HrVehicleLocation::orderBy('name')->pluck('name') as $name)<option value="{{$name}}">{{$name}}</option>@endforeach
<option value="Nie dotyczy">Nie dotyczy</option>
<option value="__custom__" data-custom-option>+ Wpisz inną lokalizację…</option>
</select>
<input class="hr-input" data-custom-location hidden disabled maxlength="255" placeholder="Wpisz lokalizację" aria-label="Inna lokalizacja">
</label>
<label>Od <input class="hr-input" data-location-field="starts_on" type="date" required></label>
<label>Do <input class="hr-input" data-location-field="ends_on" type="date" required></label>
</div>
<input data-location-field="id" type="hidden">
<label><input type="checkbox" data-permanent> Na stałe — bez daty końcowej, od dzisiaj</label>
<button class="hr-btn danger" type="button" data-remove-period>Usuń okres</button>
</fieldset>
</template>
@else
<h3>Obecne i zaplanowane przypisania</h3>
@foreach($planned as $p)<p>{{$p['location']}}: {{$p['starts_on']}} — {{$p['ends_on'] ?: 'Na stałe'}}</p>@endforeach
@endif
<h3 style="margin-top:24px">Historia zakończonych przypisań</h3>
<table class="hr-table"><thead><tr><th>Lokalizacja</th><th>Od</th><th>Do</th></tr></thead><tbody>
@forelse($history as $period)<tr><td>{{$period->location}}</td><td data-sort-value="{{$period->starts_on->toDateString()}}">{{$period->starts_on->format('d.m.Y')}}</td><td data-sort-value="{{$period->ends_on->toDateString()}}">{{$period->ends_on->format('d.m.Y')}}</td></tr>
@empty<tr data-sort-fixed><td colspan="3">Brak zakończonych przypisań.</td></tr>@endforelse
</tbody></table>
</div>
<script type="application/json" data-location-plan-config>@json($locationConfig)</script>
</div>
