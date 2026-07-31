@extends('frontend.themes.theme_portrait.layouts.app')

{{-- Sharing and structured data. Built once and used for the title, the
     description and the tags, so a preview card and a search result cannot
     disagree with each other. --}}
@php
    $seo = \App\Helpers\SeoPayload::forTeacher($teacher, $faculty, $department);
@endphp

@section('title', $seo['title'])

@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    @php
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;

        $deptUrl = $facSlug
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => strtolower($department->code)])
            : route('home');
    @endphp

    {{-- Breadcrumb as a line of text rather than a panel. --}}
    <nav class="text-[13px] mb-10 flex flex-wrap items-center gap-2" style="color: var(--text-muted);">
        <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
        <span style="color: var(--border-strong);">/</span>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:underline">{{ $faculty->short_name }}</a>
        <span style="color: var(--border-strong);">/</span>
        <a href="{{ $deptUrl }}" wire:navigate class="hover:underline">{{ $department->code }}</a>
        <span style="color: var(--border-strong);">/</span>
        <span style="color: var(--text-strong);">{{ $teacher->full_name }}</span>
    </nav>

    {{-- Two columns rather than a banner with an avatar floating over it. The
         portrait and the ways to reach this person stay in view on the left
         while the tabbed detail scrolls on the right, which is the order someone
         actually reads a profile in: look at the face, then read about them. --}}
    <div class="grid gap-10 lg:gap-14 lg:grid-cols-[20rem_minmax(0,1fr)]" id="teacher-profile-{{ $teacher->id }}">

        @include('frontend.themes.theme_portrait.partials.teacher_aside')

        <div>
            @php
                $tabs = [
                    ['id' => 'overview', 'label' => 'Overview'],
                    ['id' => 'academic', 'label' => 'Academic Background'],
                    ['id' => 'courses', 'label' => 'Teaching Area'],
                    ['id' => 'research', 'label' => 'Research'],
                    ['id' => 'publications', 'label' => 'Publications (' . $teacher->publications->count() . ')'],
                    ['id' => 'experience', 'label' => 'Experience'],
                    ['id' => 'training', 'label' => 'Training'],
                    ['id' => 'awards', 'label' => 'Awards'],
                    ['id' => 'memberships', 'label' => 'Memberships'],
                ];
            @endphp

            {{-- ?tab= opens the profile on a given tab, so a link that promises
                 publications lands on publications instead of dropping you back
                 at Overview. Checked against the real tab list, since it comes
                 from the address bar. The canonical tag ignores the query
                 string, so this adds no duplicate URL for search engines. --}}
            <div x-data="{
                    tab: 'overview',
                    init() {
                        const asked = new URLSearchParams(window.location.search).get('tab');
                        if (asked && @js(array_column($tabs, 'id')).includes(asked)) {
                            this.tab = asked;
                        }
                    }
                 }">
                {{-- Sticks below the site header so the tabs stay reachable while a
                     long list scrolls — Publications alone can run to hundreds of
                     entries, and without this you would scroll all the way back
                     up to change tab. The offset is the header's own height,
                     shared as a token so the two cannot drift apart. --}}
                <div class="sticky z-30 flex overflow-x-auto gap-1 mb-6 border-b bg-surface-page"
                     style="top: var(--header-height); border-color: var(--border-soft);">
                    @foreach($tabs as $tab)
                        <button @click="tab = '{{ $tab['id'] }}'"
                                :class="tab === '{{ $tab['id'] }}' ? 'border-diu-primary text-diu-primary font-bold' : 'border-transparent hover:text-diu-primary'"
                                class="px-4 py-3 text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-2 border-b-2 -mb-px cursor-pointer">
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>

                @include('frontend.themes.theme_portrait.partials.profile.overview')
                @include('frontend.themes.theme_portrait.partials.profile.academic')
                @include('frontend.themes.theme_portrait.partials.profile.courses')
                @include('frontend.themes.theme_portrait.partials.profile.research')
                @include('frontend.themes.theme_portrait.partials.profile.publications')
                @include('frontend.themes.theme_portrait.partials.profile.experience')
                @include('frontend.themes.theme_portrait.partials.profile.training')
                @include('frontend.themes.theme_portrait.partials.profile.awards')
                @include('frontend.themes.theme_portrait.partials.profile.memberships')

            </div>


        </div>
    </div>

@endsection
