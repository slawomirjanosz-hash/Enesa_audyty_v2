@php
    $activeTab = request('tab', 'iso50001');
    $activeSection = request('section', $chapters[0]['id'] ?? null);
    $auditRoute = fn (string $tab, ?string $section = null) => route('client.audits.show', array_filter([
        'audit' => $audit,
        'tab' => $tab,
        'section' => $section,
    ]));
@endphp
<nav class="sidebar-nav" data-client-audit-menu>
    <a href="{{ route('client.dashboard') }}" class="nav-link exit-app"><i class="ti ti-arrow-left"></i> Wyjdź do aplikacji</a>
    <div class="audit-nav-title">Audyt ISO 50001</div>
    <div class="audit-nav-name">{{ $audit->title }}</div>
    <ul>
        @foreach($chapters as $chapter)
            @php($chapterItems = $chapter['items'] ?? [])
            @php($chapterOpen = $activeTab === 'iso50001' && ($activeSection === $chapter['id'] || collect($chapterItems)->contains('id', $activeSection)))
            @if($chapterItems)
                <li class="nav-item audit-nav-group">
                    <details @if($chapterOpen) open @endif>
                        <summary class="nav-link {{ $chapterOpen ? 'active' : '' }}"><i class="ti ti-folder"></i><span>{{ $chapter['number'] }}. {{ $chapter['title'] }}</span><i class="ti ti-chevron-down audit-nav-chevron"></i></summary>
                        <a href="{{ $auditRoute('iso50001', $chapter['id']) }}" class="nav-link audit-sub {{ $activeSection === $chapter['id'] ? 'active' : '' }}">Wprowadzenie</a>
                        @foreach($chapterItems as $item)
                            <a href="{{ $auditRoute('iso50001', $item['id']) }}" class="nav-link audit-sub {{ $activeSection === $item['id'] ? 'active' : '' }}">{{ $item['number'] }} {{ $item['title'] }}</a>
                        @endforeach
                    </details>
                </li>
            @else
                <li class="nav-item"><a href="{{ $auditRoute('iso50001', $chapter['id']) }}" class="nav-link {{ $chapterOpen ? 'active' : '' }}"><i class="ti ti-file-text"></i> {{ $chapter['number'] }}. {{ $chapter['title'] }}</a></li>
            @endif
        @endforeach
    </ul>
</nav>
