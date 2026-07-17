<!-- Memberships Tab -->
<div x-show="tab === 'memberships'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-5-4-4-4-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Professional Memberships &amp; Affiliations
    </h3>
    @if($teacher->memberships->isEmpty())
        <p class="text-xs text-slate-400 font-sans">No affiliated professional bodies declared.</p>
    @else
        <div class="space-y-2">
            @foreach($teacher->memberships as $mem)
                <div class="p-3 rounded-2xl border border-slate-200 flex items-center gap-2.5 text-xs text-slate-700 font-sans font-medium ring-1 ring-slate-900/5">
                    <div class="w-2 h-2 rounded-full bg-diu-primary shrink-0"></div>
                    <div class="min-w-0">
                        <span class="font-semibold text-slate-800">{{ optional($mem->membershipType)->name ?? 'Membership' }}</span>
                        @if($mem->membershipOrganization?->name)
                            <span class="text-slate-500">&middot; {{ $mem->membershipOrganization->name }}</span>
                        @endif
                        @if($mem->position)
                            <span class="text-slate-500">&middot; {{ $mem->position }}</span>
                        @endif
                        @if($mem->start_date || $mem->end_date)
                            <span class="text-slate-400 text-[10px]">
                                &middot;
                                {{ $mem->start_date?->format('Y') ?? '' }}
                                &ndash;
                                {{ $mem->end_date?->format('Y') ?? 'Present' }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
