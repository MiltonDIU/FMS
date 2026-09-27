{{--
    Pagination for the directory listings.

    Rendered through $paginator->links(), so $paginator and $elements are the
    only things in scope. Every control is a wire:click rather than a link: the
    list is a Livewire component and a page change should not cost a round trip
    through the router.

    Styled from this theme's own tokens rather than fixed greys, so it stays
    legible when the lights go out and follows the palette when the brand colour
    changes.
--}}
@if($paginator->hasPages())
    <nav class="mt-8 flex flex-wrap items-center justify-center gap-1.5" aria-label="Pagination">

        @if($paginator->onFirstPage())
            <span class="btn btn-ghost" style="opacity: 0.4; cursor: not-allowed;" aria-disabled="true">Previous</span>
        @else
            <button type="button" rel="prev" class="btn btn-ghost"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled">Previous</button>
        @endif

        @foreach($elements as $element)
            @if(is_string($element))
                <span class="numeral px-2">{{ $element }}</span>
            @endif

            @if(is_array($element))
                @foreach($element as $page => $url)
                    @if($page == $paginator->currentPage())
                        <span class="chip is-active" style="min-width: 2.25rem; justify-content: center;"
                              aria-current="page">{{ $page }}</span>
                    @else
                        <button type="button" class="chip" style="min-width: 2.25rem; justify-content: center;"
                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                wire:loading.attr="disabled">{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if($paginator->hasMorePages())
            <button type="button" rel="next" class="btn btn-ghost"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled">Next</button>
        @else
            <span class="btn btn-ghost" style="opacity: 0.4; cursor: not-allowed;" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
