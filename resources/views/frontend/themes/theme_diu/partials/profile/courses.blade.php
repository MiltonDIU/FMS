<!-- Courses Tab -->
<div x-show="tab === 'courses'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        Courses Assigned
    </h3>
    @if($teacher->teachingAreas->isEmpty())
        <p class="text-sm text-slate-500 italic">No assigned teaching courses found.</p>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($teacher->teachingAreas as $area)
                <div class="bg-slate-50 border border-gray-100 p-4 rounded-2xl flex items-center space-x-3">
                    <span class="text-diu-primary text-lg"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></span>
                    <div><h4 class="font-bold text-gray-900 text-sm">{{ $area->name }}</h4><p class="text-[10px] text-gray-500 uppercase tracking-wider font-semibold">Active Curriculum</p></div>
                </div>
            @endforeach
        </div>
    @endif
</div>
