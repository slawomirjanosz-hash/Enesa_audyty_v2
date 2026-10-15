<!doctype html><html lang="pl"><head><meta charset="utf-8"></head><body>
<h2>Samochody — informacje wymagające uwagi</h2>
@foreach($items->groupBy(fn($item) => $item['vehicle']->id) as $vehicleItems)
@php $vehicle = $vehicleItems->first()['vehicle']; @endphp
<h3>{{$vehicle->name}} · {{$vehicle->registration_number}}</h3>
<ul>@foreach($vehicleItems as $item)<li>{{$item['message']}}</li>@endforeach</ul>
@endforeach
<p>Uzupełnij dane lub zaktualizuj termin w rejestrze samochodów po rozwiązaniu problemu. Przypomnienia o przekroczonych terminach wysyłamy codziennie; raport brakujących danych w poniedziałki.</p>
<p><a href="{{route('hr.index',['tab'=>'vehicles'])}}">Otwórz samochody w HR</a></p>
</body></html>
