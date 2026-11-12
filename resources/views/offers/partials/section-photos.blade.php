@foreach($photos as $photo)
    <div style="text-align:center;margin:6px 0;page-break-inside:avoid;">
        <img src="{{ $photo['source'] }}" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" alt="Zdjęcie sekcji">
    </div>
@endforeach
