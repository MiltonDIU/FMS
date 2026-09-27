{{--
    Publications — the longest list on the page; some profiles run to hundreds.

    Every entry is rendered, none collapsed behind a "show more". This is the
    section that gains the most from the document layout: the rail jumps
    straight to it, the browser's own find-in-page can see all of it, and a
    print or a PDF carries the whole bibliography rather than the first ten.

    The whole row is the link, so there is no separate "view details" control
    to aim at.
--}}
@php
    /*
     * Gathered under their year, newest first.
     *
     * Sorted before it is grouped, on purpose: groupBy keeps the order the keys
     * are first seen in, so one sortByDesc puts both the years and the papers
     * inside each year in the right order. Anything undated falls to 0 and
     * lands at the bottom, under an em dash.
     *
     * The sort is stable in PHP 8, so within a year the relation's own
     * sort_order still decides.
     */
    $years = $teacher->publications
        ->sortByDesc(fn ($pub) => (int) $pub->publication_year)
        ->groupBy(fn ($pub) => $pub->publication_year ?: '—');
@endphp

<section id="publications" class="doc-section">

    <div class="flex items-baseline justify-between mb-3">
        <h2 class="display-md">Publications</h2>
        <span class="numeral">{{ $teacher->publications->count() }}</span>
    </div>

    <div class="year-list">
        @foreach($years as $year => $publications)
            <div class="year-group">
                <p class="year-mark">{{ $year }}</p>

                <div>
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
                        @endphp

                        @if($pubUrl)
                            <a href="{{ $pubUrl }}" wire:navigate class="record">
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
            </div>
        @endforeach
    </div>
</section>
