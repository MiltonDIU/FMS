<!-- Awards Tab -->
<div x-show="tab === 'awards'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6m0 5h12m0-5h1.5a2.5 2.5 0 0 1 0 5H18m0 0v2a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4v-2m8 0h-4"/></svg>
        Special Awards, Fellowships &amp; Scholarships
    </h3>
    @if($teacher->awards->isEmpty())
        <p class="text-xs text-slate-400">No special awards or achievements documented.</p>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($teacher->awards as $awr)
                <div class="p-4 rounded-2xl border border-diu-accent/20 bg-diu-accent/5 backdrop-blur-xs flex gap-3.5 items-start">
                    <div class="bg-white text-diu-accent p-2 rounded-xl shrink-0 border border-diu-accent/10 shadow-2xs">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6m0 5h12m0-5h1.5a2.5 2.5 0 0 1 0 5H18m0 0v2a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4v-2m8 0h-4"/></svg>
                    </div>
                    <div>
                        <span class="bg-diu-accent text-white text-[8px] font-sans font-bold uppercase px-1.5 py-0.5 rounded-xs">{{ $awr->type ?? 'Award' }}</span>
                        <h4 class="text-xs font-bold text-slate-800 mt-1.5 leading-snug font-display">{{ $awr->title }}</h4>
                        <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ $awr->awarding_body ?? '' }} • {{ $awr->year ?? '' }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
