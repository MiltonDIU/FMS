{{--
    Teaching areas.

    Each entry is two or three words, so this is a two-column list rather than
    the dated record shape used elsewhere — there is no date to line up, and a
    grid of bordered tiles with an icon in each would be more furniture than
    content.

    Named courses.blade.php to match the file every other theme uses for this,
    so the parallel between themes stays readable to whoever maintains them.
--}}
<section id="teaching" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Teaching</h2>
        <span class="numeral">{{ $teacher->teachingAreas->count() }}</span>
    </div>

    <div class="grid gap-x-10 sm:grid-cols-2">
        @foreach($teacher->teachingAreas as $area)
            <div class="py-3" style="border-top: 1px solid var(--hairline-soft);">
                <span class="text-[15px]" style="color: var(--ink);">{{ $area->area }}</span>
                @if($area->description)
                    <span class="record-note block">{{ $area->description }}</span>
                @endif
            </div>
        @endforeach
    </div>
</section>
