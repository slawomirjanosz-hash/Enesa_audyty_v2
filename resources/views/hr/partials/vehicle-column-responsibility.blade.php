<div id="vehicle-column-responsibility-modal" class="hr-modal"><div class="hr-modal-card" style="max-width:580px">
<div class="hr-head"><h2 id="vehicle-column-responsibility-title" style="margin:0">Odpowiedzialność za kolumnę</h2><button type="button" class="hr-btn danger" onclick="document.getElementById('vehicle-column-responsibility-modal').classList.remove('open')" aria-label="Zamknij wybór odpowiedzialnych">×</button></div>
<p class="hr-rate-info">Wybór dotyczy tej kolumny we wszystkich autach służbowych. Zaznaczone osoby otrzymają dotyczące jej terminy i poniedziałkowe zgłoszenia brakujących danych. Bez zaznaczenia powiadomienia trafiają do wszystkich uprawnionych do odpowiedzialności za samochody. Przypisany użytkownik nadal otrzymuje informacje o swoim aucie. Prywatne auta nie trafiają do zgłoszeń flotowych.</p>
<form id="vehicle-column-responsibility-form" method="POST" data-form-draft="false">@csrf @method('PUT')<input type="hidden" name="revision" value="1">
<div class="vehicle-column-people">
@forelse($vehicleResponsibilityPeople as $person)<label><input type="checkbox" name="user_ids[]" value="{{$person->id}}">{{$person->name}}</label>@empty<p>Brak aktywnych osób z uprawnieniem „Odpowiedzialność za samochody”. Nadaj je w ustawieniach użytkowników.</p>@endforelse
</div><p id="vehicle-column-unavailable" class="vehicle-error" hidden>Część przypisanych osób jest nieaktywna lub nie ma już uprawnienia — nie otrzymuje powiadomień. Zapisz aktualny wybór.</p>
<div class="hr-actions" style="justify-content:flex-end"><button class="hr-btn" type="submit">Zapisz odpowiedzialnych</button></div></form>
</div></div>
<script>
const vehicleColumnAssignments = @js($vehicleColumnResponsibilities->map(fn($row)=>['user_ids'=>$row->user_ids,'revision'=>$row->revision]));
const vehicleColumnLabels = @js(\App\Models\HrVehicle::COLUMNS);
function openVehicleColumnResponsibility(column){
    const form=document.getElementById('vehicle-column-responsibility-form'),assignment=vehicleColumnAssignments[column]||{user_ids:[],revision:1},ids=assignment.user_ids.map(Number);
    document.getElementById('vehicle-column-responsibility-title').textContent='Odpowiedzialność: '+vehicleColumnLabels[column];
    form.action=@js(route('hr.vehicles.column-responsibility',['column'=>'__COLUMN__'])).replace('__COLUMN__',encodeURIComponent(column));
    form.elements.namedItem('revision').value=assignment.revision;
    const checkboxes=[...form.querySelectorAll('[name="user_ids[]"]')];
    checkboxes.forEach(box=>box.checked=ids.includes(Number(box.value)));
    document.getElementById('vehicle-column-unavailable').hidden=ids.every(id=>checkboxes.some(box=>Number(box.value)===id));
    document.getElementById('vehicle-column-responsibility-modal').classList.add('open');
}
</script>
