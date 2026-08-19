{{--
    Publications — the longest list on the sheet; some profiles run to hundreds.

    Every entry is rendered, none collapsed behind a "show more". This is the
    section that gains the most from a document layout: the index jumps straight
    to it, the browser's own find-in-page can see all of it, and a print or a
    PDF carries the whole bibliography rather than the first ten.

    The whole row is the link, so there is no separate "view details" control to
    aim at.
--}}
@php
    /*
     * Newest first. Anything undated sorts to 0 and lands at the bottom, where
     * the gutter shows an em dash rather than a year invented for it.
     *
     * The sort is stable in PHP 8, so within a year the relation's own
     * sort_order still decides.
     */
    $publications = $teacher->publications->sortByDesc(fn ($pub) => (int) $pub->publication_year);
@endphp

<section id="publications" class="doc-section">

    <div class="doc-head">
        <h2 class="title-md">Publications</h2>
        <span class="figure">{{ $teacher->publications->count() }}</span>
    </div>

    <div class="records">
        @foreach($publications as $pub)
            @php
                $pubUrl = ($faculty->short_name && $department->code && $teacher->webpage)
                    ? route('publication.show', [
                        'faculty_short_name' => strtolower($faculty->short_name),
                        'department_code' => strtolower($department->code),
                        'teacher_webpage' => $teacher->webpage,
                        'publication_slug' => $pub->slug ?: \Illuminate\Support\Str::slug($pub->title),
                    ])
                    : null;

                $type = optional($pub->type)->name;
                $year = $pub->publication_year ?: '—';
            @endphp

            @if($pubUrl)
                <a href="{{ $pubUrl }}" wire:navigate class="record">
                    <span class="record-when">{{ $year }}</span>
                    <span>
                        <span class="record-what block">{{ $pub->title }}</span>
                        @if($pub->journal_name)
                            <span class="record-sub block italic">{{ $pub->journal_name }}</span>
                        @endif
                        @if($type)
                            <span class="record-note block uppercase tracking-wider">{{ $type }}</span>
                        @endif
                    </span>
                </a>
            @else
                <div class="record">
                    <span class="record-when">{{ $year }}</span>
                    <span>
                        <span class="record-what block">{{ $pub->title }}</span>
                        @if($pub->journal_name)
                            <span class="record-sub block italic">{{ $pub->journal_name }}</span>
                        @endif
                        @if($type)
                            <span class="record-note block uppercase tracking-wider">{{ $type }}</span>
                        @endif
                    </span>
                </div>
            @endif
        @endforeach
    </div>
</section>
