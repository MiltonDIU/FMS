<!-- Academic Background Tab -->
<div x-show="tab === 'academic'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        Academic Degrees &amp; Background
    </h3>
    @if($teacher->educations->isEmpty())
        <div class="p-4 rounded-2xl border border-slate-200">
            <p class="text-xs text-slate-500 font-medium">No academic degrees have been added yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($teacher->educations as $edu)
                @php
                    $degreeName = optional($edu->degreeType)->name ?? optional($edu->degreeLevel)->name ?? 'Degree';
                    $degreeTitle = $degreeName . ($edu->major ? ' in ' . $edu->major : '');
                    $institution = $edu->institution ?? optional($edu->educationalInstitution)->name ?? 'N/A';

                    $resultParts = [];
                    if ($edu->cgpa) {
                        $resultParts[] = 'CGPA: ' . $edu->cgpa . ($edu->scale ? ' / ' . $edu->scale : '');
                    }
                    if ($edu->grade) {
                        $resultParts[] = 'Grade: ' . $edu->grade;
                    }
                    if ($edu->marks) {
                        $resultParts[] = 'Marks: ' . $edu->marks . '%';
                    }
                    if (empty($resultParts) && $edu->resultType) {
                        $resultParts[] = $edu->resultType->name;
                    }
                    $resultString = implode(' | ', $resultParts);
                @endphp
                <div class="p-4 rounded-2xl border border-slate-200 ring-1 ring-slate-900/5">
                    <span class="bg-diu-primary/10 text-diu-primary text-[9px] font-sans font-black uppercase px-2 py-0.5 rounded-xs">Year: {{ $edu->passing_year ?? 'N/A' }}</span>
                    <h4 class="text-sm font-bold text-slate-800 mt-2 font-display">{{ $degreeTitle }}</h4>
                    <p class="text-xs text-slate-600 mt-0.5 font-medium">{{ $institution }}</p>
                    @if($resultString)
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-2 bg-slate-50 border border-slate-100 rounded-sm inline-block px-1.5 py-0.5">Result: {{ $resultString }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
