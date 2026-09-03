{{--
    Research — what this person works on, and what they have been funded to do.

    Interests are a list the person keeps rather than one sentence about them,
    so each gets a line of its own against a brand rule; projects follow the
    record shape used throughout the sheet.

    $projects is resolved once by profile.blade.php: researchProjects is not
    among the controller's eager loads, and reaching for it here as well would
    run the query twice on every profile.
--}}
@php
    $projects = $projects ?? $teacher->researchProjects;
@endphp

<section id="research" class="doc-section">

    <div class="doc-head">
        <h2 class="title-md">Research</h2>
        <span class="figure">{{ $teacher->researchInterests->count() + $projects->count() }}</span>
    </div>

    @if($teacher->researchInterests->isNotEmpty())
        <div class="pl-4 mt-4" style="border-left: 2px solid var(--brand-ink);">
            @foreach($teacher->researchInterests as $interest)
                <p class="text-[15.5px] leading-[1.65] {{ $loop->first ? '' : 'mt-3' }}" style="color: var(--ink-2);">
                    {{ $interest->interest }}
                    @if($interest->description)
                        <span class="block text-[13px] mt-0.5" style="color: var(--ink-3);">{{ $interest->description }}</span>
                    @endif
                </p>
            @endforeach
        </div>
    @endif

    @if($projects->isNotEmpty())
        <div class="{{ $teacher->researchInterests->isNotEmpty() ? 'mt-8' : 'mt-1' }}">
            <p class="label pb-1.5" style="border-bottom: 1px solid var(--rule);">Projects</p>

            <div class="records" style="border-top: 0;">
                {{-- Newest first. A project carries no "still running" flag the way a
                     post or a membership does, so the start date decides on its own and
                     anything undated falls to the bottom. --}}
                @php
                    $projectList = $projects->sortByDesc(
                        fn ($proj) => $proj->start_date?->format('Ymd') ?? '00000000',
                    );
                @endphp

                @foreach($projectList as $proj)
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
        </div>
    @endif
</section>
