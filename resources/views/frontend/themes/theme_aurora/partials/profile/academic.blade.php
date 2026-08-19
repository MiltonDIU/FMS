{{--
    Education.

    Degrees are dated facts, so the year takes a column of its own and the
    degrees line up down the page — the same record shape every section of this
    document uses, which is what lets someone scan a profile top to bottom
    without relearning the layout nine times.
--}}
<section id="academic" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Education</h2>
        <span class="numeral">{{ $teacher->educations->count() }}</span>
    </div>

    <div>
        @foreach($teacher->educations as $edu)
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
                <span class="record-when">{{ $edu->passing_year ?: '—' }}</span>

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
</section>
