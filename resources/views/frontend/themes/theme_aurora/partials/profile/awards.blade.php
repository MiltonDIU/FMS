{{--
    Awards and honours. Dated rows, like everything else in this document.
--}}
<section id="awards" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Awards</h2>
        <span class="numeral">{{ $teacher->awards->count() }}</span>
    </div>

    <div>
        @foreach($teacher->awards as $awr)
            @php
                // awarding_body is the column; the organisation relation is the
                // lookup-table copy used when it was left blank.
                $body = $awr->awarding_body ?: optional($awr->awardingBodyOrganizationRelation)->name;
                $year = $awr->year ?: ($awr->date ? \Illuminate\Support\Carbon::parse($awr->date)->format('Y') : null);
            @endphp

            <div class="record">
                <span class="record-when">{{ $year ?: '—' }}</span>

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
