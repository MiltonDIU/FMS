@extends('frontend.themes.theme_diu.layouts.app')

@section('title', 'Daffodil International University Faculty Directory')

@section('content')

    <!-- Breadcrumb Navigation Strip -->
    <div class="text-xs text-slate-500 font-semibold mb-8 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
        <a href="{{ route('home') }}" class="hover:text-diu-primary font-semibold transition-colors">Home</a>
        @if(!$isHome && $selectedFaculty)
            <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            <span class="text-diu-primary font-semibold">{{ $selectedFaculty->short_name }}</span>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        @include('frontend.themes.theme_diu.partials.sidebar', [
            'faculties' => $faculties,
            'currentFaculty' => $selectedFaculty,
            'departments' => $departments,
            'adminRoles' => $adminRoles,
            'designations' => $designations,
            'currentDepartment' => null,
            'isHome' => $isHome,
            'sticky' => true,
        ])

        <!-- RIGHT MAIN STAGE -->
        <div class="lg:col-span-3 space-y-6">

            @if($isHome)
                <!-- MAIN INTRO BANNER -->
                @if(!$q)
                    <div class="bg-gradient-to-br from-diu-primary-dark via-diu-primary to-diu-secondary border border-white/20 backdrop-blur-md p-6 md:p-8 rounded-2xl text-white shadow-lg relative overflow-hidden mb-8">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -mr-20 -mt-20 blur-3xl pointer-events-none"></div>
                        <div class="absolute bottom-0 left-0 w-32 h-32 bg-diu-accent/20 rounded-full -ml-12 -mb-12 blur-2xl pointer-events-none"></div>
                        <div class="relative z-10 max-w-xl">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.5c-1.8 0-3.5 1.4-3.5 3.5s1.7 3.5 3.5 3.5 3.5-1.4 3.5-3.5a2 2 0 0 0-.063-.5"/><path d="M10 10a2 2 0 0 0 2-2c0-1-.9-1.9-2-2s-2 .9-2 2a2 2 0 0 0 2 2Z"/><path d="M4.5 11h.5a2 2 0 0 0 2-2c0-1-.9-1.9-2-2s-2 .9-2 2a2 2 0 0 0 2 2Z"/><path d="M6 17.5a2 2 0 0 0 2 2c1.8 0 3.5-1.4 3.5-3.5S9.8 12.5 8 12.5a2 2 0 0 0-2 2c0 .7.3 1.3.5 1.5Z"/><path d="m15 8 .5.5a2 2 0 0 1 0 2.8l-3 3a2 2 0 0 1-2.8 0l-.5-.5"/><path d="m13 14-.5-.5a2 2 0 0 1 0-2.8l3-3a2 2 0 0 1 2.8 0l.5.5"/></svg>
                                <span class="text-[10px] uppercase font-bold tracking-widest text-diu-accent">Smart Academic Portal</span>
                            </div>
                            <h2 class="text-xl md:text-2xl font-display font-extrabold leading-tight tracking-tight">
                                Daffodil International University Faculty Directory
                            </h2>
                            <p class="text-xs text-white/85 font-sans mt-2.5 leading-relaxed">
                                Welcome to the official, modernized Scholar profile portal. Explore academic credentials, award catalogs, research interest matrices, and generate citations for publication details.
                            </p>
                        </div>
                    </div>
                @endif

                <!-- HOME PAGE: Explore by Academic Faculties -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <div class="h-4 w-1 bg-diu-primary rounded-xs"></div>
                        <h3 class="font-display font-bold text-md text-gray-800">
                            {{ $q ? 'Search results for "' . $q . '"' : 'Explore by Academic Faculties' }}
                        </h3>
                    </div>

                    @if($visibleFaculties->isEmpty())
                        <div class="text-center py-16 bg-white/40 backdrop-blur-md border border-white/60 rounded-2xl shadow-sm">
                            <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <h4 class="text-sm font-bold text-slate-800 font-display">No faculties found</h4>
                            <p class="text-xs text-slate-500 font-sans max-w-sm mx-auto mt-1 leading-relaxed">No faculties match your search. Try a different keyword.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach($visibleFaculties as $faculty)
                                @php
                                    $deptCount = $faculty->departments_count;
                                    $memberCount = $faculty->teachers_count;
                                @endphp
                                <div class="bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm hover:shadow-xl hover:border-white/80 transition-all duration-300 overflow-hidden group flex flex-col justify-between ring-1 ring-diu-primary/10 hover:ring-diu-primary/25">
                                    <!-- Visual Header -->
                                    <div class="relative h-44 overflow-hidden bg-gradient-to-br from-diu-primary-dark via-diu-primary to-diu-secondary">
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                                        <div class="absolute top-4 right-4 bg-diu-accent text-white text-xs font-display font-extrabold px-2.5 py-1 rounded-md shadow-md tracking-wider">
                                            {{ $faculty->code }}
                                        </div>
                                        <div class="absolute bottom-4 left-4 right-4">
                                            <h3 class="text-white font-display font-bold text-lg leading-snug drop-shadow-xs">
                                                {{ $faculty->name }}
                                            </h3>
                                        </div>
                                    </div>

                                    <div class="p-5 flex-1 flex flex-col justify-between">
                                        <p class="text-xs text-slate-500 font-sans leading-relaxed mb-4 line-clamp-2">
                                            {{ $faculty->description ?? 'Academic faculty of Daffodil International University.' }}
                                        </p>

                                        <div class="pt-4 border-t border-white/40 flex items-center justify-between">
                                            <div class="flex gap-4">
                                                <div class="flex items-center gap-1 text-slate-600">
                                                    <svg class="w-3.5 h-3.5 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                                                    <span class="text-xs font-medium">{{ $deptCount }} Depts</span>
                                                </div>
                                                <div class="flex items-center gap-1 text-slate-600">
                                                    <svg class="w-3.5 h-3.5 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                                    <span class="text-xs font-medium">{{ $memberCount }} Members</span>
                                                </div>
                                            </div>

                                            <a href="{{ $faculty->url }}" class="text-xs font-semibold text-diu-primary hover:text-diu-accent flex items-center gap-1 transition-colors group-hover:translate-x-1 duration-200">
                                                Explore
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <!-- FACULTY SELECTED: Teachers of the faculty -->
                <div class="space-y-6">
                    <div>
                        <span class="text-[10px] bg-diu-primary/10 text-diu-primary font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Faculty Active</span>
                        <h2 class="text-2xl font-extrabold text-gray-900 mt-2 font-display">{{ $selectedFaculty->name }}</h2>
                        <p class="text-sm text-gray-500 mt-1 flex items-center gap-2">
                            <svg class="w-4 h-4 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            {{ $teachers->total() }} Faculty Members
                        </p>
                    </div>

                    @if($teachers->total() === 0)
                        <div class="bg-white/40 backdrop-blur-md border border-white/60 rounded-2xl p-12 text-center shadow-sm">
                            <p class="text-gray-500 font-semibold">No faculty members found under this faculty.</p>
                        </div>
                    @else
                        <!-- Faculty Members (paginated) -->
                        <div class="space-y-6">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center">
                                <span class="w-1.5 h-4 bg-diu-primary rounded-full mr-2"></span>
                                Departmental Faculty Members
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                @foreach($teachers as $teacher)
                                    @if($teacher->department)
                                        @include('frontend.themes.theme_diu.partials.teacher_card', ['teacher' => $teacher, 'faculty' => $selectedFaculty, 'department' => $teacher->department])
                                    @endif
                                @endforeach
                            </div>

                            {{ $teachers->links('frontend.themes.theme_diu.partials.pagination') }}
                        </div>

                        <!-- Departments under this Faculty -->
                        @if($departments->isNotEmpty())
                            <div class="space-y-4 pt-8 border-t border-slate-200/60 mt-8">
                                <div class="flex items-center gap-2">
                                    <div class="h-4 w-1 bg-diu-accent rounded-xs"></div>
                                    <h4 class="font-display font-bold text-sm text-gray-800">Departments under this Faculty</h4>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                                    @foreach($departments as $dept)
                                        @php
                                            $deptUrl = $selectedFaculty->short_name
                                                ? route('department.show', ['faculty_short_name' => strtolower($selectedFaculty->short_name), 'department_code' => strtolower($dept->code)])
                                                : '#';
                                        @endphp
                                        <a href="{{ $deptUrl }}"
                                           class="group bg-white/60 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm ring-1 ring-diu-accent/5 p-5 text-left hover:shadow-md hover:border-diu-accent/30 transition-all duration-200">
                                            <div class="flex items-start justify-between gap-3 mb-3">
                                                <div class="w-10 h-10 rounded-xl bg-diu-accent/10 flex items-center justify-center shrink-0 group-hover:bg-diu-accent/20 transition-colors">
                                                    <svg class="w-5 h-5 text-diu-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                                </div>
                                                <span class="bg-diu-accent/10 text-diu-accent text-[10px] font-bold px-2 py-1 rounded-md font-mono shrink-0">{{ $dept->short_name ?? $dept->code }}</span>
                                            </div>
                                            <h5 class="text-xs font-display font-bold text-gray-800 leading-tight line-clamp-2 group-hover:text-diu-accent transition-colors">{{ $dept->name }}</h5>
                                            <div class="mt-2.5 flex items-center gap-1 text-[10px] text-slate-400 font-sans">
                                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                                <span>View department scholars</span>
                                                <svg class="w-3 h-3 ml-auto opacity-0 group-hover:opacity-100 transition-opacity" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            @endif

        </div>
    </div>

@endsection
