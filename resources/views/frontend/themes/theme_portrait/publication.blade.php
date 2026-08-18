@extends('frontend.themes.theme_portrait.layouts.app')

@section('title', $publication->title . ' - Publication Details')

{{-- Sharing and structured data. ScholarlyArticle, because this is the one page
     type on the site that is a citation target. --}}
@php
    $seo = \App\Helpers\SeoPayload::forPublication($publication, $authors, $faculty, $department);
@endphp

@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.partials.seo-tags', ['seo' => $seo])
@endsection

@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $publication->abstract) ?: $publication->title, 155))

@section('content')

    @php
        // $authors and $citations are provided by the controller.
        $venue = $publication->journal_name ?? '';

        // Null-safe URLs (faculties.short_name and teachers.webpage are nullable columns).
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;
        $deptUrl = $facSlug
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => strtolower($department->code)])
            : route('home');
        $teacherUrl = ($facSlug && $teacher->webpage)
            ? route('teacher.show', ['faculty_short_name' => $facSlug, 'department_code' => strtolower($department->code), 'teacher_webpage' => $teacher->webpage])
            : route('home');

        // Same profile, opened on the tab the link promises. The breadcrumb above
        // still points at the profile proper.
        $publicationsUrl = $teacherUrl === route('home') ? $teacherUrl : $teacherUrl . '?tab=publications';

        // Only the facts this paper actually carries. An empty "N/A" column is
        // noise, so the list is built first and rendered only if it has rows.
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
    @endphp

    {{-- Breadcrumb as a line of text, same as every other page in this theme. --}}
    <nav class="text-[13px] mb-10 flex flex-wrap items-center gap-2" style="color: var(--text-muted);">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span style="color: var(--border-strong);">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span style="color: var(--border-strong);">/</span>
        <a href="{{ $deptUrl }}" wire:navigate class="hover:underline">{{ $department->code }}</a>
        <span style="color: var(--border-strong);">/</span>
        <a href="{{ $teacherUrl }}" wire:navigate class="hover:underline">{{ $teacher->full_name }}</a>
        <span style="color: var(--border-strong);">/</span>
        <span style="color: var(--text-strong);">Publication</span>
    </nav>

    {{-- The same two columns as the profile page, and the same sidebar partial.
         A paper read on its own loses the thread of whose work it is; keeping
         the portrait and contact details in view means clicking a publication
         feels like staying on the person's page rather than leaving it. --}}
    <div class="grid gap-10 lg:gap-14 lg:grid-cols-[20rem_minmax(0,1fr)]">

        @include('frontend.themes.theme_portrait.partials.teacher_aside')

        <article>

            {{-- Title block. No coloured banner: the paper's own title is the
                 loudest thing on the page. --}}
            <header class="pb-8 border-b" style="border-color: var(--border-soft);">
                <p class="eyebrow mb-3">
                    {{ optional($publication->type)->name ?? 'Research' }}
                    @if($publication->publication_year)
                        <span class="mx-1.5" style="color: var(--border-strong);">·</span>{{ $publication->publication_year }}
                    @endif
                </p>

                <h1 class="section-title max-w-3xl" style="color: var(--text-strong);">
                    {{ $publication->title }}
                </h1>

                @if($venue)
                    <p class="mt-4 text-[15px] italic" style="color: var(--text-soft);">{{ $venue }}</p>
                @endif

                <p class="mt-3 text-[13px] leading-relaxed" style="color: var(--text-muted);">
                    {{ $authors }}
                </p>
            </header>

            {{-- The full byline, with the authors who wrote under our own
                 affiliation marked. The paragraph above is the citation line;
                 this is the roll, and it is where the roles and affiliations
                 are. Shared across themes — who wrote a paper is not styling. --}}
            <div class="mt-8">
                @include('frontend.partials.publication_authors', ['publication' => $publication])
            </div>

            {{-- Facts as a definition list on hairlines, matching the contact
                 block in the sidebar — one visual language for "label, value". --}}
            @if(! empty($facts))
                <dl class="mt-8 grid gap-x-10 sm:grid-cols-2">
                    @foreach($facts as $label => $value)
                        <div class="grid grid-cols-[8.5rem_minmax(0,1fr)] gap-3 border-t py-3 text-[13px]"
                             style="border-color: var(--border-faint);">
                            <dt class="text-[11px] uppercase tracking-wider pt-0.5"
                                style="color: var(--text-muted);">{{ $label }}</dt>
                            <dd class="break-words" style="color: var(--text-soft);">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if($publication->abstract)
                <section class="mt-12">
                    <h2 class="eyebrow mb-3">Abstract</h2>
                    <p class="text-[15px] leading-[1.75] max-w-[68ch]" style="color: var(--text-base);">
                        {{ $publication->abstract }}
                    </p>
                </section>
            @endif

            {{-- Citations. Anyone who reaches this page is usually here to take
                 the reference away with them, so the three formats sit open
                 rather than behind tabs, each with its own copy control. --}}
            <section class="mt-12"
                     x-data="{
                        copied: null,
                        doCopy(ref, key) {
                            const el = $refs[ref];
                            if (! el) return;
                            navigator.clipboard.writeText(el.innerText);
                            copied = key;
                            setTimeout(() => copied = null, 2000);
                        }
                     }">
                <h2 class="eyebrow mb-4">Cite this work</h2>

                <div class="space-y-6">
                    @foreach([
                        ['key' => 'apa', 'label' => 'APA', 'mono' => false],
                        ['key' => 'ieee', 'label' => 'IEEE', 'mono' => false],
                        ['key' => 'bibtex', 'label' => 'BibTeX', 'mono' => true],
                    ] as $style)
                        <div>
                            <div class="flex items-baseline justify-between gap-4 border-t pt-3 mb-2"
                                 style="border-color: var(--border-faint);">
                                <span class="text-[11px] uppercase tracking-wider" style="color: var(--text-muted);">
                                    {{ $style['label'] }}
                                </span>
                                <button type="button" @click="doCopy('{{ $style['key'] }}', '{{ $style['key'] }}')"
                                        class="text-[12px] font-semibold cursor-pointer transition-colors hover:text-diu-primary"
                                        style="color: var(--text-muted);">
                                    <span x-show="copied !== '{{ $style['key'] }}'">Copy</span>
                                    <span x-show="copied === '{{ $style['key'] }}'" x-cloak
                                          style="color: var(--brand-ink);">Copied</span>
                                </button>
                            </div>

                            @if($style['mono'])
                                <pre x-ref="{{ $style['key'] }}"
                                     class="font-mono text-[12px] leading-relaxed select-all overflow-x-auto whitespace-pre p-4 rounded-md"
                                     style="color: var(--text-base); background-color: var(--surface-subtle);">{{ $citations[$style['key']] }}</pre>
                            @else
                                <p x-ref="{{ $style['key'] }}"
                                   class="text-[13px] leading-relaxed select-all max-w-[68ch]"
                                   style="color: var(--text-base);">{{ $citations[$style['key']] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Other papers by the same person, so the page has somewhere to go
                 that is not "back". --}}
            @php
                $others = $teacher->publications
                    ->where('id', '!=', $publication->id)
                    ->sortByDesc('publication_year')
                    ->take(5);
            @endphp

            @if($others->isNotEmpty())
                <section class="mt-14">
                    <div class="flex items-baseline justify-between mb-2">
                        <h2 class="eyebrow">More from {{ $teacher->first_name }}</h2>
                        <span class="count">{{ number_format($teacher->publications->count() - 1) }} others</span>
                    </div>

                    <div>
                        @foreach($others as $other)
                            @php
                                $otherUrl = ($facSlug && $teacher->webpage)
                                    ? route('publication.show', [
                                        'faculty_short_name' => $facSlug,
                                        'department_code' => strtolower($department->code),
                                        'teacher_webpage' => $teacher->webpage,
                                        'publication_slug' => $other->slug ?: \Illuminate\Support\Str::slug($other->title),
                                    ])
                                    : $teacherUrl;
                            @endphp
                            <a href="{{ $otherUrl }}" wire:navigate
                               class="grid grid-cols-[3.5rem_minmax(0,1fr)] gap-4 items-baseline border-t py-4 group"
                               style="border-color: var(--border-faint);">
                                <span class="count">{{ $other->publication_year ?? '—' }}</span>
                                <span>
                                    <span class="block text-[15px] font-semibold leading-snug transition-colors group-hover:text-diu-primary"
                                          style="color: var(--text-strong);">{{ $other->title }}</span>
                                    @if($other->journal_name)
                                        <span class="mt-1 block text-[13px] italic" style="color: var(--text-muted);">
                                            {{ $other->journal_name }}
                                        </span>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <p class="mt-12">
                <a href="{{ $publicationsUrl }}" wire:navigate
                   class="text-[13px] font-semibold hover:underline" style="color: var(--brand-ink);">
                    ← All publications by {{ $teacher->full_name }}
                </a>
            </p>

        </article>
    </div>

@endsection
