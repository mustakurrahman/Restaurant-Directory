{{-- Page links in the brand style. Laravel passes $paginator and $elements; use with {{ $items->links() }} --}}
@if ($paginator->hasPages())
    @php
        $base = 'inline-flex min-w-9 items-center justify-center rounded-lg border px-3 py-1.5 text-sm font-medium transition';
    @endphp

    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-cream/60">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>

        <div class="flex flex-wrap gap-1">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="{{ $base }} cursor-not-allowed border-white/10 text-cream/30" aria-disabled="true">&larr; Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $base }} border-white/20 text-cream hover:border-gold-500 hover:text-gold-500">&larr; Previous</a>
            @endif

            {{-- Page numbers ($element is "..." or a list of pages) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $base }} border-transparent text-cream/40">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="{{ $base }} border-gold-500 bg-gold-500 text-ink-950">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Go to page {{ $page }}" class="{{ $base }} border-white/20 text-cream hover:border-gold-500 hover:text-gold-500">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $base }} border-white/20 text-cream hover:border-gold-500 hover:text-gold-500">Next &rarr;</a>
            @else
                <span class="{{ $base }} cursor-not-allowed border-white/10 text-cream/30" aria-disabled="true">Next &rarr;</span>
            @endif
        </div>
    </nav>
@endif
