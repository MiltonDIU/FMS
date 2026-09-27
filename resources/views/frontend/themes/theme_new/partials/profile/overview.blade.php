{{--
    Overview — the biography, and what this person is good at.

    Contact details are deliberately not here. They belong in the profile head,
    where they fill the space beside the portrait and are visible without
    scrolling; repeating them here would be the same three lines twice on one
    screen.

    Skills are shown here and nowhere else on the site. The profile controller
    has been eager-loading them all along and no theme has ever rendered them,
    so they cost nothing extra and say something none of the dated lists below
    do.
--}}
<section id="overview" class="doc-section">

    @php
        $bio = $teacher->bio;
    @endphp

    <h2 class="sr-only">Overview</h2>

    @if(filled($bio))
        <p class="prose-body">{{ $bio }}</p>
    @else
        <p class="text-[15px]" style="color: var(--ink-4);">
            No biography has been added yet.
        </p>
    @endif

    @if($teacher->skills->isNotEmpty())
        <div class="mt-8">
            <p class="eyebrow-quiet mb-3">Skills</p>
            <div class="flex flex-wrap gap-2">
                @foreach($teacher->skills as $skill)
                    <span class="chip" style="cursor: default;">
                        {{ $skill->name }}
                        @if(filled($skill->proficiency))
                            <span class="chip-count">{{ $skill->proficiency }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif

</section>
