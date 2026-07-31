<!-- Memberships Tab -->
<div x-show="tab === 'memberships'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-5-4-4 4-4-4-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Professional Memberships &amp; Affiliations
    </h3>
    @if($teacher->memberships->isEmpty())
        <p class="text-xs text-slate-400 font-sans">No affiliated professional bodies declared.</p>
    @else
        <div class="space-y-2">
            @foreach($teacher->memberships as $mem)
                <div class="p-3  rounded-xl border border-slate-200 flex items-center gap-2.5 text-xs text-slate-700 font-sans font-medium ring-1 ring-slate-900/5">
                    <div class="w-2 h-2 rounded-full bg-diu-primary shrink-0"></div>
                    {{ optional($mem->membershipOrganization)->name }}{{ $mem->position ? ' — ' . $mem->position : '' }}{{ $mem->membership_id ? ' (ID: ' . $mem->membership_id . ')' : '' }}
                </div>
            @endforeach
        </div>
    @endif
</div>
