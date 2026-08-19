{{--
    Employment history.

    The period follows is_current rather than guessing from a missing end date.
    Of the posts on record, a number have ended without one, and printing
    "Present" for those tells a visitor someone still holds a job they left.
--}}
<section id="experience" class="doc-section">

    <div class="doc-head">
        <h2 class="title-md">Experience</h2>
        <span class="figure">{{ $teacher->jobExperiences->count() }}</span>
    </div>

    <div class="records">
        @foreach($teacher->jobExperiences as $exp)
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
