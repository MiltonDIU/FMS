@extends('frontend.themes.theme_diu.layouts.app')

@section('title', $publication->title . ' - Publication Details')

@section('content')

    @php
        // $authors and $citations are provided by the controller.
        $venue = $publication->journal_name ?? '';

        // Null-safe URLs (faculties.short_name and teachers.webpage are nullable columns).
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;
        $departmentUrl = $facSlug
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => strtolower($department->code)])
            : route('home');
        $teacherUrl = ($facSlug && $teacher->webpage)
            ? route('teacher.show', ['faculty_short_name' => $facSlug, 'department_code' => strtolower($department->code), 'teacher_webpage' => $teacher->webpage])
            : route('home');
    @endphp

    <!-- Breadcrumbs -->
    <div class="text-xs text-slate-500 font-semibold mb-8 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
        <a href="{{ route('home') }}" class="hover:text-diu-primary transition">Home</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $faculty->url }}" class="hover:text-diu-primary transition">{{ $faculty->short_name }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $departmentUrl }}" class="hover:text-diu-primary transition">{{ $department->code }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $teacherUrl }}" class="hover:text-diu-primary transition">{{ $teacher->first_name }} {{ $teacher->last_name }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <span class="text-diu-primary truncate max-w-xs">Publication Details</span>
    </div>

    <div class="bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-xs overflow-hidden font-sans">

        <!-- Upper header -->
        <div class="bg-gradient-to-r from-diu-primary to-diu-primary-dark p-6 md:p-8 text-white relative">
            <a href="{{ $teacherUrl }}"
               class="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all mb-4 backdrop-blur-xs inline-flex">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7M19 12H5"/></svg>
                Back to Profile
            </a>

            <span class="bg-diu-accent text-white text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm tracking-wide">
                {{ optional($publication->type)->name ?? 'Research' }} Publication
            </span>
            <h2 class="text-lg md:text-xl font-display font-bold text-white tracking-tight mt-3 leading-snug">{{ $publication->title }}</h2>
            <p class="text-xs text-white/85 mt-2 font-medium">Authors: {{ $authors }}</p>
        </div>

        <div class="p-6 md:p-8 space-y-8">

            <!-- Core Metadata Block -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-white/30 backdrop-blur-xs rounded-xl border border-white/60 text-xs ring-1 ring-slate-900/5">
                @if($venue)
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Journal / Conference</p>
                        <p class="font-semibold text-slate-800 mt-1 leading-tight">{{ $venue }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Published Year</p>
                    <p class="font-semibold text-slate-800 mt-1 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
                        {{ $publication->publication_year ?? 'N/A' }}
                    </p>
                </div>
                @if($publication->impact_factor)
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Impact Factor</p>
                        <p class="font-semibold text-emerald-600 mt-1">{{ $publication->impact_factor }}</p>
                    </div>
                @endif
                @if($publication->citescore)
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase">CiteScore</p>
                        <p class="font-semibold text-blue-600 mt-1">{{ $publication->citescore }}</p>
                    </div>
                @endif
                @if($publication->h_index)
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase">H-Index</p>
                        <p class="font-semibold text-indigo-600 mt-1">{{ $publication->h_index }}</p>
                    </div>
                @endif
                @if($publication->research_area)
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Research Area</p>
                        <p class="text-xs font-semibold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-md inline-block mt-1">{{ $publication->research_area }}</p>
                    </div>
                @endif
            </div>

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
                 class="bg-white/30 backdrop-blur-xs rounded-xl border border-white/60 p-5 ring-1 ring-slate-900/5">
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
                        <p x-ref="apa" class="p-3 bg-white/50 border border-white/80 rounded-lg text-xs text-slate-700 font-sans select-all leading-relaxed">{{ $citations['apa'] }}</p>
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
                        <p x-ref="ieee" class="p-3 bg-white/50 border border-white/80 rounded-lg text-xs text-slate-700 font-sans select-all leading-relaxed">{{ $citations['ieee'] }}</p>
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
                        <pre x-ref="bibtex" class="p-3 bg-slate-900 text-slate-100 rounded-lg text-[11px] font-mono select-all overflow-x-auto whitespace-pre leading-normal shadow-inner">{{ $citations['bibtex'] }}</pre>
                    </div>
                </div>
            </div>

            <!-- Contributing Academic Member info -->
            <div class="p-4 bg-white/30 border border-white/60 rounded-xl flex items-center justify-between ring-1 ring-slate-900/5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-200 shrink-0">
                        @if($teacher->photo)
                            <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover" />
                        @else
                            <div class="w-full h-full bg-diu-primary text-white flex items-center justify-center font-display font-bold">{{ strtoupper(substr($teacher->first_name, 0, 1)) }}</div>
                        @endif
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Contributing Scholar</p>
                        <p class="text-xs font-bold text-slate-800 font-display">{{ $teacher->first_name }} {{ $teacher->last_name }}</p>
                    </div>
                </div>

                <a href="{{ $teacherUrl }}"
                   class="text-xs font-semibold text-diu-primary hover:text-diu-accent transition-colors flex items-center gap-1">
                    Back to Academic Profile <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg>
                </a>
            </div>

        </div>
    </div>

@endsection
