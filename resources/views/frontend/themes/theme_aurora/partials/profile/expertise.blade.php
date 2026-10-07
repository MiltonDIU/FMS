{{--
    Area of Expertise — the research directory's list of what this person is
    expert in, from the Directorate of Research. Kept apart from Research
    Interest, which is the teacher's own; each area gets a line of its own
    against the brand rule, the same as interests do.
--}}
<section id="expertise" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Area of Expertise</h2>
        <span class="numeral">{{ $teacher->areasOfExpertise->count() }}</span>
    </div>

    <div class="pl-5" style="border-left: 2px solid var(--brand-ink);">
        @foreach($teacher->areasOfExpertise as $area)
            <p class="text-[16px] leading-[1.65] {{ $loop->first ? '' : 'mt-3' }}" style="color: var(--ink-2);">
                {{ $area->expertise }}
                @if($area->description)
                    <span class="block text-[13.5px] mt-0.5" style="color: var(--ink-3);">{{ $area->description }}</span>
                @endif
            </p>
        @endforeach
    </div>
</section>
