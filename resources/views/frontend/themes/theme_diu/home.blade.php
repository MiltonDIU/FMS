@extends('frontend.themes.theme_diu.layouts.app')

@section('title', 'Daffodil International University Faculty Directory')

@section('content')

    @if($selectedFaculty)
        <!-- Breadcrumb -->
        <div class="text-xs text-slate-500 font-semibold mb-6 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
            <a href="{{ route('home') }}" class="hover:text-diu-primary transition">Home</a>
            <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            <span class="text-diu-primary">{{ $selectedFaculty->short_name }}</span>
        </div>

        <livewire:teacher-search :selected-faculty-id="$selectedFaculty->id" />
    @else
        <!-- Breadcrumb Navigation Strip -->
        <div class="text-xs text-slate-500 font-semibold mb-8 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
            <a href="{{ route('home') }}" class="hover:text-diu-primary font-semibold transition-colors">Home</a>
        </div>

        <!-- Hero Banner -->
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

        <!-- HOME PAGE: Explore by Academic Faculties -->
        <div class="space-y-4">
            <div class="flex items-center gap-2">
                <div class="h-4 w-1 bg-diu-primary rounded-xs"></div>
                <h3 class="font-display font-bold text-md text-gray-800">Explore by Academic Faculties</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($faculties as $faculty)
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
                                        <span class="text-xs font-medium">{{ $faculty->departments_count }} Depts</span>
                                    </div>
                                    <div class="flex items-center gap-1 text-slate-600">
                                        <svg class="w-3.5 h-3.5 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                        <span class="text-xs font-medium">{{ $faculty->teachers_count }} Members</span>
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
        </div>
    @endif

@endsection
