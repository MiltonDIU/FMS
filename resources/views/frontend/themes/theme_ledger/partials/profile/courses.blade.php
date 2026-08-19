{{--
    Teaching areas.

    Each entry is two or three words and none of them is dated, so this is the
    one section that does not spend the year gutter: a column of empty gutters
    beside a list of short phrases is alignment with nothing to align. Two
    columns of ruled lines instead.

    Named courses.blade.php to match the file every other theme uses for this,
    so the parallel between themes stays readable to whoever maintains them.
--}}
<section id="teaching" class="doc-section">

    <div class="doc-head">
        <h2 class="title-md">Teaching</h2>
        <span class="figure">{{ $teacher->teachingAreas->count() }}</span>
    </div>

    <div class="grid gap-x-10 sm:grid-cols-2">
        @foreach($teacher->teachingAreas as $area)
            <div class="py-2.5" style="border-bottom: 1px solid var(--rule-soft);">
                <span class="text-[14.5px]" style="color: var(--ink);">{{ $area->area }}</span>
                @if($area->description)
                    <span class="record-note block">{{ $area->description }}</span>
                @endif
            </div>
        @endforeach
    </div>
</section>
