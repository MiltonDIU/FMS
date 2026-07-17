<!-- Publications Tab -->
<div x-show="tab === 'publications'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
        List of Scholarly Papers
    </h3>
    @if($teacher->publications->isEmpty())
        <div class="text-center py-12 border-2 border-dashed border-slate-200 rounded-2xl bg-white/10">
            <svg class="w-10 h-10 text-slate-400 mx-auto mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
            <p class="text-sm text-slate-500 font-sans font-medium">No publications added yet for this teacher.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($teacher->publications as $pub)
                @php
                    $pubUrl = ($faculty->short_name && $teacher->webpage)
                        ? route('publication.show', ['faculty_short_name' => strtolower($faculty->short_name), 'department_code' => strtolower($department->code), 'teacher_webpage' => $teacher->webpage, 'publication_slug' => $pub->slug ?: \Illuminate\Support\Str::slug($pub->title)])
                        : '#';
                @endphp
                <div class="p-4 rounded-2xl border border-slate-200 hover:border-diu-primary/40 shadow-2xs hover:shadow-xs transition-all flex items-start gap-4">
                    <div class="bg-diu-primary/10 text-diu-primary p-2.5 rounded-xl shrink-0 mt-0.5">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[9px] font-sans font-bold px-1.5 py-0.5 rounded-xs {{ stripos($pub->type?->name ?? '', 'journal') !== false ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-indigo-50 text-indigo-700 border border-indigo-100' }}">
                                {{ $pub->type?->name ?? 'Research Paper' }}
                            </span>
                            <span class="text-[10px] text-slate-400 font-semibold font-sans">{{ $pub->publication_year ?? 'N/A' }}</span>
                        </div>
                        <h4 class="text-sm font-semibold text-slate-800 tracking-tight leading-snug group-hover:text-diu-primary transition-colors">{{ $pub->title }}</h4>
                        <p class="text-xs text-slate-500 mt-1 italic font-sans">{{ $pub->journal_name ?? '' }}</p>
                        <div class="flex items-center space-x-4 mt-4">
                            <a href="{{ $pubUrl }}" class="inline-flex items-center text-xs font-bold text-diu-primary hover:underline">
                                <span>View Details</span><span class="ml-1.5">→</span>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
