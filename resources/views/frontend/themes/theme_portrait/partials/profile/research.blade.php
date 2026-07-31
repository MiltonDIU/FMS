{{--
    Research.

    The statement is the person's own words, so it is set as a pull quote
    against a rule rather than dropped into a tinted panel. Projects follow the
    dated-row shape used across this profile.
--}}
<div x-show="tab === 'research'" x-cloak>

    @if($teacher->research_interest)
        <blockquote class="border-l-2 pl-5 max-w-[62ch]" style="border-color: var(--brand-ink);">
            <p class="text-[17px] leading-[1.65]" style="color: var(--text-base);">
                {{ $teacher->research_interest }}
            </p>
        </blockquote>
    @endif

    <section class="{{ $teacher->research_interest ? 'mt-12' : '' }}">
        <div class="flex items-baseline justify-between mb-2">
            <h3 class="eyebrow">Projects</h3>
            @if($teacher->researchProjects->isNotEmpty())
                <span class="count">{{ $teacher->researchProjects->count() }}</span>
            @endif
        </div>

        @if($teacher->researchProjects->isEmpty())
            <p class="border-t pt-3 text-[15px]"
               style="border-color: var(--border-faint); color: var(--text-muted);">
                No research projects registered.
            </p>
        @else
            @foreach($teacher->researchProjects as $proj)
                @php
                    $from = $proj->start_date ? \Illuminate\Support\Carbon::parse($proj->start_date)->format('Y') : null;
                    $to = $proj->end_date ? \Illuminate\Support\Carbon::parse($proj->end_date)->format('Y') : null;
                    $period = match (true) {
                        (bool) ($from && $to) => $from === $to ? $from : $from . '–' . $to,
                        (bool) $from => $from,
                        (bool) $to => $to,
                        default => '—',
                    };
                    $facts = array_filter([
                        $proj->role,
                        $proj->funding_agency ?: optional($proj->fundingAgencyOrganizationRelation)->name,
                        $proj->status,
                    ], 'filled');
                @endphp

                <div class="record-row">
                    <span class="record-meta">{{ $period }}</span>

                    <span>
                        <span class="record-title block">{{ $proj->title }}</span>

                        @if($facts)
                            <span class="mt-1 block text-[13px]" style="color: var(--text-soft);">
                                {{ implode(' · ', $facts) }}
                            </span>
                        @endif

                        @if($proj->description)
                            <span class="mt-1.5 block text-[13px] leading-relaxed max-w-[68ch]"
                                  style="color: var(--text-muted);">{{ $proj->description }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        @endif
    </section>
</div>
