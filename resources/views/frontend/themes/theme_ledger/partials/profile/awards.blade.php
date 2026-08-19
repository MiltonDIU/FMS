{{--
    Awards and honours, in the record shape every dated list on this sheet uses.
--}}
@php
    /*
     * year is the column that is filled; date is there for the records that
     * carry a full one instead. Resolved once, here, so the sort and the gutter
     * cannot disagree about what year an award belongs to.
     */
    $awardYear = function ($awr) {
        return $awr->year
            ?: ($awr->date ? \Illuminate\Support\Carbon::parse($awr->date)->format('Y') : null);
    };

    $awards = $teacher->awards->sortByDesc(fn ($awr) => (int) $awardYear($awr));
@endphp

<section id="awards" class="doc-section">

    <div class="doc-head">
        <h2 class="title-md">Awards</h2>
        <span class="figure">{{ $teacher->awards->count() }}</span>
    </div>

    <div class="records">
        @foreach($awards as $awr)
            @php
                // awarding_body is the column; the organisation relation is the
                // lookup-table copy used when it was left blank.
                $body = $awr->awarding_body ?: optional($awr->awardingBodyOrganizationRelation)->name;
            @endphp

            <div class="record">
                <span class="record-when">{{ $awardYear($awr) ?: '—' }}</span>

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
</section>
