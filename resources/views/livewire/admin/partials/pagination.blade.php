@if ($paginator->hasPages())
    <nav class="admin-pagination" aria-label="Pagination">
        <p>
            Showing <strong>{{ $paginator->firstItem() }}</strong>-<strong>{{ $paginator->lastItem() }}</strong>
            of <strong>{{ $paginator->total() }}</strong>
        </p>
        <div class="admin-pagination-actions">
            <button
                class="admin-button secondary small"
                type="button"
                wire:click="gotoPage(1)"
                wire:loading.attr="disabled"
                @disabled($paginator->onFirstPage())
            >First</button>
            <button
                class="admin-button secondary small"
                type="button"
                wire:click="previousPage"
                wire:loading.attr="disabled"
                @disabled($paginator->onFirstPage())
            >Previous</button>
            <span aria-current="page">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
            <button
                class="admin-button secondary small"
                type="button"
                wire:click="nextPage"
                wire:loading.attr="disabled"
                @disabled(! $paginator->hasMorePages())
            >Next</button>
            <button
                class="admin-button secondary small"
                type="button"
                wire:click="gotoPage({{ $paginator->lastPage() }})"
                wire:loading.attr="disabled"
                @disabled(! $paginator->hasMorePages())
            >Last</button>
        </div>
    </nav>
@elseif ($paginator->total())
    <div class="admin-pagination admin-pagination-single">
        <p>Showing <strong>{{ $paginator->total() }}</strong> of <strong>{{ $paginator->total() }}</strong></p>
    </div>
@endif
