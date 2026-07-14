<!-- Experience Tab -->
<div x-show="tab === 'experience'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="7" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        Employment History
    </h3>
    @if($teacher->jobExperiences->isEmpty())
        <p class="text-xs text-slate-400">No corporate or academic work history submitted.</p>
    @else
        <div class="relative border-l border-white/40 pl-5 ml-2.5 space-y-6">
            @foreach($teacher->jobExperiences as $exp)
                <div class="relative">
                    <span class="absolute -left-7.5 top-1 bg-white border-2 border-diu-primary rounded-full w-4 h-4 flex items-center justify-center shadow-xs"><span class="w-1.5 h-1.5 bg-diu-primary rounded-full"></span></span>
                    <div class="flex items-center gap-2 text-xs font-bold text-diu-primary tracking-wide">
                        <svg class="w-3.5 h-3.5 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
                        {{ $exp->start_date ? date('Y', strtotime($exp->start_date)) : '' }} - {{ $exp->is_current ? 'Present' : ($exp->end_date ? date('Y', strtotime($exp->end_date)) : 'Past') }}
                    </div>
                    <h4 class="text-sm font-bold text-slate-800 mt-1 font-display">{{ $exp->title }}</h4>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">{{ $exp->institution_name ?? '' }}</p>
                    @if($exp->description)<p class="text-xs text-slate-500 font-sans mt-1 leading-relaxed">{{ $exp->description }}</p>@endif
                </div>
            @endforeach
        </div>
    @endif
</div>
