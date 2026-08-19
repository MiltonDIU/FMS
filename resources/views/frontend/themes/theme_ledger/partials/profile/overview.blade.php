{{--
    Overview — the biography, and what this person is good at.

    Contact details are deliberately not here. They are in the stub column, in
    the ruled block beside this one, and repeating them would be the same four
    lines twice on one screen.

    Skills are shown here and nowhere else on the site. The profile controller
    has been eager-loading them all along and no theme has ever rendered them,
    so they cost nothing extra and say something none of the dated lists below
    do.
--}}
<section id="overview" class="doc-section">

    @php $bio = $teacher->bio; @endphp

    <h2 class="sr-only">Overview</h2>

    @if(filled($bio))
        <p class="prose-body">{{ $bio }}</p>
    @else
        <p class="text-[15px]" style="color: var(--ink-4);">No biography has been added yet.</p>
    @endif

    @if($teacher->skills->isNotEmpty())
        <div class="mt-7">
            <p class="label pb-1.5 mb-2.5" style="border-bottom: 1px solid var(--rule);">Skills</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($teacher->skills as $skill)
                    <span class="pick" style="cursor: default;">
                        {{ $skill->name }}
                        @if(filled($skill->proficiency))
                            <span class="font-mono" style="color: var(--ink-4);">{{ $skill->proficiency }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif

</section>
