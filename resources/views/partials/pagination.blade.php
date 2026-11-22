@if ($paginator->hasPages())
    <nav aria-label="Pagination" style="display:flex; gap:1rem; align-items:center;">
        @if ($paginator->onFirstPage())
            <span style="color:var(--muted);">&laquo; Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Previous</a>
        @endif

        <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next &raquo;</a>
        @else
            <span style="color:var(--muted);">Next &raquo;</span>
        @endif
    </nav>
@endif
