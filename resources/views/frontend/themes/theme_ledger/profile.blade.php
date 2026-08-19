@extends('frontend.themes.theme_ledger.layouts.app')

{{-- Sharing and structured data. Built once and used for the title, the
     description and the tags, so a preview card and a search result cannot
     disagree with each other. --}}
@php
    $seo = \App\Helpers\SeoPayload::forTeacher($teacher, $faculty, $department);
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_ledger.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    @php
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;

        $deptUrl = ($facSlug && $department->code)
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => strtolower($department->code)])
            : route('home');

        /*
         * The sections of the sheet, and which of them this person has anything
         * to put in.
         *
         * The older themes render nine tabs whatever happens, so a new lecturer
         * gets eight panels that say "nothing recorded" and the tab strip
         * promises eight things that are not there. Here an empty section is
         * simply not part of the page, and the index beside it lists exactly
         * what is — the count next to each entry is the honest one.
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

    <nav class="figure mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span aria-hidden="true">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span aria-hidden="true">/</span>
        <a href="{{ $deptUrl }}" wire:navigate class="hover:underline">{{ $department->code }}</a>
        <span aria-hidden="true">/</span>
        <span style="color: var(--ink-2);">{{ $teacher->full_name }}</span>
    </nav>

    {{-- The name runs the full measure, above both columns. A ledger sheet is
         headed before it is ruled. --}}
    @include('frontend.themes.theme_ledger.partials.teacher_hero', ['variant' => 'head'])

    {{-- Below the head, the sheet: the stub on the left carries who this is and
         what you can take away, the entries on the right carry the record.

         Everything is on the page. The older themes hide eight-ninths of a
         person behind a tab strip, which means the browser's own find-in-page
         cannot see most of a profile, a print gives you one panel, and a link
         that promises publications lands on Overview unless the theme also
         parses ?tab= out of the address bar. --}}
    <div class="with-stub mt-8">

        <div class="min-w-0">
            @include('frontend.themes.theme_ledger.partials.teacher_hero', ['variant' => 'stub'])

            {{-- Desktop: the index of the sheet, sticky beside it. The active
                 entry is set by an IntersectionObserver in theme.js; with
                 JavaScript off these are still anchors to sections that are
                 already on the page. --}}
            <nav class="section-list mt-8" aria-label="Sections of this profile">
                <p class="label pb-1.5 mb-1" style="border-bottom: 1px solid var(--rule-strong);">Contents</p>
                @foreach($sections as $section)
                    <a href="#{{ $section['id'] }}" data-section-link="{{ $section['id'] }}" class="section-link">
                        <span>{{ $section['label'] }}</span>
                        @if($section['count'])
                            <span class="figure">{{ $section['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="min-w-0">
            {{-- Below that breakpoint the index becomes a strip that parks under
                 the masthead. Which of the two shows is decided in theme.css,
                 not with utility classes here: both set `display`, and a utility
                 cannot outrank a plain class written later in the same
                 stylesheet. --}}
            <nav class="section-strip" aria-label="Sections of this profile">
                @foreach($sections as $section)
                    <a href="#{{ $section['id'] }}" data-section-link="{{ $section['id'] }}">{{ $section['label'] }}</a>
                @endforeach
            </nav>

            <div class="pt-8 lg:pt-0">
                @include('frontend.themes.theme_ledger.partials.profile.overview')

                @if(in_array('academic', $shown, true))
                    @include('frontend.themes.theme_ledger.partials.profile.academic')
                @endif

                @if(in_array('teaching', $shown, true))
                    @include('frontend.themes.theme_ledger.partials.profile.courses')
                @endif

                @if(in_array('research', $shown, true))
                    @include('frontend.themes.theme_ledger.partials.profile.research', ['projects' => $projects])
                @endif

                @if(in_array('publications', $shown, true))
                    @include('frontend.themes.theme_ledger.partials.profile.publications')
                @endif

                @if(in_array('experience', $shown, true))
                    @include('frontend.themes.theme_ledger.partials.profile.experience')
                @endif

                @if(in_array('training', $shown, true))
                    @include('frontend.themes.theme_ledger.partials.profile.training')
                @endif

                @if(in_array('awards', $shown, true))
                    @include('frontend.themes.theme_ledger.partials.profile.awards')
                @endif

                @if(in_array('memberships', $shown, true))
                    @include('frontend.themes.theme_ledger.partials.profile.memberships')
                @endif
            </div>
        </div>
    </div>

@endsection
