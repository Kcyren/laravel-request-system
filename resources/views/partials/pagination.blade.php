@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pagination">
        <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
        <span style="display:flex; gap:8px;">
            <a href="{{ $paginator->previousPageUrl() ?? '#' }}" rel="prev" class="btn btn-secondary btn-sm"
               @if ($paginator->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>
                <x-icon name="arrow-left" /> Previous
            </a>
            <a href="{{ $paginator->nextPageUrl() ?? '#' }}" rel="next" class="btn btn-secondary btn-sm"
               @unless ($paginator->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless>
                Next <x-icon name="arrow-right" />
            </a>
        </span>
    </nav>
@endif
