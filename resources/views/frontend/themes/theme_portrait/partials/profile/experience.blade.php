{{--
    Employment history.

    Same dated rows as the rest of the profile, in place of a timeline with
    drawn dots and connecting rules.

    The period follows is_current rather than guessing from a missing end date:
    of the posts recorded, 18 have ended without one, and printing "Present" for
    those said someone still holds a job they left.
--}}
<div x-show="tab === 'experience'" x-cloak>

    @if($teacher->jobExperiences->isEmpty())
        <p class="text-[15px]" style="color: var(--text-muted);">
            No employment history submitted.
        </p>
    @else
        <div>
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

                <div class="record-row">
                    <span class="record-meta">{{ $period }}</span>

                    <span>
                        <span class="record-title block">{{ $role ?: 'Position not recorded' }}</span>

                        @if($org)
                            <span class="mt-1 block text-[13px]" style="color: var(--text-soft);">{{ $org }}</span>
                        @endif

                        @if($exp->department || $exp->location)
                            <span class="mt-0.5 block text-[12px]" style="color: var(--text-muted);">
                                {{ implode(' · ', array_filter([$exp->department, $exp->location], 'filled')) }}
                            </span>
                        @endif

                        @if($exp->responsibilities)
                            <span class="mt-1.5 block text-[13px] leading-relaxed max-w-[68ch]"
                                  style="color: var(--text-muted);">{{ $exp->responsibilities }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
