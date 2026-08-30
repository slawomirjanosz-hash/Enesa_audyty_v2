@php
    $activeTab = request('tab', 'overview');
    $activeSection = request('section', $chapters[0]['id'] ?? null);
    $auditRoute = fn (string $tab, ?string $section = null) => route('client.audits.show', array_filter([
        'audit' => $audit,
        'tab' => $tab,
        'section' => $section,
    ]));
@endphp
<nav class="sidebar-nav" data-client-audit-menu>
    <a href="{{ route('client.dashboard') }}" class="nav-link exit-app"><i class="ti ti-arrow-left"></i> Wyjdź do aplikacji</a>
    <div class="audit-nav-title">Audyt klienta</div>
    <div class="audit-nav-name">{{ $audit->title }}</div>
    <ul>
        @foreach(['overview' => ['ti-eye', 'Podgląd'], 'schedule' => ['ti-calendar-stats', 'Harmonogram i zadania'], 'documents' => ['ti-files', 'Dokumenty'], 'surveys' => ['ti-clipboard-check', 'Audyty'], 'passports' => ['ti-bolt', 'Paszporty energetyczne']] as $tab => [$icon, $label])
            <li class="nav-item"><a href="{{ $auditRoute($tab) }}" class="nav-link {{ $activeTab === $tab ? 'active' : '' }}"><i class="ti {{ $icon }}"></i> {{ $label }}</a></li>
        @endforeach
    </ul>
    <div class="audit-nav-title">ISO 50001</div>
    <ul>
        @foreach($chapters as $chapter)
            <li class="nav-item"><a href="{{ $auditRoute('iso50001', $chapter['id']) }}" class="nav-link {{ $activeTab === 'iso50001' && $activeSection === $chapter['id'] ? 'active' : '' }}"><i class="ti ti-chevron-right"></i> {{ $chapter['number'] }}. {{ $chapter['title'] }}</a></li>
            @foreach($chapter['items'] ?? [] as $item)
                <li class="nav-item"><a href="{{ $auditRoute('iso50001', $item['id']) }}" class="nav-link audit-sub {{ $activeTab === 'iso50001' && $activeSection === $item['id'] ? 'active' : '' }}">{{ $item['number'] }} {{ $item['title'] }}</a></li>
            @endforeach
        @endforeach
    </ul>
</nav>
