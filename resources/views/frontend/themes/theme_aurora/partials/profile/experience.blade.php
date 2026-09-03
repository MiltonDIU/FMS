{{--
    Employment history.

    The period follows is_current rather than guessing from a missing end date.
    Of the posts on record, a number have ended without one, and printing
    "Present" for those tells a visitor someone still holds a job they left.
--}}
<section id="experience" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Experience</h2>
        <span class="numeral">{{ $teacher->jobExperiences->count() }}</span>
    </div>

    <div>
        {{-- Newest first, with posts still held at the top.

             The relation is ordered by sort_order and every job_experiences row
             carries 0, so the list came out in insertion order — two thirds of the
             profiles with more than one dated post read out of sequence. Every other
             dated section here sorts its own years; this one never did.

             One sortable string rather than a multi-key sort: a flag for a post still
             held, then the start date, then zeros for anything undated so it falls to
             the bottom of its group rather than the top. --}}
        @php
            $experiences = $teacher->jobExperiences->sortByDesc(
                fn ($exp) => ($exp->is_current ? '1' : '0') . ($exp->start_date?->format('Ymd') ?? '00000000'),
            );
        @endphp

        @foreach($experiences as $exp)
            @php
                $role = $exp->position ?: optional($exp->positionRelation)->name;
                $org = $exp->organization ?: optional($exp->organizationRelation)->name;

                $from = $exp->start_date ? \Illuminate\Support\Carbon::parse($exp->start_date)->format('Y') : null;
                $to = $exp->end_date ? \Illuminate\Support\Carbon::parse($exp->end_date)->format('Y') : null;

                $period = match (true) {
                    $exp->is_current && $from => $from . '–now',
                    (bool) $exp->is_current => 'Current',
                    (bool) ($from && $to) => $from === $to ? $from : $from . '–' . $to,
                    (bool) $from => $from,
                    (bool) $to => 'to ' . $to,
                    default => '—',
                };
            @endphp

            <div class="record">
                <span class="record-when">{{ $period }}</span>

                <span>
                    <span class="record-what block">{{ $role ?: 'Position not recorded' }}</span>

                    @if($org)
                        <span class="record-sub block">{{ $org }}</span>
                    @endif

                    @if($exp->department || $exp->location)
                        <span class="record-note block">
                            {{ implode(' · ', array_filter([$exp->department, $exp->location], 'filled')) }}
                        </span>
                    @endif

                    @if($exp->responsibilities)
                        <span class="record-note block">{{ $exp->responsibilities }}</span>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
</section>
