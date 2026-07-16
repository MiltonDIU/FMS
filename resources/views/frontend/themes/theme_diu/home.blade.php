@extends('frontend.themes.theme_diu.layouts.app')

@section('title', 'Faculty Directory — Daffodil International University')
@section('meta_description', 'Explore academic credentials, research profiles, and connect with faculty members of Daffodil International University.')

@section('content')

    @if($selectedFaculty)
        {{-- ─── Faculty selected → delegate to Livewire search ─── --}}
        <div class="text-xs text-slate-500 font-semibold mb-6 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
            <a href="{{ route('home') }}" class="hover:text-diu-primary transition">Home</a>
            <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            <span class="text-diu-primary">{{ $selectedFaculty->short_name }}</span>
        </div>
        <livewire:teacher-search :selected-faculty-id="$selectedFaculty->id" />

    @else

        {{-- ══════════════════════════════════════════════════════════
             HERO — DIU main site inspired: full-bleed dark navy panel
        ══════════════════════════════════════════════════════════ --}}
        <section class="relative overflow-hidden rounded-3xl mb-10 min-h-[280px] flex items-end"
                 style="background: linear-gradient(160deg, #021430 0%, #032652 40%, #0e3d75 75%, #155fa0 100%);">

            {{-- Fine dot-mesh texture --}}
            <div class="absolute inset-0 pointer-events-none opacity-[0.18]"
                 style="background-image: radial-gradient(circle, rgba(255,255,255,0.55) 1px, transparent 1px);
                        background-size: 24px 24px;"></div>

            {{-- Glowing accent circles --}}
            <div class="absolute top-0 right-0 w-[480px] h-[480px] -translate-y-1/3 translate-x-1/4 rounded-full pointer-events-none"
                 style="background: radial-gradient(circle, rgba(0,114,188,0.28) 0%, transparent 70%);"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 translate-y-1/3 -translate-x-1/4 rounded-full pointer-events-none"
                 style="background: radial-gradient(circle, rgba(3,78,162,0.22) 0%, transparent 70%);"></div>

            {{-- Thin brand top-border line --}}
            <div class="absolute top-0 left-0 right-0 h-0.5"
                 style="background: linear-gradient(90deg, transparent, #0072bc 30%, #034ea2 60%, transparent);"></div>

            {{-- Content --}}
            <div class="relative z-10 w-full px-8 py-14 md:px-16 md:py-16" style="padding: 10px">

                {{-- Eyebrow --}}
                <div class="inline-flex items-center gap-2 mb-6">
                    <span class="block w-5 h-0.5 bg-diu-accent rounded-full"></span>
                    <span class="text-diu-accent text-[11px] uppercase font-extrabold tracking-[0.2em]">Official Faculty Portal · DIU</span>
                </div>

                <h1 class="text-white font-display font-extrabold text-3xl md:text-[2.5rem] leading-tight tracking-tight mb-5 max-w-2xl">
                    Meet the Scholars Who<br>
                    <span style="color: #ffffff;">Shape Tomorrow.</span>
                </h1>

                <p class="text-white/75 text-sm leading-relaxed max-w-xl mb-10" style="padding-left: 2px; color: white">
                    Browse academic profiles, research interests, publications, and credentials of faculty members across every department of Daffodil International University.
                </p>

                {{-- CTA + stats row --}}
                <div class="flex flex-wrap items-center gap-5">
                    <a href="#faculties"
                       class="inline-flex items-center gap-2 font-bold text-sm px-5 py-2.5 rounded-xl shadow-lg transition-all hover:-translate-y-0.5 duration-200 bg-diu-primary hover:bg-diu-primary-hover text-white">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                        Browse Faculties
                    </a>

                    {{-- Stat pills --}}
                    <div class="flex flex-wrap gap-3">
                        @php
                            $heroStats = [
                                ['n' => number_format($totalTeachers),    'l' => 'Members'],
                                ['n' => $totalFaculties,                  'l' => 'Faculties'],
                                ['n' => $totalDepartments,                'l' => 'Departments'],
                                ['n' => number_format($totalPublications), 'l' => 'Publications'],
                            ];
                        @endphp
                        @foreach($heroStats as $s)
                            <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl"
                                 style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14);">
                                <span class="font-display font-extrabold text-white text-base leading-none">{{ $s['n'] }}</span>
                                <span class="text-white/50 text-[10px] font-bold uppercase tracking-wider">{{ $s['l'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════
             FACULTY GRID
        ══════════════════════════════════════════════════════════ --}}
        <section id="faculties" class="space-y-5">

            <div class="flex items-center gap-3">
                <div class="w-1 h-6 bg-diu-primary rounded-full"></div>
                <h2 class="font-display font-extrabold text-xl text-slate-900">Explore by Academic Faculty</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach($faculties as $faculty)
                    @php
                        $facSlug      = strtolower($faculty->short_name ?? $faculty->code ?? '');
                        $allDepts     = $faculty->departments()->where('is_active', true)->orderBy('sort_order')->get();
                        $visibleDepts = $allDepts->take(4);
                        $hiddenDepts  = $allDepts->skip(4);

                        // Each faculty card header gets a slightly different DIU brand shade
                        $headerGrads = [
                            'linear-gradient(135deg, #002652 0%, #034ea2 100%)',
                            'linear-gradient(135deg, #011d3c 0%, #0e3d75 100%)',
                            'linear-gradient(135deg, #021b40 0%, #0072bc 100%)',
                            'linear-gradient(135deg, #002652 0%, #0861a7 100%)',
                            'linear-gradient(135deg, #011d3c 0%, #034ea2 100%)',
                            'linear-gradient(135deg, #021b40 0%, #0072bc 100%)',
                        ];
                        $grad = $headerGrads[$loop->index % count($headerGrads)];
                    @endphp

                    <div class="group flex flex-col bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-xl hover:border-diu-primary/25 transition-all duration-300 overflow-hidden">

                        {{-- Header strip (clickable) --}}
                        <a href="{{ $faculty->url }}"
                           class="block relative h-36 overflow-hidden flex-shrink-0"
                           style="background: {{ $grad }};">

                            {{-- Dot texture --}}
                            <div class="absolute inset-0 opacity-[0.15]"
                                 style="background-image: radial-gradient(circle, rgba(255,255,255,0.8) 1px, transparent 1px);
                                        background-size: 18px 18px;"></div>

                            {{-- Bottom fade --}}
                            <div class="absolute inset-0 bg-gradient-to-t from-black/65 via-black/10 to-transparent"></div>

                            {{-- Code badge --}}
                            <div class="absolute top-3 right-3 text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded-md"
                                 style="background: rgba(3,78,162,0.92); color: #ffffff;">
                                {{ $faculty->code ?? $faculty->short_name }}
                            </div>

                            {{-- Faculty name --}}
                            <div class="absolute bottom-0 left-0 right-0 px-4 pb-3.5 z-10">
                                <h3 class="text-white font-display font-bold text-base leading-snug drop-shadow-sm">
                                    {{ $faculty->name }}
                                </h3>
                            </div>
                        </a>

                        {{-- Body --}}
                        <div class="px-4 py-4 flex-1 flex flex-col gap-3">

                            {{-- Description --}}
                            <p class="text-xs leading-relaxed line-clamp-2" style="color: #64748b;">
                                {{ $faculty->description ?? 'Academic faculty of Daffodil International University offering quality education across multiple disciplines.' }}
                            </p>

                            {{-- Departments --}}
                            @if($allDepts->isNotEmpty())
                                <div x-data="{ expanded: false }">
                                    <p class="text-[10px] uppercase font-bold tracking-widest mb-1.5" style="color: #94a3b8;">Departments</p>

                                    <div class="flex flex-wrap gap-1.5">
                                        {{-- Always visible --}}
                                        @foreach($visibleDepts as $dept)
                                            @php
                                                $deptUrl = route('department.show', [
                                                    'faculty_short_name' => $facSlug,
                                                    'department_code'    => strtolower($dept->code),
                                                ]);
                                            @endphp
                                            <a href="{{ $deptUrl }}" title="{{ $dept->name }}"
                                               class="inline-flex items-center text-diu-primary bg-diu-primary/10 border border-diu-primary/20 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md transition-colors duration-150 hover:bg-diu-primary hover:text-white hover:border-diu-primary">
                                                {{ $dept->code }}
                                            </a>
                                        @endforeach

                                        {{-- Hidden depts (expanded) --}}
                                        @if($hiddenDepts->isNotEmpty())
                                            <template x-if="expanded">
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach($hiddenDepts as $dept)
                                                        @php
                                                            $deptUrl = route('department.show', [
                                                                'faculty_short_name' => $facSlug,
                                                                'department_code'    => strtolower($dept->code),
                                                            ]);
                                                        @endphp
                                                        <a href="{{ $deptUrl }}" title="{{ $dept->name }}"
                                                           class="inline-flex items-center text-diu-primary bg-diu-primary/10 border border-diu-primary/20 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md transition-colors duration-150 hover:bg-diu-primary hover:text-white hover:border-diu-primary">
                                                            {{ $dept->code }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </template>

                                            <button @click="expanded = !expanded"
                                                    class="inline-flex items-center text-slate-500 bg-slate-100 border border-slate-200 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md cursor-pointer transition-colors duration-150 hover:bg-slate-200 hover:text-slate-700">
                                                <span x-show="!expanded">+{{ $hiddenDepts->count() }} More</span>
                                                <span x-show="expanded" style="display:none;">Show less</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Footer --}}
                            <div class="pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
                                <div class="flex gap-4">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                                        <span class="text-xs font-semibold" style="color:#475569;">{{ $faculty->departments_count }} Dept{{ $faculty->departments_count != 1 ? 's' : '' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-diu-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                        <span class="text-xs font-semibold" style="color:#475569;">{{ number_format($faculty->teachers_count) }} Members</span>
                                    </div>
                                </div>

                                <a href="{{ $faculty->url }}"
                                   class="inline-flex items-center gap-1.5 text-xs font-bold text-diu-primary hover:text-diu-primary-hover transition-colors duration-200 group/cta">
                                    View Faculty
                                    <svg class="w-3.5 h-3.5 group-hover/cta:translate-x-0.5 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

    @endif

@endsection
