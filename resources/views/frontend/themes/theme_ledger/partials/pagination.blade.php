{{--
    Pagination for the directory listings.

    Rendered through $paginator->links(), so $paginator and $elements are the
    only things in scope. Every control is a wire:click rather than a link: the
    list is a Livewire component and a page change should not cost a round trip
    through the router.

    A ledger says which rows you are looking at, not only which page you are on —
    "13–24 of 1,284" is the fact somebody scanning a long list actually wants,
    and the page numbers beside it are the way to move.
--}}
@if($paginator->hasPages())
    <nav class="mt-5 pt-4 flex flex-wrap items-center justify-between gap-x-6 gap-y-3 rule-hard"
         aria-label="Pagination">

        <p class="figure">
            {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }}
            of {{ number_format($paginator->total()) }}
        </p>

        <div class="flex flex-wrap items-center gap-1.5">
            @if($paginator->onFirstPage())
                <span class="pick" style="opacity: 0.4; cursor: not-allowed;" aria-disabled="true">Previous</span>
            @else
                <button type="button" rel="prev" class="pick"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled">Previous</button>
            @endif

            @foreach($elements as $element)
                @if(is_string($element))
                    <span class="figure px-1">{{ $element }}</span>
                @endif

                @if(is_array($element))
                    @foreach($element as $page => $url)
                        @if($page == $paginator->currentPage())
                            <span class="pick is-active" style="min-width: 2rem; justify-content: center;"
                                  aria-current="page">{{ $page }}</span>
                        @else
                            <button type="button" class="pick" style="min-width: 2rem; justify-content: center;"
                                    wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                    wire:loading.attr="disabled">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if($paginator->hasMorePages())
                <button type="button" rel="next" class="pick"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled">Next</button>
            @else
                <span class="pick" style="opacity: 0.4; cursor: not-allowed;" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
