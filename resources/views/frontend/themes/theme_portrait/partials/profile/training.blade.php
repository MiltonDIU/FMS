{{--
    Training.

    Dated rows again. The icon tile that opened every entry carried no
    information the heading did not already give.
--}}
<div x-show="tab === 'training'" x-cloak>

    @if($teacher->trainingExperiences->isEmpty())
        <p class="text-[15px]" style="color: var(--text-muted);">
            No training recorded.
        </p>
    @else
        <div>
            @foreach($teacher->trainingExperiences as $trn)
                @php
                    $org = $trn->organization ?: optional($trn->organizationRelation)->name;
                    $facts = array_filter([
                        $trn->category,
                        $trn->duration_days ? $trn->duration_days . ' days' : null,
                        $trn->is_online ? 'Online' : null,
                        $trn->country,
                    ], 'filled');
                @endphp

                <div class="record-row">
                    <span class="record-meta">{{ $trn->year ?: '—' }}</span>

                    <span>
                        <span class="record-title block">{{ $trn->title }}</span>

                        @if($org)
                            <span class="mt-1 block text-[13px]" style="color: var(--text-soft);">{{ $org }}</span>
                        @endif

                        @if($facts)
                            <span class="mt-1 block text-[12px]" style="color: var(--text-muted);">
                                {{ implode(' · ', $facts) }}
                            </span>
                        @endif

                        @if($trn->description)
                            <span class="mt-1.5 block text-[13px] leading-relaxed max-w-[68ch]"
                                  style="color: var(--text-muted);">{{ $trn->description }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
