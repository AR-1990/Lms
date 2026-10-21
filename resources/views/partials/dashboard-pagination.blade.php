@if ($paginator->hasPages())
    <nav class="dash-pagination" aria-label="Pagination">
        <p class="dash-pagination-info">
            Showing <strong>{{ $paginator->firstItem() }}</strong>&ndash;<strong>{{ $paginator->lastItem() }}</strong>
            of <strong>{{ $paginator->total() }}</strong>
        </p>

        <div class="dash-pagination-links">
            @if ($paginator->onFirstPage())
                <span class="dash-page-link is-disabled" aria-hidden="true">@include('partials.dashboard-icon', ['name' => 'chevron-left'])</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="dash-page-link" rel="prev" aria-label="Previous page">@include('partials.dashboard-icon', ['name' => 'chevron-left'])</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="dash-page-link is-disabled">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="dash-page-link is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="dash-page-link">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="dash-page-link" rel="next" aria-label="Next page">@include('partials.dashboard-icon', ['name' => 'chevron-right'])</a>
            @else
                <span class="dash-page-link is-disabled" aria-hidden="true">@include('partials.dashboard-icon', ['name' => 'chevron-right'])</span>
            @endif
        </div>
    </nav>
@endif
