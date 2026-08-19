@extends('frontend.themes.theme_ledger.layouts.app')

{{-- Sharing and structured data. ScholarlyArticle, because this is the one page
     on the site that is a citation target. --}}
@php
    $seo = \App\Helpers\SeoPayload::forPublication($publication, $authors, $faculty, $department);
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_ledger.partials.seo-tags', ['seo' => $seo])
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

        // A profile here is one sheet rather than nine tabs, so the link back to
        // someone's publications is an anchor into the document rather than a
        // ?tab=.
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

    <nav class="figure mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span aria-hidden="true">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span aria-hidden="true">/</span>
        <a href="{{ $deptUrl }}" wire:navigate class="hover:underline">{{ $department->code }}</a>
        <span aria-hidden="true">/</span>
        <a href="{{ $teacherUrl }}" wire:navigate class="hover:underline">{{ $teacher->full_name }}</a>
        <span aria-hidden="true">/</span>
        <span style="color: var(--ink-2);">Publication</span>
    </nav>

    {{-- One column, not two.

         Aurora sets this page as an article beside a sticky sidebar of
         citations. That works there and would work here, but it is not what a
         ledger does with a record: a catalogue entry is a heading, a table of
         facts, and then the text. Read straight down, the order is also the
         order somebody actually wants it in — what the paper is, what it says,
         who wrote it, and then the three formats to take away. --}}
    <article class="max-w-4xl">

        <header class="pb-5 rule-double-b">
            <p class="label mb-3">
                {{ optional($publication->type)->name ?? 'Research' }}
                @if($publication->publication_year)
                    <span aria-hidden="true"> · </span>{{ $publication->publication_year }}
                @endif
            </p>

            {{-- title-fill, not the balanced default: a paper's title is a
                 sentence and should run to the same edge as the abstract below
                 it, not sit evened-up and short of it. --}}
            <h1 class="title-lg title-fill">{{ $publication->title }}</h1>

            @if($venue)
                <p class="mt-4 text-[15px] italic" style="color: var(--ink-3);">{{ $venue }}</p>
            @endif

            <p class="mt-2 text-[13px] leading-relaxed" style="color: var(--ink-4);">{{ $authors }}</p>
        </header>

        @if(! empty($facts))
            <section class="mt-8">
                <p class="label pb-1.5" style="border-bottom: 1px solid var(--rule-strong);">Record</p>
                <dl class="mt-1 sm:grid sm:grid-cols-2 sm:gap-x-10">
                    @foreach($facts as $label => $value)
                        <div class="pair" style="grid-template-columns: 8.5rem minmax(0, 1fr);">
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if($publication->abstract)
            <section class="mt-10">
                <p class="label pb-1.5 mb-4" style="border-bottom: 1px solid var(--rule-strong);">Abstract</p>
                <p class="prose-body">{{ $publication->abstract }}</p>
            </section>
        @endif

        {{-- The full byline, with the authors who wrote under our own
             affiliation marked. The line under the title is the citation; this
             is the roll, and it is where the roles and affiliations are. --}}
        <div class="mt-10">
            @include('frontend.themes.theme_ledger.partials.publication_authors', ['publication' => $publication])
        </div>

        {{-- Whose work this is. The same identity partial the profile head uses,
             so the two cannot describe the same person differently. --}}
        <div class="mt-10">
            @include('frontend.themes.theme_ledger.partials.teacher_hero', ['variant' => 'compact'])
        </div>

        {{-- Anyone who reaches this page is usually here to take the reference
             away with them, so all three formats sit open rather than behind
             tabs, each with its own copy control. --}}
        <section class="mt-12" x-data="{
                    copied: null,
                    doCopy(key) {
                        const el = $refs[key];
                        if (! el) return;
                        navigator.clipboard.writeText(el.innerText);
                        this.copied = key;
                        setTimeout(() => this.copied = null, 2000);
                    }
                 }">
            <p class="label pb-1.5" style="border-bottom: 1px solid var(--rule-strong);">Cite this work</p>

            <div class="mt-1">
                @foreach($citationStyles as $style)
                    <div class="py-4" style="border-bottom: 1px solid var(--rule-soft);">
                        <div class="flex items-baseline justify-between gap-4 mb-2">
                            <span class="label">{{ $style['label'] }}</span>
                            <button type="button" @click="doCopy('{{ $style['key'] }}')"
                                    class="text-[12px] font-semibold cursor-pointer" style="color: var(--ink-4);">
                                <span x-show="copied !== '{{ $style['key'] }}'">Copy</span>
                                <span x-show="copied === '{{ $style['key'] }}'" x-cloak
                                      style="color: var(--brand-ink);">Copied</span>
                            </button>
                        </div>

                        @if($style['mono'])
                            {{-- Wrapped rather than scrolled sideways. A BibTeX
                                 title is longer than this column, and a
                                 horizontally scrolling block reads as truncated
                                 — people copy what they can see. Wrapping does
                                 not alter innerText, so the Copy button still
                                 yields the real record. --}}
                            <pre x-ref="{{ $style['key'] }}" class="cite-mono select-all">{{ $citations[$style['key']] }}</pre>
                        @else
                            <p x-ref="{{ $style['key'] }}" class="cite select-all">{{ $citations[$style['key']] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Somewhere to go that is not "back". --}}
        @php
            $others = $teacher->publications
                ->where('id', '!=', $publication->id)
                ->sortByDesc('publication_year')
                ->take(6);
        @endphp

        @if($others->isNotEmpty())
            <section class="mt-12">
                <div class="doc-head">
                    <h2 class="title-md">More from {{ $teacher->first_name }}</h2>
                    <span class="figure">{{ number_format($teacher->publications->count() - 1) }} others</span>
                </div>

                <div class="records">
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
                </div>
            </section>
        @endif

        <p class="mt-10">
            <a href="{{ $publicationsUrl }}" wire:navigate class="link text-[13px]">
                &larr; All publications by {{ $teacher->full_name }}
            </a>
        </p>
    </article>

@endsection
