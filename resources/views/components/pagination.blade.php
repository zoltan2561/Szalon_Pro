@if($paginator->hasPages())
<nav class="admin-pagination" aria-label="Lapozás">
    <p>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }} találat</p>
    <div class="pagination-controls">
        @if($paginator->onFirstPage())
            <span class="pagination-disabled">Előző oldal</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Előző oldal</a>
        @endif
        <span class="pagination-position">{{ $paginator->currentPage() }}. / {{ $paginator->lastPage() }} oldal</span>
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Következő oldal</a>
        @else
            <span class="pagination-disabled">Következő oldal</span>
        @endif
    </div>
</nav>
@endif
