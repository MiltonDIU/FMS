<!-- Overview Tab -->
<div x-show="tab === 'overview'" class="space-y-6" x-cloak>
    <div>
        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-2">Biography</h3>
        <p class="text-sm text-slate-600 leading-relaxed font-sans">{{ $teacher->bio ?: (implode(', ', $teacher->researchInterestNames()) ?: 'No biography added yet.') }}</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
        <div class="bg-white p-5 rounded-xl border border-slate-200 ring-1 ring-slate-900/5">
            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><path d="M21 21v-2a4 4 0 0 0-3-3.87M9 3.13a4 4 0 0 1 0 7.75"/></svg>
                Teaching Areas
            </h4>
            @if($teacher->teachingAreas->isEmpty())
                <p class="text-xs text-slate-400">No teaching areas specified.</p>
            @else
                <ul class="space-y-2">
                    @foreach($teacher->teachingAreas as $area)
                        <li class="flex items-center justify-between text-xs text-slate-600 font-sans">
                            <span class="flex items-center gap-2"><svg class="w-3 h-3 text-diu-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>{{ $area->area }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 ring-1 ring-slate-900/5">
            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.48M12 3v18"/></svg>
                Research Interests
            </h4>
            @if(count($teacher->researchInterestNames()) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach($teacher->researchInterestNames() as $interest)
                        <span class="bg-diu-primary/10 border border-diu-primary/20 text-diu-primary text-xs font-sans px-3 py-1 rounded-full shadow-2xs">{{ $interest }}</span>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-slate-400">No research interests listed.</p>
            @endif
        </div>
    </div>

    {{-- Skills. Recorded in the admin panel since the beginning and rendered
         by no theme until Aurora, which meant a teacher could enter one and
         never see it appear on their own page. --}}
    @if($teacher->skills->isNotEmpty())
        <div class="bg-white p-5 rounded-xl border border-slate-200 ring-1 ring-slate-900/5">
            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3z"/></svg>
                Skills
            </h4>
            <div class="flex flex-wrap gap-2">
                @foreach($teacher->skills as $skill)
                    <span class="bg-diu-accent/10 border border-diu-accent/20 text-diu-accent text-xs font-sans px-3 py-1 rounded-full shadow-2xs">
                        {{ $skill->name }}@if($skill->proficiency)<span class="text-slate-400 font-semibold ml-1.5">{{ $skill->proficiency }}</span>@endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
