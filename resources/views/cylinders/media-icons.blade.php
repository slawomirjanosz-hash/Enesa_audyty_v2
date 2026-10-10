@foreach(['videos'=>'video', 'photos'=>'photo'] as $relation=>$icon)
@if($mediaInspection && $mediaInspection->{$relation.'_count'})
<a class="cyl-media-icon" href="{{ route($routePrefix.'inspections.media', [$cylinder, $mediaInspection]) }}" data-inspection-media title="{{ $relation === 'videos' ? 'Film przeglądu' : 'Zdjęcia przeglądu' }}" aria-label="{{ $relation === 'videos' ? 'Film przeglądu' : 'Zdjęcia przeglądu' }}"><i class="ti ti-{{ $icon }}" aria-hidden="true"></i></a>
@else
<span class="cyl-media-icon is-empty" aria-disabled="true" title="{{ $relation === 'videos' ? 'Brak filmu' : 'Brak zdjęć' }}"><i class="ti ti-{{ $icon }}" aria-hidden="true"></i></span>
@endif
@endforeach
<span class="cyl-media-icon is-empty" aria-disabled="true" title="Protokół PDF — dostępny w kolejnym etapie"><i class="ti ti-file-type-pdf" aria-hidden="true"></i></span>
