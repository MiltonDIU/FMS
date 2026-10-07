<!-- Area of Expertise Tab -->
{{-- The research directory's list of what this person is expert in, from the
     Directorate of Research — not the teacher's own research interests, which
     have their tab above. --}}
<div id="expertise" class="profile-section space-y-4">
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Area of Expertise
    </h3>
    <div class="p-5 bg-diu-primary/5 border border-diu-primary/10 rounded-2xl">
        <ul class="flex flex-wrap gap-2">
            @foreach($teacher->areasOfExpertise as $area)
                <li class="bg-white/70 border border-diu-primary/20 text-diu-primary text-xs font-semibold px-3 py-1 rounded-full"
                    @if($area->description) title="{{ $area->description }}" @endif>
                    {{ $area->expertise }}
                </li>
            @endforeach
        </ul>
    </div>
</div>
