@extends('frontend.themes.theme_aurora.layouts.app')

{{-- Sharing and structured data. ScholarlyArticle, because this is the one page
     on the site that is a citation target. --}}
@php
    $seo = \App\Helpers\SeoPayload::forPublication($publication, $authors, $faculty, $department);
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_aurora.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    @php
        // $authors and $citations come from the controller.
        $venue = $publication->journal_name ?? '';

        // Null-safe URLs: faculties.short_name and teachers.webpage are both
        // nullable columns, and a broken route() throws rather than degrading.
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;
        $deptSlug = $department->code ? strtolower($department->code) : null;

        $deptUrl = ($facSlug && $deptSlug)
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => $deptSlug])
            : route('home');

        $teacherUrl = ($facSlug && $deptSlug && $teacher->webpage)
            ? route('teacher.show', [
                'faculty_short_name' => $facSlug,
                'department_code' => $deptSlug,
                'teacher_webpage' => $teacher->webpage,
            ])
            : route('home');

        // Aurora shows a whole profile at once, so the link back to someone's
        // publications is an anchor into the document rather than a ?tab=.
        $publicationsUrl = $teacherUrl === route('home') ? $teacherUrl : $teacherUrl . '#publications';

        // Only the facts this paper actually carries. An empty "N/A" row is
        // noise, so the list is built first and rendered only if it has any.
        $facts = array_filter([
            'Journal / Conference' => $venue,
            'Year'                 => $publication->publication_year,
            'Type'                 => optional($publication->type)->name,
            'Research area'        => $publication->research_area,
            'Impact factor'        => $publication->impact_factor,
            'CiteScore'            => $publication->citescore,
            'H-index'              => $publication->h_index,
            'Recorded by'          => $publication->created_by_name,
        ], fn ($value) => filled($value));

        $citationStyles = [
            ['key' => 'apa', 'label' => 'APA', 'mono' => false],
            ['key' => 'ieee', 'label' => 'IEEE', 'mono' => false],
            ['key' => 'bibtex', 'label' => 'BibTeX', 'mono' => true],
        ];
    @endphp

    <nav class="text-[13px] mb-6 flex flex-wrap items-center gap-2" style="color: var(--ink-4);">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span style="color: var(--hairline-strong);">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span style="color: var(--hairline-strong);">/</span>
        <a href="{{ $deptUrl }}" wire:navigate class="hover:underline">{{ $department->code }}</a>
        <span style="color: var(--hairline-strong);">/</span>
        <a href="{{ $teacherUrl }}" wire:navigate class="hover:underline">{{ $teacher->full_name }}</a>
        <span style="color: var(--hairline-strong);">/</span>
        <span style="color: var(--ink-2);">Publication</span>
    </nav>

    {{-- Two columns, because this page has two jobs.

         The left is the paper: its title, what it says, who wrote it, what else
         the author has written. The right is what someone came here to take
         away — whose work it is, the record, and the three citation formats —
         and it stays with you as the abstract scrolls.

         The title lives inside the left column rather than above both. Sitting
         above, it was capped to the article's width so its right edge would
         line up with the abstract, which left a dead rectangle beside it: space
         held open for a sidebar that did not start until further down the page.
         With the title in the column, the sidebar begins at the top and fills
         that space itself, and the alignment comes free from the grid instead
         of from a width calculated to match it. --}}
    <div class="article-layout">

        <article class="min-w-0">

            {{-- The paper's own title is the loudest thing on the page; the
                 aurora behind it does the work a coloured banner would have
                 done. --}}
            <header class="mb-10">
                <p class="eyebrow mb-3">
                    {{ optional($publication->type)->name ?? 'Research' }}
                    @if($publication->publication_year)
                        <span class="mx-1.5" style="color: var(--hairline-strong);">·</span>{{ $publication->publication_year }}
                    @endif
                </p>

                {{-- display-fill, not the balanced default: a paper's title is a
                     sentence and should run to the same edge as the abstract
                     below it, not sit evened-up and short of it. --}}
                <h1 class="display-lg display-fill">{{ $publication->title }}</h1>

                @if($venue)
                    <p class="mt-4 text-[15px] italic" style="color: var(--ink-3);">{{ $venue }}</p>
                @endif

                <p class="mt-3 text-[13px] leading-relaxed" style="color: var(--ink-4);">{{ $authors }}</p>
            </header>

            @if($publication->abstract)
                <section>
                    <h2 class="eyebrow mb-3">Abstract</h2>
                    <p class="prose-body">{{ $publication->abstract }}</p>
                </section>
            @endif

            {{-- The full byline, with the authors who wrote under our own
                 affiliation marked. The line under the title is the citation;
                 this is the roll, and it is where the roles and affiliations
                 are. --}}
            <div class="{{ $publication->abstract ? 'mt-10' : '' }}">
                @include('frontend.themes.theme_aurora.partials.publication_authors', ['publication' => $publication])
            </div>

            {{-- Somewhere to go that is not "back". --}}
            @php
                $others = $teacher->publications
                    ->where('id', '!=', $publication->id)
                    ->sortByDesc('publication_year')
                    ->take(6);
            @endphp

            @if($others->isNotEmpty())
                <section class="mt-12">
                    <div class="flex items-baseline justify-between mb-2">
                        <h2 class="eyebrow">More from {{ $teacher->first_name }}</h2>
                        <span class="numeral">{{ number_format($teacher->publications->count() - 1) }} others</span>
                    </div>

                    @foreach($others as $other)
                        @php
                            $otherUrl = ($facSlug && $deptSlug && $teacher->webpage)
                                ? route('publication.show', [
                                    'faculty_short_name' => $facSlug,
                                    'department_code' => $deptSlug,
                                    'teacher_webpage' => $teacher->webpage,
                                    'publication_slug' => $other->slug ?: \Illuminate\Support\Str::slug($other->title),
                                ])
                                : $teacherUrl;
                        @endphp

                        <a href="{{ $otherUrl }}" wire:navigate class="record">
                            <span class="record-when">{{ $other->publication_year ?? '—' }}</span>
                            <span>
                                <span class="record-what block">{{ $other->title }}</span>
                                @if($other->journal_name)
                                    <span class="record-sub block italic">{{ $other->journal_name }}</span>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </section>
            @endif

            <p class="mt-12">
                <a href="{{ $publicationsUrl }}" wire:navigate class="link-brand text-[13px]">
                    &larr; All publications by {{ $teacher->full_name }}
                </a>
            </p>
        </article>

        {{-- No longer pulled above the article on a phone. It was, back when the
             title sat outside this grid and the column started at the abstract
             — reaching the citations meant scrolling the whole paper first. Now
             the title leads the column, so the natural order is already the
             right one: what the paper is, then what it says, then the machinery
             for taking it away. --}}
        <aside class="min-w-0 lg:sticky lg:self-start space-y-8"
               style="top: calc(var(--header-h) + 1.5rem);">

            {{-- Whose work this is. A citation read on its own loses the thread,
                 and this is the same identity partial the profile head uses, so
                 the two cannot describe the same person differently. --}}
            @include('frontend.themes.theme_aurora.partials.teacher_hero', ['variant' => 'compact'])

            @if(! empty($facts))
                <section>
                    <h2 class="eyebrow mb-1">Record</h2>
                    <dl>
                        @foreach($facts as $label => $value)
                            <div class="pair" style="grid-template-columns: 7.5rem minmax(0, 1fr);">
                                <dt>{{ $label }}</dt>
                                <dd>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            {{-- Anyone who reaches this page is usually here to take the
                 reference away with them, so all three formats sit open rather
                 than behind tabs, each with its own copy control. --}}
            <section x-data="{
                        copied: null,
                        doCopy(key) {
                            const el = $refs[key];
                            if (! el) return;
                            navigator.clipboard.writeText(el.innerText);
                            this.copied = key;
                            setTimeout(() => this.copied = null, 2000);
                        }
                     }">
                <h2 class="eyebrow mb-2">Cite this work</h2>

                <div class="space-y-4">
                    @foreach($citationStyles as $style)
                        <div>
                            <div class="flex items-baseline justify-between gap-4 pt-3 mb-1.5"
                                 style="border-top: 1px solid var(--hairline-soft);">
                                <span class="eyebrow-quiet">{{ $style['label'] }}</span>
                                <button type="button" @click="doCopy('{{ $style['key'] }}')"
                                        class="text-[12px] font-semibold cursor-pointer" style="color: var(--ink-4);">
                                    <span x-show="copied !== '{{ $style['key'] }}'">Copy</span>
                                    <span x-show="copied === '{{ $style['key'] }}'" x-cloak
                                          style="color: var(--brand-ink);">Copied</span>
                                </button>
                            </div>

                            @if($style['mono'])
                                {{-- Wrapped rather than scrolled sideways. A
                                     BibTeX title is longer than any sidebar,
                                     and a horizontally scrolling block in a
                                     column this narrow reads as truncated —
                                     people copy what they can see. Wrapping
                                     does not alter innerText, so the Copy
                                     button still yields the real record. --}}
                                <pre x-ref="{{ $style['key'] }}"
                                     class="glass-soft rounded-xl p-3 font-mono text-[11px] leading-relaxed select-all whitespace-pre-wrap break-words"
                                     style="color: var(--ink-2);">{{ $citations[$style['key']] }}</pre>
                            @else
                                <p x-ref="{{ $style['key'] }}" class="text-[12.5px] leading-relaxed select-all"
                                   style="color: var(--ink-2);">{{ $citations[$style['key']] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>

@endsection
