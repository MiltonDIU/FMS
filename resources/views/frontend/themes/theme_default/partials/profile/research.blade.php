<!-- Research Tab -->
<div x-show="tab === 'research'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.5c-1.8 0-3.5 1.4-3.5 3.5s1.7 3.5 3.5 3.5 3.5-1.4 3.5-3.5a2 2 0 0 0-.063-.5"/><path d="M10 10a2 2 0 0 0 2-2c0-1-.9-1.9-2-2s-2 .9-2 2a2 2 0 0 0 2 2Z"/><path d="M4.5 11h.5a2 2 0 0 0 2-2c0-1-.9-1.9-2-2s-2 .9-2 2a2 2 0 0 0 2 2Z"/><path d="M6 17.5a2 2 0 0 0 2 2c1.8 0 3.5-1.4 3.5-3.5S9.8 12.5 8 12.5a2 2 0 0 0-2 2c0 .7.3 1.3.5 1.5Z"/><path d="m15 8 .5.5a2 2 0 0 1 0 2.8l-3 3a2 2 0 0 1-2.8 0l-.5-.5"/><path d="m13 14-.5-.5a2 2 0 0 1 0-2.8l3-3a2 2 0 0 1 2.8 0l.5.5"/></svg>
        Research Profile
    </h3>
    @if($teacher->research_interest)
        <div class="p-5 bg-diu-primary/5 border border-diu-primary/10 rounded-2xl italic text-gray-700 text-sm">"{{ $teacher->research_interest }}"</div>
    @endif
    @if($teacher->researchProjects->isEmpty())
        <p class="text-sm text-slate-500 italic mt-4">No specific research projects registered.</p>
    @else
        <div class="space-y-4 mt-6">
            @foreach($teacher->researchProjects as $proj)
                <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                    <h4 class="font-extrabold text-gray-900 text-sm">{{ $proj->title }}</h4>
                    <p class="text-xs text-gray-500 mt-1">Funding: {{ $proj->funding_agency ?? 'N/A' }} | Role: {{ $proj->role ?? 'N/A' }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
