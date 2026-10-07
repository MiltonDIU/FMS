{{--
    Area of Expertise — the research directory's list of what this person is
    expert in, from the Directorate of Research. Kept apart from Research
    Interest, which is the teacher's own; each area gets a line of its own
    against the brand rule, the same as interests do.
--}}
<section id="expertise" class="doc-section">

    <div class="doc-head">
        <h2 class="title-md">Area of Expertise</h2>
        <span class="figure">{{ $teacher->areasOfExpertise->count() }}</span>
    </div>

    <div class="pl-4 mt-4" style="border-left: 2px solid var(--brand-ink);">
        @foreach($teacher->areasOfExpertise as $area)
            <p class="text-[15.5px] leading-[1.65] {{ $loop->first ? '' : 'mt-3' }}" style="color: var(--ink-2);">
                {{ $area->expertise }}
                @if($area->description)
                    <span class="block text-[13px] mt-0.5" style="color: var(--ink-3);">{{ $area->description }}</span>
                @endif
            </p>
        @endforeach
    </div>
</section>
