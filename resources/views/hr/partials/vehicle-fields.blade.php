@php
    $vehicle = $vehicle ?? new \App\Models\HrVehicle;
    $vehicleChoices = \App\Models\HrVehicle::choicesFor(auth()->user());
@endphp
<div class="hr-grid">
    @if(!$vehicle->exists)
    <div class="hr-field"><label for="vehicle-type">Rodzaj *</label><select id="vehicle-type" name="type"><option value="private" @selected(old('type')==='private')>Prywatny — widoczny tylko dla mnie</option>@if($canTeam)<option value="company" @selected(old('type')==='company')>Służbowy</option>@endif</select></div>
    @else
    <div class="hr-field"><label>Rodzaj</label><p>{{$vehicle->type==='private'?'Prywatny — widoczny tylko dla autora':'Służbowy'}}</p></div>
    @endif
    @foreach(['name'=>'Nazwa', 'registration_number'=>'Nr rejestracyjny', 'make_model'=>'Marka / model', 'registration_document'=>'Dowód rejestracyjny — gdzie się znajduje / uwagi'] as $field=>$label)
    <div class="hr-field"><label for="vehicle-{{$field}}">{{$label}}{{in_array($field,['name','registration_number'])?' *':''}}</label><input id="vehicle-{{$field}}" name="{{$field}}" value="{{old($field,$vehicle->$field)}}" maxlength="{{ match($field) { 'registration_number'=>30, 'fuel_card_number'=>50, 'insurer'=>150, default=>255 } }}" @required(in_array($field,['name','registration_number'])) @if($field==='fuel_card_number') inputmode="numeric" @endif>@error($field,'vehicle')<small class="vehicle-error">{{$message}}</small>@enderror</div>
    @endforeach
    <div class="hr-field"><label for="vehicle-usage_location">Lokalizacja</label><select id="vehicle-usage_location" name="usage_location"><option value="">Nie podano</option>
    @php $locationValue = old('usage_location', $vehicle->usage_location); @endphp
    @foreach(\App\Models\HrVehicleLocation::orderBy('name')->pluck('name') as $location)<option value="{{$location}}" @selected($locationValue===$location)>{{$location}}</option>@endforeach
    @if($locationValue && !\App\Models\HrVehicleLocation::where('name',$locationValue)->exists())<option value="{{$locationValue}}" selected>{{$locationValue}} (dotychczasowa)</option>@endif
    </select>@error('usage_location','vehicle')<small class="vehicle-error">{{$message}}</small>@enderror</div>
    <div class="hr-field"><label for="vehicle-responsible-user">Osoba odpowiedzialna — użytkownik</label><select id="vehicle-responsible-user" name="responsible_user_id"><option value="">Osoba spoza bazy — wpiszę ręcznie</option>
    @foreach(\App\Models\HrVehicle::eligibleResponsibleUsers()->orderBy('name')->get(['id','name']) as $person)<option value="{{$person->id}}" @selected((string)old('responsible_user_id',$vehicle->responsible_user_id)===(string)$person->id)>{{$person->name}}</option>@endforeach
    </select><small>Wybrany użytkownik otrzyma powiadomienia o swoim aucie służbowym. Auto prywatne: można wskazać tylko siebie.</small>@error('responsible_user_id','vehicle')<small class="vehicle-error">{{$message}}</small>@enderror</div>
    <div class="hr-field"><label for="vehicle-responsible-person">Osoba odpowiedzialna — wpis ręczny</label><input id="vehicle-responsible-person" name="responsible_person" maxlength="255" value="{{old('responsible_person',$vehicle->responsible_person)}}"><small>Wpis ręczny nie powoduje wysyłki maili. Wybór użytkownika ma pierwszeństwo.</small></div>
    @foreach(['fuel_card_number'=>'Nr karty Orlen', 'insurer'=>'Ubezpieczyciel'] as $field=>$label)
    @php
        $choiceValue = (string) old($field, $vehicle->$field);
        $choices = collect($vehicleChoices[$field])->push($choiceValue)->filter(fn($value) => $value !== '')->unique()->values();
    @endphp
    <div class="hr-field" data-vehicle-choice>
        <label for="vehicle-{{$field}}">{{$label}}</label>
        <select id="vehicle-{{$field}}" name="{{$field}}" data-choice-select>
            <option value="">Nie podano</option>
            @foreach($choices as $choice)<option value="{{$choice}}" @selected($choiceValue===(string)$choice)>{{$choice}}</option>@endforeach
            <option value="" data-choice-new>+ Dodaj nową wartość…</option>
        </select>
        <input name="{{$field}}" aria-label="{{$label}} — nowa wartość" data-choice-input hidden disabled maxlength="{{ $field==='fuel_card_number' ? 50 : ($field==='insurer' ? 150 : 255) }}" placeholder="Wpisz nową wartość" style="margin-top:6px">
        @error($field,'vehicle')<small class="vehicle-error">{{$message}}</small>@enderror
    </div>
    @endforeach
    <div class="hr-field"><label for="vehicle-vat">VAT</label><select id="vehicle-vat" name="vat_percent"><option value="">Nie podano</option>@foreach([50,100] as $percent)<option value="{{$percent}}" @selected((string)old('vat_percent',$vehicle->vat_percent)===(string)$percent)>{{$percent}}%</option>@endforeach</select></div>
    <div class="hr-field"><label for="vehicle-cit">CIT (%)</label><input id="vehicle-cit" type="number" name="cit_percent" min="0" max="100" step="0.01" value="{{old('cit_percent',$vehicle->cit_percent)}}">@error('cit_percent','vehicle')<small class="vehicle-error">{{$message}}</small>@enderror</div>
    @foreach(['vat_26'=>'VAT 26','gps'=>'GPS','registration_document_present'=>'Dowód rejestracyjny — dostępny'] as $field=>$label)
    <div class="hr-field"><label for="vehicle-{{$field}}">{{$label}}</label><select id="vehicle-{{$field}}" name="{{$field}}"><option value="">Nie podano</option><option value="1" @selected((string)old($field,$vehicle->$field===null?'':(int)$vehicle->$field)==='1')>Tak</option><option value="0" @selected((string)old($field,$vehicle->$field===null?'':(int)$vehicle->$field)==='0')>Nie</option></select></div>
    @endforeach
    @foreach(['insurance_expires_at'=>'Koniec ubezpieczenia','inspection_due_at'=>'Data kolejnego przeglądu','logbook_entry_at'=>'Książka ewidencji pojazdu — data ostatniego wpisu','service_book_entry_at'=>'Książka serwisowa — data ostatniego wpisu'] as $field=>$label)
    <div class="hr-field"><label for="vehicle-{{$field}}">{{$label}}</label><input id="vehicle-{{$field}}" type="date" name="{{$field}}" value="{{old($field,$vehicle->$field?->format('Y-m-d'))}}">@error($field,'vehicle')<small class="vehicle-error">{{$message}}</small>@enderror</div>
    @endforeach
    <div class="hr-field"><label for="vehicle-service-book">Książka serwisowa</label><select id="vehicle-service-book" name="service_book_status"><option value="">Nie podano</option><option value="available" @selected(old('service_book_status',$vehicle->service_book_status)==='available')>Jest</option><option value="missing" @selected(old('service_book_status',$vehicle->service_book_status)==='missing')>Brak książki</option></select></div>
</div>
<script>
document.querySelectorAll('[data-vehicle-choice]').forEach(field=>{
    if(field.dataset.ready)return;field.dataset.ready='1';
    const select=field.querySelector('[data-choice-select]'),input=field.querySelector('[data-choice-input]');
    const name=select.name;
    select.addEventListener('change',()=>{
        const custom=select.selectedOptions[0]?.hasAttribute('data-choice-new');
        input.hidden=!custom;input.disabled=!custom;input.required=custom;
        if(custom){select.removeAttribute('name');input.focus();}else{select.name=name;}
    });
});
{
    const linked=document.getElementById('vehicle-responsible-user'),manual=document.getElementById('vehicle-responsible-person'),type=document.getElementById('vehicle-type');
    if(linked && !linked.dataset.ready){
        linked.dataset.ready='1';
        const sync=()=>{
            const privateCar=type ? type.value==='private' : @js($vehicle->type==='private');
            [...linked.options].forEach(option=>{option.hidden=privateCar && option.value!=='' && option.value!==@js((string)auth()->id());});
            if(privateCar && linked.value && linked.value!==@js((string)auth()->id()))linked.value='';
            manual.disabled=linked.value!=='';
        };
        linked.addEventListener('change',sync);type?.addEventListener('change',sync);sync();
    }
}
</script>
<p style="font-size:12px;color:#64746d;margin-top:12px">Puste daty oznaczają brak danych. Wartości VAT i CIT są wpisywane ręcznie; nie są wyliczane przez system.</p>
