{{--
    Education.

    Degrees are dated facts, so they take the record shape every dated list on
    this sheet takes: the year hangs in the gutter and the degree sits beside
    it, newest first. A degree with no passing year on file falls to the bottom
    under an em dash rather than being dropped or dated by guesswork.
--}}
@php
    $educations = $teacher->educations->sortByDesc(fn ($edu) => (int) $edu->passing_year);
@endphp

<section id="academic" class="doc-section">

    <div class="doc-head">
        <h2 class="title-md">Education</h2>
        <span class="figure">{{ $teacher->educations->count() }}</span>
    </div>

    <div class="records">
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
