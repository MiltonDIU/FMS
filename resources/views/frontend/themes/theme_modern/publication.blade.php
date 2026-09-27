@extends('frontend.themes.theme_modern.layouts.app')

{{-- Sharing and structured data. ScholarlyArticle, because this is the one page
     type on the site that is a citation target. --}}
@php
    $seo = \App\Helpers\SeoPayload::forPublication($publication, $authors, $faculty, $department);
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_modern.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    @php
        // $authors and $citations are provided by the controller.
        $venue = $publication->journal_name ?? '';

        // Null-safe URLs (faculties.short_name, departments.code and
        // teachers.webpage are nullable columns).
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;
        $deptSlug = $department->code ? strtolower($department->code) : null;
        $departmentUrl = ($facSlug && $deptSlug)
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => $deptSlug])
            : route('home');
        $teacherUrl = ($facSlug && $deptSlug && $teacher->webpage)
            ? route('teacher.show', ['faculty_short_name' => $facSlug, 'department_code' => $deptSlug, 'teacher_webpage' => $teacher->webpage])
            : route('home');

        // The whole profile is one page now, so the way back to someone's
        // publications is an anchor into it.
        $publicationsUrl = $teacherUrl === route('home') ? $teacherUrl : $teacherUrl . '#publications';

        // Only the facts this paper actually carries. An empty "N/A" box is
        // noise, so the list is built first and rendered only if it has any.
        $facts = array_filter([
            'Journal / Conference' => $venue,
            'Published Year'       => $publication->publication_year,
            'Type'                 => optional($publication->type)->name,
            'Impact Factor'        => $publication->impact_factor,
            'CiteScore'            => $publication->citescore,
            'H-Index'              => $publication->h_index,
            'Research Area'        => $publication->research_area,
            'Created By'           => $publication->created_by_name,
        ], fn ($value) => filled($value));

        // The accent each figure has always been set in.
        $factTone = [
            'Impact Factor' => 'text-emerald-600',
            'CiteScore'     => 'text-blue-600',
            'H-Index'       => 'text-indigo-600',
        ];
    @endphp

    <!-- Breadcrumbs -->
    <div class="text-xs text-slate-500 font-semibold mb-8 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
        <a href="{{ route('home') }}" wire:navigate class="hover:text-diu-primary transition">Home</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:text-diu-primary transition">{{ $faculty->short_name }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $departmentUrl }}" wire:navigate class="hover:text-diu-primary transition">{{ $department->code }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $teacherUrl }}" wire:navigate class="hover:text-diu-primary transition">{{ $teacher->full_name }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <span class="text-diu-primary truncate max-w-xs">Publication Details</span>
    </div>

    <div class="card overflow-hidden font-sans">

        <!-- Cover / Hero header banner -->
        <div class="relative h-44 flex items-end p-6 md:p-8"
             style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-diu-primary-dark) 90%, #0a0f1a) 0%, color-mix(in srgb, var(--color-diu-primary) 75%, #0a0f1a) 60%, color-mix(in srgb, var(--color-diu-accent) 65%, #0a0f1a) 100%);">
            <a href="{{ $teacherUrl }}" wire:navigate
               class="absolute top-4 left-4 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-xl flex items-center gap-1.5 transition-all backdrop-blur-xs">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7M19 12H5"/></svg>
                Back to Profile
            </a>
            <div class="absolute right-6 top-6 text-white/10 font-display font-extrabold text-7xl select-none hidden sm:block">{{ \App\Helpers\Branding::get('short_name') }}</div>

            <div class="relative z-10 w-full">
                <span class="bg-diu-accent text-white text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm tracking-wide shadow-xs border border-diu-accent/20">
                    {{ optional($publication->type)->name ?? 'Research' }} Publication
                </span>
                <h1 class="text-lg md:text-xl font-display font-bold text-white tracking-tight mt-3 leading-snug max-w-3xl">{{ $publication->title }}</h1>
                <p class="text-xs text-white/85 mt-2 font-medium">Authors: {{ $authors }}</p>
            </div>
        </div>

        <div class="p-6 md:p-8 space-y-8">

            {{-- Every author of the paper, with the ones who wrote it under our
                 own affiliation marked. This theme's own partial: nothing here
                 depends on another theme being present. --}}
            @include('frontend.themes.theme_modern.partials.publication_authors', ['publication' => $publication])

            <!-- Core Metadata Block -->
            @if(! empty($facts))
                <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-100 text-xs ring-1 ring-slate-900/5">
                    @foreach($facts as $label => $value)
                        <div class="min-w-0">
                            <dt class="text-[10px] text-slate-400 font-bold uppercase">{{ $label }}</dt>
                            @if($label === 'Research Area')
                                <dd class="text-xs font-semibold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-md inline-block mt-1">{{ $value }}</dd>
                            @elseif($label === 'Published Year')
                                <dd class="font-semibold text-slate-800 mt-1 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
                                    {{ $value }}
                                </dd>
                            @else
                                <dd class="font-semibold {{ $factTone[$label] ?? 'text-slate-800' }} mt-1 leading-tight" style="overflow-wrap: anywhere;">{{ $value }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>
            @endif

            <!-- Abstract Section -->
            @if($publication->abstract)
                <div>
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
                        Abstract
                    </h3>
                    <p class="text-sm text-slate-600 leading-relaxed font-sans text-justify">{{ $publication->abstract }}</p>
                </div>
            @endif

            <!-- Dynamic Citation Generator Widget -->
            <div x-data="{ copied: null, doCopy(ref, key) { const el = $refs[ref]; if(!el) return; navigator.clipboard.writeText(el.innerText); copied = key; setTimeout(() => copied = null, 2000); } }"
                 class="bg-white rounded-2xl border border-slate-200 p-5 ring-1 ring-slate-900/5">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 9 5 10 5 10zM18 21c-3 0-7-1-7-8V5c0-1.25.757-2.017 2-2h3c1.25 0 2 .75 2 1.972V11c0 9-5 10-5 10z"/></svg>
                    Scholarly Citation Generator
                </h3>

                <div class="space-y-4">
                    <!-- APA -->
                    <div>
                        <div class="flex justify-between items-center mb-1 text-[11px] font-semibold text-gray-400">
                            <span>APA STYLE</span>
                            <button @click="doCopy('apa', 'apa')" class="hover:text-diu-primary flex items-center gap-1 cursor-pointer transition-colors">
                                <template x-if="copied === 'apa'">
                                    <span class="text-emerald-600 flex items-center gap-1 font-sans"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg> Copied</span>
                                </template>
                                <template x-if="copied !== 'apa'">
                                    <span class="flex items-center gap-1 font-sans"><svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg> Copy Citation</span>
                                </template>
                            </button>
                        </div>
                        <p x-ref="apa" class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-sans select-all leading-relaxed">{{ $citations['apa'] }}</p>
                    </div>

                    <!-- IEEE -->
                    <div>
                        <div class="flex justify-between items-center mb-1 text-[11px] font-semibold text-gray-400">
                            <span>IEEE STYLE</span>
                            <button @click="doCopy('ieee', 'ieee')" class="hover:text-diu-primary flex items-center gap-1 cursor-pointer transition-colors">
                                <template x-if="copied === 'ieee'">
                                    <span class="text-emerald-600 flex items-center gap-1 font-sans"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg> Copied</span>
                                </template>
                                <template x-if="copied !== 'ieee'">
                                    <span class="flex items-center gap-1 font-sans"><svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg> Copy Citation</span>
                                </template>
                            </button>
                        </div>
                        <p x-ref="ieee" class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-sans select-all leading-relaxed">{{ $citations['ieee'] }}</p>
                    </div>

                    <!-- BibTeX -->
                    <div>
                        <div class="flex justify-between items-center mb-1 text-[11px] font-semibold text-gray-400">
                            <span>BIBTEX PARSER</span>
                            <button @click="doCopy('bibtex', 'bibtex')" class="hover:text-diu-primary flex items-center gap-1 cursor-pointer transition-colors">
                                <template x-if="copied === 'bibtex'">
                                    <span class="text-emerald-600 flex items-center gap-1 font-sans"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg> Copied</span>
                                </template>
                                <template x-if="copied !== 'bibtex'">
                                    <span class="flex items-center gap-1 font-sans"><svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg> Copy BibTeX</span>
                                </template>
                            </button>
                        </div>
                        {{-- Wrapped rather than scrolled sideways: a horizontally
                             scrolling block reads as truncated, and people copy
                             what they can see. Wrapping does not alter innerText,
                             so Copy still yields the real record. --}}
                        <pre x-ref="bibtex" class="p-3 bg-slate-900 text-slate-100 rounded-xl text-[11px] font-mono select-all whitespace-pre-wrap break-words leading-normal shadow-inner">{{ $citations['bibtex'] }}</pre>
                    </div>
                </div>
            </div>

            <!-- Contributing Academic Member info -->
            <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl flex items-center justify-between ring-1 ring-slate-900/5">
                {{-- Whose work this is: name, post and department, so a citation
                     read on its own does not lose the thread. --}}
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-200 shrink-0">
                        @if($teacher->photo_thumb_url)
                            <img src="{{ $teacher->photo_thumb_url }}" alt="{{ $teacher->full_name }}" loading="lazy" class="w-full h-full object-cover" />
                        @else
                            <div class="w-full h-full bg-diu-primary text-white flex items-center justify-center font-display font-bold">{{ $teacher->initials }}</div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Contributing Scholar</p>
                        <p class="text-xs font-bold text-slate-800 font-display">{{ $teacher->full_name }}</p>
                        <p class="text-[11px] text-slate-500 truncate">
                            {{ $teacher->designation_title ?? 'Faculty Member' }}@if($department?->name) · {{ $department->name }}@endif
                        </p>
                    </div>
                </div>

                <a href="{{ $teacherUrl }}" wire:navigate
                   class="text-xs font-semibold text-diu-primary hover:text-diu-accent transition-colors flex items-center gap-1 shrink-0">
                    Back to Academic Profile <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg>
                </a>
            </div>

            {{-- Somewhere to go that is not "back". --}}
            @php
                $others = $teacher->publications
                    ->where('id', '!=', $publication->id)
                    ->sortByDesc('publication_year')
                    ->take(6);
            @endphp

            @if($others->isNotEmpty())
                <section>
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                            More from {{ $teacher->first_name }}
                        </h3>
                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                            {{ number_format($teacher->publications->count() - 1) }} others
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
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

                            <a href="{{ $otherUrl }}" wire:navigate
                               class="group flex gap-3 p-3.5 rounded-2xl border border-slate-200 hover:border-diu-primary/40 hover:shadow-xs transition-all">
                                <span class="text-[11px] font-sans font-black text-diu-primary tracking-wider tabular-nums shrink-0 pt-0.5">{{ $other->publication_year ?? '—' }}</span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-semibold text-slate-800 leading-snug group-hover:text-diu-primary transition-colors">{{ $other->title }}</span>
                                    @if($other->journal_name)
                                        <span class="block text-[11px] text-slate-500 italic mt-0.5">{{ $other->journal_name }}</span>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <p>
                <a href="{{ $publicationsUrl }}" wire:navigate class="text-xs font-bold text-diu-primary hover:underline">
                    &larr; All publications by {{ $teacher->full_name }}
                </a>
            </p>

        </div>
    </div>

@endsection
