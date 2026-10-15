<h3>Lokalizacje samochodów</h3>
<p style="font-size:12px">Zmiana nazwy aktualizuje przypisane samochody. Lokalizację można usunąć, gdy nie korzysta z niej żadne auto.</p>
<table class="hr-table"><thead><tr><th>Lokalizacja</th><th data-sortable="false">Akcje</th></tr></thead><tbody>
@forelse($vehicleLocations as $location)
<tr><td data-sort-value="{{$location->name}}"><input class="hr-input" form="location-{{$location->id}}" name="name" value="{{$location->name}}" maxlength="255" required aria-label="Nazwa lokalizacji"></td><td>
<div class="hr-actions"><form id="location-{{$location->id}}" method="POST" action="{{route('hr.vehicle-locations.update',$location)}}">@csrf @method('PUT')<button class="hr-btn">Zapisz</button></form>
<form method="POST" action="{{route('hr.vehicle-locations.destroy',$location)}}" onsubmit="return confirm('Usunąć lokalizację?')">@csrf @method('DELETE')<button class="hr-btn danger">Usuń</button></form></div>
</td></tr>
@empty<tr data-sort-fixed><td colspan="2">Brak lokalizacji.</td></tr>@endforelse
</tbody></table>
<form method="POST" action="{{route('hr.vehicle-locations.store')}}" style="margin-top:12px">@csrf
<label for="new-vehicle-location">Nowa lokalizacja</label><div class="hr-actions"><input id="new-vehicle-location" class="hr-input" name="name" maxlength="255" required><button class="hr-btn">Dodaj lokalizację</button></div></form>
