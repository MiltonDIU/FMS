<!-- Research Tab -->
<div x-show="tab === 'research'" class="space-y-4" x-cloak>
    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.5c-1.8 0-3.5 1.4-3.5 3.5s1.7 3.5 3.5 3.5 3.5-1.4 3.5-3.5a2 2 0 0 0-.063-.5"/><path d="M10 10a2 2 0 0 0 2-2c0-1-.9-1.9-2-2s-2 .9-2 2a2 2 0 0 0 2 2Z"/><path d="M4.5 11h.5a2 2 0 0 0 2-2c0-1-.9-1.9-2-2s-2 .9-2 2a2 2 0 0 0 2 2Z"/><path d="M6 17.5a2 2 0 0 0 2 2c1.8 0 3.5-1.4 3.5-3.5S9.8 12.5 8 12.5a2 2 0 0 0-2 2c0 .7.3 1.3.5 1.5Z"/><path d="m15 8 .5.5a2 2 0 0 1 0 2.8l-3 3a2 2 0 0 1-2.8 0l-.5-.5"/><path d="m13 14-.5-.5a2 2 0 0 1 0-2.8l3-3a2 2 0 0 1 2.8 0l.5.5"/></svg>
        Research Profile
    </h3>
    {{-- A list now rather than one sentence, so an interest can carry a note
         of what it covers and the order is the teacher's own. --}}
    @if($teacher->researchInterests->isNotEmpty())
        <div class="p-5 bg-diu-primary/5 border border-diu-primary/10 rounded-2xl">
            <ul class="flex flex-wrap gap-2">
                @foreach($teacher->researchInterests as $interest)
                    <li class="bg-white/70 border border-diu-primary/20 text-diu-primary text-xs font-semibold px-3 py-1 rounded-full"
                        @if($interest->description) title="{{ $interest->description }}" @endif>
                        {{ $interest->interest }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
    @if($teacher->researchProjects->isEmpty())
        <p class="text-sm text-slate-500 italic mt-4">No specific research projects registered.</p>
    @else
        <div class="space-y-4 mt-6">
            {{-- Newest first. A project carries no "still running" flag the way a
                 post or a membership does, so the start date decides on its own and
                 anything undated falls to the bottom. --}}
            @php
                $projectList = $teacher->researchProjects->sortByDesc(
                    fn ($proj) => $proj->start_date?->format('Ymd') ?? '00000000',
                );
            @endphp

            @foreach($projectList as $proj)
                @php
                    $from = $proj->start_date?->format('Y');
                    $to = $proj->end_date?->format('Y');

                    $period = match (true) {
                        (bool) ($from && $to) => $from === $to ? $from : $from . '–' . $to,
                        (bool) $from => $from,
                        (bool) $to => $to,
                        default => null,
                    };

                    // The funding agency is free text on older records and a
                    // relation on newer ones; printing "N/A" for the second kind
                    // said a funded project had no funder.
                    $facts = array_filter([
                        $proj->role,
                        $proj->funding_agency ?: optional($proj->fundingAgencyOrganizationRelation)->name,
                        $proj->status,
                    ], 'filled');
                @endphp

                <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                    @if($period)
                        <p class="text-[10px] font-sans font-black text-diu-primary tracking-wider tabular-nums">{{ $period }}</p>
                    @endif
                    <h4 class="font-extrabold text-gray-900 text-sm mt-0.5">{{ $proj->title }}</h4>
                    @if($facts)
                        <p class="text-xs text-gray-500 mt-1">{{ implode(' · ', $facts) }}</p>
                    @endif
                    @if($proj->description)
                        <p class="text-[11px] text-gray-500 mt-1 leading-relaxed">{{ $proj->description }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
