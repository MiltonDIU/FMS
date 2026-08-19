{{--
    Training, and the certifications that came out of it.

    Certifications are shown here and nowhere else on the site. The profile
    controller has been eager-loading them all along without any theme
    rendering them, which meant a teacher could record a credential in the
    admin panel and never see it appear on their own page.
--}}
<section id="training" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Training</h2>
        <span class="numeral">{{ $teacher->trainingExperiences->count() + $teacher->certifications->count() }}</span>
    </div>

    {{-- Either half can be empty: the section is on the page when there is at
         least one of the two, which is the count the rail shows. --}}
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

            <div class="record">
                <span class="record-when">{{ $trn->year ?: '—' }}</span>

                <span>
                    <span class="record-what block">{{ $trn->title }}</span>

                    @if($org)
                        <span class="record-sub block">{{ $org }}</span>
                    @endif

                    @if($facts)
                        <span class="record-note block">{{ implode(' · ', $facts) }}</span>
                    @endif

                    @if($trn->description)
                        <span class="record-note block">{{ $trn->description }}</span>
                    @endif
                </span>
            </div>
        @endforeach
    </div>

    @if($teacher->certifications->isNotEmpty())
        <div class="{{ $teacher->trainingExperiences->isNotEmpty() ? 'mt-9' : '' }}">
            <p class="eyebrow-quiet mb-2">Certifications</p>

            @foreach($teacher->certifications as $cert)
                @php
                    $issued = $cert->issue_date ? \Illuminate\Support\Carbon::parse($cert->issue_date)->format('Y') : null;

                    // The column, not the organisation relation the CV builder
                    // reaches for: issuing_authority is not nullable, and the
                    // profile controller does not eager-load that relation, so
                    // asking for it here would be a query per certification for
                    // a fallback that can never fire.
                    $issuer = $cert->issuing_authority;
                @endphp

                <div class="record">
                    <span class="record-when">{{ $issued ?: '—' }}</span>

                    <span>
                        <span class="record-what block">{{ $cert->title }}</span>

                        @if($issuer)
                            <span class="record-sub block">{{ $issuer }}</span>
                        @endif

                        @php
                            $certFacts = array_filter([
                                $cert->type,
                                $cert->credential_id ? 'ID ' . $cert->credential_id : null,
                                $cert->expiry_date
                                    ? 'Expires ' . \Illuminate\Support\Carbon::parse($cert->expiry_date)->format('M Y')
                                    : null,
                            ], 'filled');
                        @endphp

                        @if($certFacts)
                            <span class="record-note block">{{ implode(' · ', $certFacts) }}</span>
                        @endif

                        @if($cert->credential_url)
                            <a href="{{ $cert->credential_url }}" target="_blank" rel="noopener noreferrer"
                               class="link-brand inline-block mt-1 text-[12px]">Verify credential &rarr;</a>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</section>
