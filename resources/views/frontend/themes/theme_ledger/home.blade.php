@extends('frontend.themes.theme_ledger.layouts.app')

{{-- Sharing and structured data, built in PHP so the title, the description and
     the tags cannot disagree with each other. --}}
@php
    $seo = $selectedFaculty
        ? \App\Helpers\SeoPayload::forFaculty($selectedFaculty, $departments->count(), $selectedFaculty->teachers_count ?? 0)
        : \App\Helpers\SeoPayload::forDirectory($totalFaculties ?? 0, $totalDepartments ?? 0, $totalTeachers ?? 0);
@endphp

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_ledger.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    @if($selectedFaculty)

        {{-- A faculty was asked for by name, so the page opens on its people
             rather than making someone find them a second time. --}}
        <nav class="figure mb-5 flex flex-wrap items-center gap-2">
            <a href="{{ route('home') }}" wire:navigate class="hover:underline">Directory</a>
            <span aria-hidden="true">/</span>
            <span style="color: var(--ink-2);">{{ $selectedFaculty->name }}</span>
        </nav>

        <livewire:teacher-search :selected-faculty-id="$selectedFaculty->id" />

    @else

        {{-- ── Title page ─────────────────────────────────────────────────────
             No banner, no statistics tiles. This is the title page of a
             reference work: what it is, how big this edition is, and then
             straight into the index. The four numbers are a sentence rather
             than four boxes nobody clicks. --}}
        <section class="pb-7 mb-8 rule-double-b">
            <p class="label mb-4">{{ \App\Helpers\Branding::get('short_name') }} · Faculty Directory</p>

            <h1 class="title-xl max-w-4xl">Every scholar at {{ \App\Helpers\Branding::get('site_name') }}</h1>

            <p class="lede mt-6">
                {{ number_format($totalTeachers) }} academics across
                {{ number_format($totalDepartments) }} departments and
                {{ number_format($totalFaculties) }} faculties, with
                {{ number_format($totalPublications) }} recorded publications between them.
                Start typing to find someone.
            </p>
        </section>

        {{-- The index itself. Search, faculty and department navigation and the
             rows are one component, so nothing on this page is a dead end that
             has to be backed out of. --}}
        <livewire:teacher-search />

        {{-- ── The register ──────────────────────────────────────────────────
             The index rail above only lists a faculty's departments once that
             faculty is chosen. This is the whole map, for anyone who arrived
             knowing exactly which department they want — and for the crawlers,
             which is how a department page gets found at all.

             Set as ruled columns rather than cards for the same reason the
             directory is: it is a table of contents, and a table of contents is
             read down. --}}
        <section class="mt-16">
            <div class="flex items-baseline justify-between pb-2 mb-1 rule-hard" style="border-top: 0; border-bottom: 1px solid var(--rule-strong);">
                <h2 class="title-md">Faculties and departments</h2>
                <span class="figure">{{ number_format($totalDepartments) }}</span>
            </div>

            <div class="grid gap-x-12 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($faculties as $faculty)
                    @php $facultyDepartments = $faculty->departments; @endphp

                    @if($facultyDepartments->isNotEmpty())
                        <div class="break-inside-avoid">
                            {{-- The name, then the code. short_name is nullable,
                                 so the code is the part that may be missing —
                                 which is the right way round: the name is what
                                 this heading is for. --}}
                            <a href="{{ $faculty->url }}" wire:navigate
                               class="flex items-baseline justify-between gap-3 pt-5 pb-1.5"
                               style="border-bottom: 1px solid var(--rule);">
                                <span class="text-[14px] font-bold" style="color: var(--ink);">
                                    Faculty of {{ $faculty->name }}
                                </span>
                                @if(filled($faculty->short_name))
                                    <span class="figure shrink-0">{{ strtoupper($faculty->short_name) }}</span>
                                @endif
                            </a>

                            <ul>
                                @foreach($facultyDepartments as $dept)
                                    @php
                                        $deptUrl = $faculty->short_name
                                            ? route('department.show', [
                                                'faculty_short_name' => strtolower($faculty->short_name),
                                                'department_code' => strtolower($dept->code),
                                            ])
                                            : null;
                                    @endphp
                                    <li style="border-bottom: 1px solid var(--rule-soft);">
                                        <a @if($deptUrl) href="{{ $deptUrl }}" wire:navigate @endif
                                           class="flex items-baseline justify-between gap-3 py-2 text-[13.5px]"
                                           style="color: var(--ink-2);">
                                            <span>{{ $dept->name }}</span>
                                            {{-- Upper-cased here rather than trusted from
                                                 the column: most codes are stored
                                                 capitalised but a few are not, and "rme"
                                                 sitting in a column of ICE, TE and EEE
                                                 reads as a mistake. --}}
                                            <span class="figure shrink-0">{{ strtoupper($dept->code) }}</span>
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
