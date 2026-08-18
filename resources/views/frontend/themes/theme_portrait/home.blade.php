@extends('frontend.themes.theme_portrait.layouts.app')

{{-- Sharing and structured data. Built in PHP and rendered by a shared partial
     so all four themes advertise the same thing. --}}
@php
    $seo = $selectedFaculty
        ? \App\Helpers\SeoPayload::forFaculty($selectedFaculty, $departments->count(), $selectedFaculty->teachers_count ?? 0)
        : \App\Helpers\SeoPayload::forDirectory($totalFaculties ?? 0, $totalDepartments ?? 0, $totalTeachers ?? 0);
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_portrait.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    @if($selectedFaculty)

        {{-- A faculty is selected: hand straight over to the gallery. The
             breadcrumb is a line of text rather than a panel, matching the rest
             of the theme. --}}
        <nav class="text-[13px] mb-8 flex flex-wrap items-center gap-2" style="color: var(--text-muted);">
            <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
            <span style="color: var(--border-strong);">/</span>
            <span style="color: var(--text-strong);">{{ $selectedFaculty->name }}</span>
        </nav>

        <livewire:teacher-search :selected-faculty-id="$selectedFaculty->id" />

    @else

        {{-- ── Masthead ──────────────────────────────────────────────────────
             No gradient panel, no dot mesh, no glow. The other three themes
             open with a coloured hero; this one opens with a sentence, because
             the photographs further down are what the page is actually for. --}}
        <section class="pb-12 md:pb-16">
            <p class="eyebrow mb-5">{{ \App\Helpers\Branding::get('short_name') }} · Faculty Directory</p>

            <h1 class="masthead max-w-4xl" style="color: var(--text-strong);">
                The people who teach here.
            </h1>

            <p class="mt-6 max-w-xl text-base leading-relaxed" style="color: var(--text-soft);">
                {{ number_format($totalTeachers) }} academics across
                {{ number_format($totalDepartments) }} departments. Browse by faculty, or search
                for someone by name, designation or research interest.
            </p>
        </section>

        {{-- ── Faculty index ────────────────────────────────────────────────
             Rows, not cards. Six faculties read faster as a numbered list than
             as six coloured boxes, and a list scales if a seventh is added. --}}
        <section id="faculties" class="mb-20">
            <div class="flex items-baseline justify-between mb-2">
                <h2 class="section-title" style="color: var(--text-strong);">Faculties</h2>
                <span class="count">{{ number_format($totalFaculties) }}</span>
            </div>

            <div>
                @foreach($faculties as $index => $faculty)
                    <a href="{{ $faculty->url }}" wire:navigate class="index-row block">
                        <span class="index-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>

                        <span>
                            <span class="index-name block" style="color: var(--text-strong);">
                                {{ $faculty->name }}
                            </span>
                            @if($faculty->description)
                                <span class="mt-1 block text-[13px] leading-relaxed max-w-2xl"
                                      style="color: var(--text-muted);">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($faculty->description), 150) }}
                                </span>
                            @endif
                        </span>

                        <span class="count whitespace-nowrap">
                            {{ number_format($faculty->teachers_count) }} faculty
                            <span class="mx-1" style="color: var(--border-strong);">·</span>
                            {{ number_format($faculty->departments_count) }} dept
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- ── Departments ──────────────────────────────────────────────────
             A plain typographic list grouped under its faculty. Reaching a
             department is the most common reason anyone opens this page, so it
             is one click with nothing decorative in the way. --}}
        <section>
            <h2 class="section-title mb-6" style="color: var(--text-strong);">Departments</h2>

            <div class="grid gap-x-10 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($faculties as $faculty)
                    @php $facultyDepartments = $faculty->departments; @endphp

                    @if($facultyDepartments->isNotEmpty())
                        <div>
                            <p class="eyebrow mb-3">{{ $faculty->short_name ?: $faculty->name }}</p>

                            <ul class="space-y-0">
                                @foreach($facultyDepartments as $dept)
                                    @php
                                        $deptUrl = $faculty->short_name
                                            ? route('department.show', [
                                                'faculty_short_name' => strtolower($faculty->short_name),
                                                'department_code' => strtolower($dept->code),
                                            ])
                                            : '#';
                                    @endphp
                                    <li class="border-t" style="border-color: var(--border-faint);">
                                        <a href="{{ $deptUrl }}" wire:navigate
                                           class="flex items-baseline justify-between gap-3 py-2.5 text-[14px] transition-colors hover:text-diu-primary"
                                           style="color: var(--text-soft);">
                                            <span>{{ $dept->name }}</span>
                                            <span class="count shrink-0">{{ $dept->code }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>

    @endif

@endsection
