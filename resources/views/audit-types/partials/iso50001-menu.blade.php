<nav class="iso-nav iso-nav-{{$side ?? 'left'}}" aria-label="Struktura ISO 50001">
    <div class="iso-nav-head"><i class="ti ti-certificate"></i><div><strong>ISO 50001</strong><small>Struktura audytu</small></div></div>
    @foreach($chapters as $chapter)
        @if($chapter['items'] ?? [])
            <details class="iso-nav-group">
                <summary class="iso-nav-item"><span class="iso-nav-number">{{$chapter['number']}}</span><span>{{$chapter['title']}}</span><i class="ti ti-chevron-down"></i></summary>
                <button type="button" class="iso-nav-item iso-nav-sub" data-iso-target="{{$chapter['id']}}"><span class="iso-nav-number">{{$chapter['number']}}</span><span>Wprowadzenie</span></button>
                @foreach($chapter['items'] as $item)
                    <button type="button" class="iso-nav-item iso-nav-sub" data-iso-target="{{$item['id']}}"><span class="iso-nav-number">{{$item['number']}}</span><span>{{$item['title']}}</span></button>
                @endforeach
            </details>
        @else
            <button type="button" class="iso-nav-item {{$loop->first?'active':''}}" data-iso-target="{{$chapter['id']}}"><span class="iso-nav-number">{{$chapter['number']}}</span><span>{{$chapter['title']}}</span></button>
        @endif
    @endforeach
</nav>
