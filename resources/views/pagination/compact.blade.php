@if ($paginator->hasPages())
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
