@extends('frontend.themes.theme_aurora.layouts.app')

{{-- Sharing and structured data, built in PHP so the title, the description
     and the tags cannot disagree with each other. --}}
@php
    $seo = $selectedFaculty
        ? \App\Helpers\SeoPayload::forFaculty($selectedFaculty, $departments->count(), $selectedFaculty->teachers_count ?? 0)
        : \App\Helpers\SeoPayload::forDirectory($totalFaculties ?? 0, $totalDepartments ?? 0, $totalTeachers ?? 0);
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_aurora.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    @if($selectedFaculty)

        {{-- A faculty was asked for by name, so the page opens on its people
             rather than making someone find them a second time. --}}
        <nav class="text-[13px] mb-6 flex flex-wrap items-center gap-2" style="color: var(--ink-4);">
            <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
            <span style="color: var(--hairline-strong);">/</span>
            <span style="color: var(--ink-2);">{{ $selectedFaculty->name }}</span>
        </nav>

        <livewire:teacher-search :selected-faculty-id="$selectedFaculty->id" />

    @else

        {{-- ── Opening ───────────────────────────────────────────────────────
             No banner panel and no statistics tiles: the aurora behind the page
             is already the decoration, and the numbers are three words in the
             sentence below rather than four boxes nobody clicks. --}}
        <section class="pt-6 pb-10 md:pt-12 md:pb-14">
            <p class="eyebrow mb-5">{{ \App\Helpers\Branding::get('short_name') }} · Faculty Directory</p>

            <h1 class="display-xl max-w-4xl">
                Every scholar at<br>
                <span class="display-spectrum">{{ \App\Helpers\Branding::get('site_name') }}</span>
            </h1>

            <p class="lede mt-7">
                {{ number_format($totalTeachers) }} academics across
                {{ number_format($totalDepartments) }} departments and
                {{ number_format($totalFaculties) }} faculties, with
                {{ number_format($totalPublications) }} recorded publications between them.
                Start typing to find someone.
            </p>
        </section>

        {{-- The directory itself. Search, faculty and department navigation and
             the results are one component, so nothing on this page is a dead
             end that has to be backed out of. --}}
        <livewire:teacher-search />

        {{-- ── Departments ──────────────────────────────────────────────────
             The chip rail above only shows a faculty's departments once that
             faculty is chosen. This is the whole map, for anyone who arrived
             knowing exactly which department they want — and for the crawlers,
             which is how a department page gets found at all. --}}
        <section class="mt-20">
            <div class="flex items-baseline justify-between mb-6">
                <h2 class="display-md">All departments</h2>
                <span class="numeral">{{ number_format($totalDepartments) }}</span>
            </div>

            <div class="grid gap-x-10 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($faculties as $faculty)
                    @php $facultyDepartments = $faculty->departments; @endphp

                    @if($facultyDepartments->isNotEmpty())
                        <div>
                            {{-- The name, then the code. short_name is nullable,
                                 so the code is the part that may be missing —
                                 which is the right way round: the name is what
                                 this heading is for. --}}
                            <a href="{{ $faculty->url }}" wire:navigate class="group-title hover:underline">
                                Faculty of {{ $faculty->name }}@if(filled($faculty->short_name))<span class="numeral"> · {{ $faculty->short_name }}</span>@endif
                            </a>

                            <ul class="mt-3">
                                @foreach($facultyDepartments as $dept)
                                    @php
                                        $deptUrl = $faculty->short_name
                                            ? route('department.show', [
                                                'faculty_short_name' => strtolower($faculty->short_name),
                                                'department_code' => strtolower($dept->code),
                                            ])
                                            : null;
                                    @endphp
                                    <li style="border-top: 1px solid var(--hairline-soft);">
                                        <a @if($deptUrl) href="{{ $deptUrl }}" wire:navigate @endif
                                           class="row-link flex items-baseline justify-between gap-3 py-2.5 text-[14px]">
                                            <span>{{ $dept->name }}</span>
                                            {{-- Upper-cased here rather than trusted from the
                                                 column: most codes are stored capitalised but a
                                                 few are not, and "rme" sitting in a column of
                                                 ICE, TE and EEE reads as a mistake. --}}
                                            <span class="numeral shrink-0">{{ strtoupper($dept->code) }}</span>
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
