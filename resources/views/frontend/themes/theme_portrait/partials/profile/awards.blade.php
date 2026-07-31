{{--
    Awards and honours.

    Dated rows. The tinted panels with a coloured type badge in each made a
    handful of lines look like a dashboard.
--}}
<div x-show="tab === 'awards'" x-cloak>

    @if($teacher->awards->isEmpty())
        <p class="text-[15px]" style="color: var(--text-muted);">
            No awards documented.
        </p>
    @else
        <div>
            @foreach($teacher->awards as $awr)
                @php
                    // The column is awarding_body; the organisation relation is
                    // the lookup-table copy used when it is blank.
                    $body = $awr->awarding_body ?: optional($awr->awardingBodyOrganizationRelation)->name;
                    $year = $awr->year ?: ($awr->date ? \Illuminate\Support\Carbon::parse($awr->date)->format('Y') : null);
                @endphp

                <div class="record-row">
                    <span class="record-meta">{{ $year ?: '—' }}</span>

                    <span>
                        <span class="record-title block">{{ $awr->title }}</span>

                        @if($body)
                            <span class="mt-1 block text-[13px]" style="color: var(--text-soft);">{{ $body }}</span>
                        @endif

                        @if($awr->type)
                            <span class="mt-1 block text-[11px] uppercase tracking-wider"
                                  style="color: var(--text-muted);">{{ $awr->type }}</span>
                        @endif

                        @if($awr->remarks)
                            <span class="mt-1.5 block text-[13px] leading-relaxed max-w-[68ch]"
                                  style="color: var(--text-muted);">{{ $awr->remarks }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
