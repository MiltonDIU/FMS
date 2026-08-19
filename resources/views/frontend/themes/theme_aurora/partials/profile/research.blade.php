{{--
    Research — what this person works on, and what they have been funded to do.

    Interests are a list the person keeps rather than one sentence about them,
    so each gets a line of its own against a brand rule; projects follow the
    dated record shape used throughout the document.

    $projects is resolved once by profile.blade.php: researchProjects is not
    among the controller's eager loads, and reaching for it here as well would
    run the query twice on every profile.
--}}
@php
    $projects = $projects ?? $teacher->researchProjects;
@endphp

<section id="research" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Research</h2>
        <span class="numeral">{{ $teacher->researchInterests->count() + $projects->count() }}</span>
    </div>

    @if($teacher->researchInterests->isNotEmpty())
        {{-- No width cap of its own: the column already carries the reading
             measure, and capping again here left this block short of the
             record rows underneath it. --}}
        <div class="pl-5" style="border-left: 2px solid var(--brand-ink);">
            @foreach($teacher->researchInterests as $interest)
                <p class="text-[16px] leading-[1.65] {{ $loop->first ? '' : 'mt-3' }}" style="color: var(--ink-2);">
                    {{ $interest->interest }}
                    @if($interest->description)
                        <span class="block text-[13.5px] mt-0.5" style="color: var(--ink-3);">{{ $interest->description }}</span>
                    @endif
                </p>
            @endforeach
        </div>
    @endif

    @if($projects->isNotEmpty())
        <div class="{{ $teacher->researchInterests->isNotEmpty() ? 'mt-9' : '' }}">
            <p class="eyebrow-quiet mb-2">Projects</p>

            @foreach($projects as $proj)
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

                <div class="record">
                    <span class="record-when">{{ $period }}</span>

                    <span>
                        <span class="record-what block">{{ $proj->title }}</span>

                        @if($facts)
                            <span class="record-sub block">{{ implode(' · ', $facts) }}</span>
                        @endif

                        @if($proj->description)
                            <span class="record-note block">{{ $proj->description }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</section>
