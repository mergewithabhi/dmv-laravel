@if ($paginator->total() > 0)
    <nav class="public-gallery-pagination" aria-label="Gallery pagination">
        <p>
            Showing {{ number_format($paginator->firstItem()) }}-{{ number_format($paginator->lastItem()) }}
            of {{ number_format($paginator->total()) }}
        </p>
        @if ($paginator->hasPages())
            <div>
                <button
                    type="button"
                    wire:click="previousPage"
                    wire:loading.attr="disabled"
                    @disabled($paginator->onFirstPage())
                    aria-label="Previous gallery page"
                >
                    <img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">
                </button>
                <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
                <button
                    type="button"
                    wire:click="nextPage"
                    wire:loading.attr="disabled"
                    @disabled(! $paginator->hasMorePages())
                    aria-label="Next gallery page"
                >
                    <img src="{{ asset('assets/icons/arrow-right.svg') }}" alt="">
                </button>
            </div>
        @endif
    </nav>
@endif
