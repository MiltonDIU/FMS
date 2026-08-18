{{--
    Publications, grouped by the year they were published.

    A flat list of 228 papers is a wall — the busiest profile here spans 21
    years, and the thing a reader wants first is the recent work. Newest year at
    the top, each year its own block with a count, so the shape of somebody's
    output is legible before a single title is read.

    The papers with no year recorded go last under their own heading rather than
    sorting as year zero. There are 507 of them across the system, and floating
    them to the top under a blank label would bury the recent work they were
    grouped to surface.
--}}
@php
    $grouped = $teacher->publications
        ->groupBy(fn ($pub) => $pub->publication_year ?: '')
        // Descending, and the undated group forced to the end: '' sorts below
        // every year on a string comparison, which is the wrong end.
        ->sortKeysDesc()
        ->sortBy(fn ($papers, $year) => $year === '' ? 1 : 0, SORT_REGULAR, false);

    // Only the groups that name a year. "Year not recorded" is a group but it
    // is not a year, and counting it said "2 years" for a profile holding one.
    $yearCount = $grouped->keys()->filter(fn ($year) => $year !== '')->count();
@endphp

<!-- Publications Tab -->
<div x-show="tab === 'publications'" class="space-y-6" x-cloak>
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
            <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
            List of Scholarly Papers
        </h3>

        @if($teacher->publications->isNotEmpty())
            <span class="text-[11px] font-sans text-slate-500">
                {{ $teacher->publications->count() }} {{ \Illuminate\Support\Str::plural('paper', $teacher->publications->count()) }}
                @if($yearCount > 1)
                    · {{ $yearCount }} {{ \Illuminate\Support\Str::plural('year', $yearCount) }}
                @endif
            </span>
        @endif
    </div>

    @if($teacher->publications->isEmpty())
        <div class="text-center py-12 border-2 border-dashed border-white/60 rounded-xl bg-white/10">
            <svg class="w-10 h-10 text-slate-400 mx-auto mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
            <p class="text-sm text-slate-500 font-sans font-medium">No publications added yet for this teacher.</p>
        </div>
    @else
        @foreach($grouped as $year => $papers)
            <section>
                {{-- The year, then a hairline running out to the count: it reads
                     as one band across the column, which is what separates the
                     groups without another box inside a box. --}}
                <div class="flex items-center gap-3 mb-3">
                    <span class="text-xs font-sans font-extrabold tracking-wide px-2.5 py-1 rounded-lg
                        {{ $year === '' ? 'bg-slate-100 text-slate-500 border border-slate-200' : 'bg-diu-primary/10 text-diu-primary border border-diu-primary/20' }}">
                        {{ $year === '' ? 'Year not recorded' : $year }}
                    </span>
                    <span class="h-px flex-1 bg-slate-200/70"></span>
                    <span class="text-[10px] font-sans font-semibold text-slate-400 shrink-0">
                        {{ $papers->count() }} {{ \Illuminate\Support\Str::plural('paper', $papers->count()) }}
                    </span>
                </div>

                {{-- Indented under the year on a rule, so a long group still
                     reads as belonging to the heading above it. --}}
                <div class="space-y-3 sm:pl-4 sm:border-l sm:border-slate-200/70">
                    @foreach($papers as $pub)
                        @php
                            $pubUrl = ($faculty->short_name && $teacher->webpage)
                                ? route('publication.show', ['faculty_short_name' => strtolower($faculty->short_name), 'department_code' => strtolower($department->code), 'teacher_webpage' => $teacher->webpage, 'publication_slug' => $pub->slug ?: \Illuminate\Support\Str::slug($pub->title)])
                                : '#';
                        @endphp
                        <div class="p-4 rounded-xl border border-white/60 hover:border-diu-primary/40 bg-white/30 backdrop-blur-xs shadow-3xs hover:shadow-xs transition-all flex items-start gap-4">
                            <div class="bg-diu-primary/10 text-diu-primary p-2.5 rounded-lg shrink-0 mt-0.5">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                {{-- The year badge that used to sit here is gone: the
                                     heading above already says it, on every card. --}}
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <span class="text-[9px] font-sans font-bold px-1.5 py-0.5 rounded-xs {{ stripos($pub->type?->name ?? '', 'journal') !== false ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-indigo-50 text-indigo-700 border border-indigo-100' }}">
                                        {{ $pub->type?->name ?? 'Research Paper' }}
                                    </span>
                                    @if($pub->publication_linkage_id && $pub->linkage?->name)
                                        <span class="text-[9px] font-sans font-bold px-1.5 py-0.5 rounded-xs bg-slate-50 text-slate-600 border border-slate-200">
                                            {{ $pub->linkage->name }}
                                        </span>
                                    @endif
                                </div>
                                <h4 class="text-sm font-semibold text-slate-800 tracking-tight leading-snug group-hover:text-diu-primary transition-colors">{{ $pub->title }}</h4>
                                <p class="text-xs text-slate-500 mt-1 italic font-sans">{{ $pub->journal_name ?? '' }}</p>
                                <div class="flex items-center space-x-4 mt-4">
                                    <a href="{{ $pubUrl }}" wire:navigate class="inline-flex items-center text-xs font-bold text-diu-primary hover:underline">
                                        <span>View Details</span><span class="ml-1.5">→</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
