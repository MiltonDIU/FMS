@extends('frontend.themes.theme_aurora.layouts.app')

{{-- Sharing and structured data. Built once and used for the title, the
     description and the tags, so a preview card and a search result cannot
     disagree with each other. --}}
@php
    $seo = \App\Helpers\SeoPayload::forTeacher($teacher, $faculty, $department);
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_aurora.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    @php
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;

        $deptUrl = ($facSlug && $department->code)
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => strtolower($department->code)])
            : route('home');

        /*
         * The sections of the document, and which of them this person has
         * anything to put in.
         *
         * The other themes render nine tabs whatever happens, so a new lecturer
         * gets eight panels that say "nothing recorded" and the tab strip
         * promises eight things that are not there. Here an empty section is
         * simply not part of the page, and the rail beside it lists exactly what
         * is — the count next to each entry is the honest one.
         *
         * researchProjects is not among the controller's eager loads, so it is
         * resolved once here rather than inside the loop below.
         */
        $projects = $teacher->researchProjects;

        $sections = collect([
            ['id' => 'overview',     'label' => 'Overview',   'count' => null, 'show' => true],
            ['id' => 'academic',     'label' => 'Education',  'count' => $teacher->educations->count()],
            ['id' => 'teaching',     'label' => 'Teaching',   'count' => $teacher->teachingAreas->count()],
            ['id' => 'research',     'label' => 'Research',   'count' => $teacher->researchInterests->count() + $projects->count()],
            ['id' => 'publications', 'label' => 'Publications', 'count' => $teacher->publications->count()],
            ['id' => 'experience',   'label' => 'Experience', 'count' => $teacher->jobExperiences->count()],
            // Certifications are rendered inside the training section — the two
            // are the same kind of fact — so they count towards whether it
            // appears at all. Without this a teacher with certifications but no
            // training courses would have them silently dropped.
            ['id' => 'training',     'label' => 'Training',   'count' => $teacher->trainingExperiences->count() + $teacher->certifications->count()],
            ['id' => 'awards',       'label' => 'Awards',     'count' => $teacher->awards->count()],
            ['id' => 'memberships',  'label' => 'Memberships','count' => $teacher->memberships->count()],
        ])->filter(fn ($s) => ($s['show'] ?? false) || ($s['count'] ?? 0) > 0)->values();

        $shown = $sections->pluck('id')->all();
    @endphp

    <nav class="text-[13px] mb-6 flex flex-wrap items-center gap-2" style="color: var(--ink-4);">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span style="color: var(--hairline-strong);">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span style="color: var(--hairline-strong);">/</span>
        <a href="{{ $deptUrl }}" wire:navigate class="hover:underline">{{ $department->code }}</a>
        <span style="color: var(--hairline-strong);">/</span>
        <span style="color: var(--ink-2);">{{ $teacher->full_name }}</span>
    </nav>

    @include('frontend.themes.theme_aurora.partials.teacher_hero')

    {{-- Below the head, the profile is one continuous document.

         The other themes hide eight-ninths of a person behind a tab strip, which
         means the browser's own find-in-page cannot see most of a profile, a
         print gives you one panel, and a link that promises publications lands
         on Overview unless the theme also parses ?tab= out of the address bar.
         Here everything is on the page and the rail says where you are in it. --}}
    {{-- The document column takes the full width beside the rail, and nothing
         inside it caps itself — the biography, the record rows and the
         two-column lists all run to the same edge. --}}
    <div class="mt-10 grid gap-10 lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-14">

        {{-- Desktop: a sticky rail. The active entry is set by an
             IntersectionObserver in theme.js; with JavaScript off these are
             still anchors to sections that are already on the page. --}}
        <nav class="rail" aria-label="Sections of this profile">
            @foreach($sections as $section)
                <a href="#{{ $section['id'] }}" data-rail-link="{{ $section['id'] }}" class="rail-link">
                    <span>{{ $section['label'] }}</span>
                    @if($section['count'])
                        <span class="numeral">{{ $section['count'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        {{-- Below that breakpoint the rail becomes a strip under the header.
             Which of the two shows is decided in theme.css, not with utility
             classes here: both set `display`, and a utility cannot outrank a
             plain class written later in the same stylesheet. --}}
        <nav class="rail-strip -mx-5 px-5 sm:-mx-8 sm:px-8" aria-label="Sections of this profile">
            @foreach($sections as $section)
                <a href="#{{ $section['id'] }}" data-rail-link="{{ $section['id'] }}">{{ $section['label'] }}</a>
            @endforeach
        </nav>

        <div class="min-w-0">
            @include('frontend.themes.theme_aurora.partials.profile.overview')

            @if(in_array('academic', $shown, true))
                @include('frontend.themes.theme_aurora.partials.profile.academic')
            @endif

            @if(in_array('teaching', $shown, true))
                @include('frontend.themes.theme_aurora.partials.profile.courses')
            @endif

            @if(in_array('research', $shown, true))
                @include('frontend.themes.theme_aurora.partials.profile.research', ['projects' => $projects])
            @endif

            @if(in_array('publications', $shown, true))
                @include('frontend.themes.theme_aurora.partials.profile.publications')
            @endif

            @if(in_array('experience', $shown, true))
                @include('frontend.themes.theme_aurora.partials.profile.experience')
            @endif

            @if(in_array('training', $shown, true))
                @include('frontend.themes.theme_aurora.partials.profile.training')
            @endif

            @if(in_array('awards', $shown, true))
                @include('frontend.themes.theme_aurora.partials.profile.awards')
            @endif

            @if(in_array('memberships', $shown, true))
                @include('frontend.themes.theme_aurora.partials.profile.memberships')
            @endif
        </div>
    </div>

@endsection
