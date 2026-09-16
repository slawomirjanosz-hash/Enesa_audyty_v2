@if($cylinder->photo)
<a class="cyl-photo-thumb" href="{{ route($routePrefix.'photo', $cylinder) }}" data-cylinder-photo aria-label="Powiększ zdjęcie butli {{ $cylinder->serial_number }}"><img src="{{ route($routePrefix.'photo', ['cylinder'=>$cylinder, 'thumbnail'=>1]) }}" alt="Butla {{ $cylinder->serial_number }}" width="36" height="36" loading="lazy"></a>
@endif
