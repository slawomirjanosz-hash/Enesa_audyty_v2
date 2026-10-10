<section class="cyl-module">
<h2>Przegląd #{{ $inspection->id }} · {{ $inspection->inspected_at->format('d.m.Y') }}</h2>
<h3>Film</h3>
@forelse($inspection->videos as $video)
@if($video->external_url)
<p><a href="{{ $video->external_url }}" target="_blank" rel="noopener noreferrer">{{ $video->title }} — otwórz u źródła ↗</a></p>
@else
<video controls playsinline preload="metadata" style="width:100%;max-height:50vh" src="{{ route($routePrefix.'videos.show', [$cylinder, $video]) }}"></video>
<p><a href="{{ route($routePrefix.'videos.show', [$cylinder, $video]) }}" target="_blank" rel="noopener" download> Pobierz film — gdy przeglądarka nie obsługuje nagrania</a></p>
@endif
@empty<p>Brak filmu.</p>@endforelse
<h3>Zdjęcia</h3><div class="cyl-inspection-gallery">
@forelse($inspection->photos as $photo)
<a href="{{ route($routePrefix.'inspections.photos.show', [$cylinder, $inspection, $photo]) }}" target="_blank" rel="noopener" data-inspection-image><img src="{{ route($routePrefix.'inspections.photos.show', [$cylinder, $inspection, $photo, 'thumbnail'=>1]) }}" alt="Zdjęcie przeglądu {{ $loop->iteration }}" loading="lazy" width="96" height="96"></a>
@empty<p>Brak zdjęć.</p>@endforelse
</div><img data-inspection-large hidden alt="Powiększone zdjęcie przeglądu" style="max-width:100%;max-height:65vh;object-fit:contain">
</section>
