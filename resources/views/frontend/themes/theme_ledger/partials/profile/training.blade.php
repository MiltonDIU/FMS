{{--
    Training, and the certifications that came out of it.

    Certifications are shown here and nowhere else on the site. The profile
    controller has been eager-loading them all along without any theme rendering
    them, which meant a teacher could record a credential in the admin panel and
    never see it appear on their own page.
--}}
@php
    /*
     * Both halves are newest first. Training carries a plain year column; a
     * certification carries the date it was issued, so that one is reduced to
     * its year to sit in the same gutter.
     */
    $sessions = $teacher->trainingExperiences->sortByDesc(fn ($trn) => (int) $trn->year);

    $certYear = fn ($cert) => $cert->issue_date
        ? \Illuminate\Support\Carbon::parse($cert->issue_date)->format('Y')
        : null;

    $certifications = $teacher->certifications->sortByDesc(fn ($cert) => (int) $certYear($cert));
@endphp

<section id="training" class="doc-section">

    <div class="doc-head">
        <h2 class="title-md">Training</h2>
        <span class="figure">{{ $teacher->trainingExperiences->count() + $teacher->certifications->count() }}</span>
    </div>

    {{-- Either half can be empty: the section is on the page when there is at
         least one of the two, which is the count the index shows. --}}
    @if($sessions->isNotEmpty())
        <div class="records">
            @foreach($sessions as $trn)
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
    @endif

    @if($certifications->isNotEmpty())
        <div class="{{ $sessions->isNotEmpty() ? 'mt-8' : 'mt-1' }}">
            <p class="label pb-1.5" style="border-bottom: 1px solid var(--rule);">Certifications</p>

            <div class="records" style="border-top: 0;">
                @foreach($certifications as $cert)
                    @php
                        // The column, not the organisation relation the CV builder
                        // reaches for: issuing_authority is not nullable, and the
                        // profile controller does not eager-load that relation, so
                        // asking for it here would be a query per certification for
                        // a fallback that can never fire.
                        $issuer = $cert->issuing_authority;

                        $certFacts = array_filter([
                            $cert->type,
                            $cert->credential_id ? 'ID ' . $cert->credential_id : null,
                            $cert->expiry_date
                                ? 'Expires ' . \Illuminate\Support\Carbon::parse($cert->expiry_date)->format('M Y')
                                : null,
                        ], 'filled');
                    @endphp

                    <div class="record">
                        <span class="record-when">{{ $certYear($cert) ?: '—' }}</span>

                        <span>
                            <span class="record-what block">{{ $cert->title }}</span>

                            @if($issuer)
                                <span class="record-sub block">{{ $issuer }}</span>
                            @endif

                            @if($certFacts)
                                <span class="record-note block">{{ implode(' · ', $certFacts) }}</span>
                            @endif

                            @if($cert->credential_url)
                                <a href="{{ $cert->credential_url }}" target="_blank" rel="noopener noreferrer"
                                   class="link inline-block mt-1 text-[12px]">Verify credential &rarr;</a>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
