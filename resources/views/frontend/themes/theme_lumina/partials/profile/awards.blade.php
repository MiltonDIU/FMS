{{--
    Awards and honours, gathered under their year like every other dated list
    in this document.
--}}
@php
    /*
     * year is the column that is filled; date is there for the records that
     * carry a full one instead. Resolved once, here, so the sort and the
     * heading cannot disagree about what year an award belongs to.
     *
     * See publications for why one sortByDesc before the groupBy is enough to
     * order both the years and the rows inside them.
     */
    $awardYear = function ($awr) {
        return $awr->year
            ?: ($awr->date ? \Illuminate\Support\Carbon::parse($awr->date)->format('Y') : null);
    };

    $years = $teacher->awards
        ->sortByDesc(fn ($awr) => (int) $awardYear($awr))
        ->groupBy(fn ($awr) => $awardYear($awr) ?: '—');
@endphp

<section id="awards" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Awards</h2>
        <span class="numeral">{{ $teacher->awards->count() }}</span>
    </div>

    <div class="year-list">
        @foreach($years as $year => $awards)
            <div class="year-group">
                <p class="year-mark">{{ $year }}</p>

                <div>
                    @foreach($awards as $awr)
                        @php
                            // awarding_body is the column; the organisation relation is the
                            // lookup-table copy used when it was left blank.
                            $body = $awr->awarding_body ?: optional($awr->awardingBodyOrganizationRelation)->name;
                        @endphp

                        <div class="record">
                            <span>
                                <span class="record-what block">{{ $awr->title }}</span>

                                @if($body)
                                    <span class="record-sub block">{{ $body }}</span>
                                @endif

                                @if($awr->type)
                                    <span class="record-note block uppercase tracking-wider">{{ $awr->type }}</span>
                                @endif

                                @if($awr->remarks)
                                    <span class="record-note block">{{ $awr->remarks }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
