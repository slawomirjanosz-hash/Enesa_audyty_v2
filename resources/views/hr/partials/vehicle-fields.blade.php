@php($vehicle = $vehicle ?? new \App\Models\HrVehicle)
<div class="hr-grid">
    @if(!$vehicle->exists)
    <div class="hr-field"><label for="vehicle-type">Rodzaj *</label><select id="vehicle-type" name="type"><option value="private" @selected(old('type')==='private')>Prywatny — widoczny tylko dla mnie</option>@if($canTeam)<option value="company" @selected(old('type')==='company')>Służbowy</option>@endif</select></div>
    @else
    <div class="hr-field"><label>Rodzaj</label><p>{{$vehicle->type==='private'?'Prywatny — widoczny tylko dla autora':'Służbowy'}}</p></div>
    @endif
    @foreach(['name'=>'Nazwa', 'registration_number'=>'Nr rejestracyjny', 'make_model'=>'Marka / model', 'fuel_card_number'=>'Nr karty Orlen', 'responsible_person'=>'Osoba odpowiedzialna', 'usage_location'=>'Miejsce użytkowania', 'insurer'=>'Ubezpieczyciel', 'registration_document'=>'Dowód rejestracyjny — gdzie się znajduje'] as $field=>$label)
    <div class="hr-field"><label for="vehicle-{{$field}}">{{$label}}{{in_array($field,['name','registration_number'])?' *':''}}</label><input id="vehicle-{{$field}}" name="{{$field}}" value="{{old($field,$vehicle->$field)}}" maxlength="{{ match($field) { 'registration_number'=>30, 'fuel_card_number'=>50, 'insurer'=>150, default=>255 } }}" @required(in_array($field,['name','registration_number'])) @if($field==='fuel_card_number') inputmode="numeric" @endif>@error($field,'vehicle')<small class="vehicle-error">{{$message}}</small>@enderror</div>
    @endforeach
    <div class="hr-field"><label for="vehicle-vat">VAT</label><select id="vehicle-vat" name="vat_percent"><option value="">Nie podano</option>@foreach([50,100] as $percent)<option value="{{$percent}}" @selected((string)old('vat_percent',$vehicle->vat_percent)===(string)$percent)>{{$percent}}%</option>@endforeach</select></div>
    <div class="hr-field"><label for="vehicle-cit">CIT (%)</label><input id="vehicle-cit" type="number" name="cit_percent" min="0" max="100" step="0.01" value="{{old('cit_percent',$vehicle->cit_percent)}}">@error('cit_percent','vehicle')<small class="vehicle-error">{{$message}}</small>@enderror</div>
    @foreach(['vat_26'=>'VAT 26','gps'=>'GPS'] as $field=>$label)
    <div class="hr-field"><label for="vehicle-{{$field}}">{{$label}}</label><select id="vehicle-{{$field}}" name="{{$field}}"><option value="">Nie podano</option><option value="1" @selected((string)old($field,$vehicle->$field===null?'':(int)$vehicle->$field)==='1')>Tak</option><option value="0" @selected((string)old($field,$vehicle->$field===null?'':(int)$vehicle->$field)==='0')>Nie</option></select></div>
    @endforeach
    @foreach(['insurance_expires_at'=>'Koniec ubezpieczenia','inspection_due_at'=>'Data kolejnego przeglądu','logbook_entry_at'=>'Książka ewidencji pojazdu — data ostatniego wpisu','service_book_entry_at'=>'Książka serwisowa — data ostatniego wpisu'] as $field=>$label)
    <div class="hr-field"><label for="vehicle-{{$field}}">{{$label}}</label><input id="vehicle-{{$field}}" type="date" name="{{$field}}" value="{{old($field,$vehicle->$field?->format('Y-m-d'))}}">@error($field,'vehicle')<small class="vehicle-error">{{$message}}</small>@enderror</div>
    @endforeach
    <div class="hr-field"><label for="vehicle-service-book">Książka serwisowa</label><select id="vehicle-service-book" name="service_book_status"><option value="">Nie podano</option><option value="available" @selected(old('service_book_status',$vehicle->service_book_status)==='available')>Jest</option><option value="missing" @selected(old('service_book_status',$vehicle->service_book_status)==='missing')>Brak książki</option></select></div>
</div>
<p style="font-size:12px;color:#64746d;margin-top:12px">Puste daty oznaczają brak danych. Wartości VAT i CIT są wpisywane ręcznie; nie są wyliczane przez system.</p>
