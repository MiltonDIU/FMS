<!-- Training Tab -->
<div x-show="tab === 'training'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        Special Training &amp; Pedagogy Programs
    </h3>
    @if($teacher->trainingExperiences->isEmpty())
        <p class="text-xs text-slate-400">No training experiences recorded.</p>
    @else
        <div class="space-y-3">
            @foreach($teacher->trainingExperiences as $trn)
                <div class="p-4 rounded-2xl border border-slate-200 flex gap-3 ring-1 ring-slate-900/5">
                    <div class="bg-diu-accent/15 text-diu-accent p-2 rounded-xl shrink-0 h-9 w-9 flex items-center justify-center">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 leading-snug font-display">{{ $trn->title }}</h4>
                        <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ $trn->organization ?? optional($trn->organizationRelation)->name ?? '' }}</p>
                        <div class="flex items-center gap-4 mt-2 text-[10px] text-slate-400 font-bold uppercase">
                            <span>Year: {{ $trn->year ?? 'N/A' }}</span>
                            @if($trn->duration_days)<span>• Duration: {{ $trn->duration_days }} Days</span>@endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
