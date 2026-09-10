@if ($paginator->hasPages())
    @once
    <style>
        .compact-pagination{margin-top:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .compact-pagination-summary{font-size:12px;color:#68746d}
        .compact-pagination-links{display:flex;gap:5px;align-items:center;flex-wrap:wrap}
        .compact-page{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:6px 10px;border:1px solid #ddd8cd;border-radius:7px;background:#fff;color:#405047;text-decoration:none;font-size:12px;font-weight:700}
        .compact-page:hover{border-color:var(--green);color:var(--green)}
        .compact-page.active{background:var(--green);border-color:var(--green);color:#fff}
        .compact-page.disabled{opacity:.5}
    </style>
    @endonce
    <nav class="compact-pagination" role="navigation" aria-label="Paginacja">
        <div class="compact-pagination-summary">
            Wyświetlanie {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} z {{ $paginator->total() }} wyników
        </div>
        <div class="compact-pagination-links">
            @if ($paginator->onFirstPage())
                <span class="compact-page disabled">← Poprzednia</span>
            @else
                <a class="compact-page" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Poprzednia</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="compact-page disabled">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="compact-page active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="compact-page" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="compact-page" href="{{ $paginator->nextPageUrl() }}" rel="next">Następna →</a>
            @else
                <span class="compact-page disabled">Następna →</span>
            @endif
        </div>
    </nav>
@endif
