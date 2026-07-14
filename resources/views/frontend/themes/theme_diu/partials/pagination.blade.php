@if($paginator->hasPages())
    <nav class="flex items-center justify-center gap-1.5 pt-6 border-t border-gray-100 font-sans" aria-label="Pagination">
        {{-- Previous --}}
        @if($paginator->onFirstPage())
            <span class="px-3.5 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-300 cursor-not-allowed">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="px-3.5 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-all cursor-pointer">Previous</a>
        @endif

        {{-- Page numbers --}}
        @foreach($elements as $element)
            @if(is_string($element))
                <span class="px-3 py-1.5 text-xs font-semibold text-gray-400">{{ $element }}</span>
            @endif

            @if(is_array($element))
                @foreach($element as $page => $url)
                    @if($page == $paginator->currentPage())
                        <span class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold bg-diu-primary text-white shadow-md shadow-diu-primary/10 cursor-default">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50 transition-all cursor-pointer">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="px-3.5 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-all cursor-pointer">Next</a>
        @else
            <span class="px-3.5 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-300 cursor-not-allowed">Next</span>
        @endif
    </nav>
@endif
