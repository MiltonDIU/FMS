{{--
    Education.

    Degrees are dated facts, so they are gathered under the year they were
    finished — the same record shape and the same newest-first order every
    dated section of this document uses, which is what lets someone scan a
    profile top to bottom without relearning the layout nine times.
--}}
@php
    /*
     * Newest degree first. See publications for why sorting before the groupBy
     * is enough to order both the years and the rows inside them; a degree with
     * no passing year on file falls to the bottom under an em dash.
     */
    $years = $teacher->educations
        ->sortByDesc(fn ($edu) => (int) $edu->passing_year)
        ->groupBy(fn ($edu) => $edu->passing_year ?: '—');
@endphp

<section id="academic" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Education</h2>
        <span class="numeral">{{ $teacher->educations->count() }}</span>
    </div>

    <div class="year-list">
        @foreach($years as $year => $educations)
            <div class="year-group">
                <p class="year-mark">{{ $year }}</p>

                <div>
                    @foreach($educations as $edu)
                        @php
                            $degree = optional($edu->degreeType)->name ?: optional($edu->degreeLevel)->name ?: 'Degree';
                            $major = $edu->major ?: optional($edu->majorRelation)->name;
                            $institution = $edu->institution ?: optional($edu->educationalInstitution)->name;

                            // The result lives in whichever of these the record happens to
                            // carry; older imports filled only one of them.
                            $result = match (true) {
                                filled($edu->cgpa) && filled($edu->scale) => 'CGPA ' . $edu->cgpa . ' / ' . $edu->scale,
                                filled($edu->cgpa) => 'CGPA ' . $edu->cgpa,
                                filled($edu->grade) => 'Grade ' . $edu->grade,
                                filled($edu->marks) => $edu->marks . '%',
                                default => optional($edu->resultType)->name,
                            };
                        @endphp

                        <div class="record">
                            <span>
                                <span class="record-what block">
                                    {{ $degree }}@if($major) <span class="font-normal" style="color: var(--ink-3);">in {{ $major }}</span>@endif
                                </span>

                                @if($institution)
                                    <span class="record-sub block">{{ $institution }}</span>
                                @endif

                                @if(filled($result))
                                    <span class="record-note block">{{ $result }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
