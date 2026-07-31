{{--
    Academic background.

    Degrees are dated facts, so the year takes a column of its own and the
    degrees line up down the page — the same shape every other tab in this
    profile uses. Previously two columns of bordered cards each carrying a
    coloured "Year:" chip.
--}}
<div x-show="tab === 'academic'" x-cloak>

    @if($teacher->educations->isEmpty())
        <p class="text-[15px]" style="color: var(--text-muted);">
            No academic degrees have been added yet.
        </p>
    @else
        <div>
            @foreach($teacher->educations as $edu)
                @php
                    $degree = optional($edu->degreeType)->name ?: optional($edu->degreeLevel)->name ?: 'Degree';
                    $major = $edu->major ?: optional($edu->majorRelation)->name;
                    $institution = $edu->institution ?: optional($edu->educationalInstitution)->name;

                    // Result lives in whichever of these the record happens to carry.
                    $result = match (true) {
                        filled($edu->cgpa) && filled($edu->scale) => 'CGPA ' . $edu->cgpa . ' / ' . $edu->scale,
                        filled($edu->cgpa) => 'CGPA ' . $edu->cgpa,
                        filled($edu->grade) => 'Grade ' . $edu->grade,
                        filled($edu->marks) => $edu->marks . '%',
                        default => optional($edu->resultType)->name,
                    };
                @endphp

                <div class="record-row">
                    <span class="record-meta">{{ $edu->passing_year ?: '—' }}</span>

                    <span>
                        <span class="record-title block">
                            {{ $degree }}@if($major) <span class="font-normal" style="color: var(--text-soft);">in {{ $major }}</span>@endif
                        </span>

                        @if($institution)
                            <span class="mt-1 block text-[13px]" style="color: var(--text-soft);">{{ $institution }}</span>
                        @endif

                        @if(filled($result))
                            <span class="mt-1 block text-[12px]" style="color: var(--text-muted);">{{ $result }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
