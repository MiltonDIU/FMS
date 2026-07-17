@extends('frontend.themes.theme_modern.layouts.app')

@section('title', 'Faculty Directory' . \App\Helpers\Branding::get('meta_title_suffix'))
@section('meta_description', \App\Helpers\Branding::get('meta_description'))

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

        {{-- ════════════════════════════════════════════════════════
              HERO — Modern minimal hero with soft brand gradient + dot mesh
         ════════════════════════════════════════════════════════ --}}
        <section class="relative overflow-hidden rounded-[2rem] mb-10 min-h-[320px] flex items-end card"
                 style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-diu-primary-dark) 92%, #0a0f1a) 0%, color-mix(in srgb, var(--color-diu-primary) 80%, #0a0f1a) 55%, color-mix(in srgb, var(--color-diu-accent) 70%, #0a0f1a) 100%);">

            {{-- Fine dot-mesh texture --}}
            <div class="absolute inset-0 pointer-events-none opacity-20"
                 style="background-image: radial-gradient(circle, rgba(255,255,255,0.5) 1px, transparent 1px); background-size: 26px 26px;"></div>

            {{-- Glowing accent blobs --}}
            <div class="absolute top-0 right-0 w-[460px] h-[460px] -translate-y-1/3 translate-x-1/4 rounded-full pointer-events-none"
                 style="background: radial-gradient(circle, color-mix(in srgb, var(--color-diu-accent) 30%, transparent) 0%, transparent 70%);"></div>
            <div class="absolute bottom-0 left-0 w-72 h-72 translate-y-1/3 -translate-x-1/4 rounded-full pointer-events-none"
                 style="background: radial-gradient(circle, color-mix(in srgb, var(--color-diu-primary-light) 26%, transparent) 0%, transparent 70%);"></div>

            {{-- Thin brand top-border line --}}
            <div class="absolute top-0 left-0 right-0 h-0.5"
                 style="background: linear-gradient(90deg, transparent, var(--color-diu-accent) 30%, var(--color-diu-primary) 60%, transparent);"></div>

            <div class="relative z-10 w-full px-8 py-16 md:px-16 md:py-20">
                <div class="inline-flex items-center gap-2 mb-6">
                    <span class="block w-5 h-0.5 bg-diu-accent rounded-full"></span>
                    <span class="text-diu-accent text-[11px] uppercase font-extrabold tracking-[0.2em]">Official Faculty Portal · {{ \App\Helpers\Branding::get('short_name') }}</span>
                </div>

                <h1 class="text-white font-display font-extrabold text-3xl md:text-[2.75rem] leading-[1.1] tracking-tight mb-5 max-w-2xl">
                    Meet the Scholars Who<br>
                    <span class="text-white/90">Shape Tomorrow.</span>
                </h1>

                <p class="text-white/75 text-sm leading-relaxed max-w-xl mb-9">
                    Browse academic profiles, research interests, publications, and credentials of faculty members across every department of {{ \App\Helpers\Branding::get('site_name') }}.
                </p>

                <div class="flex flex-wrap items-center gap-5">
                    <a href="#faculties"
                       class="inline-flex items-center gap-2 font-bold text-sm px-5 py-2.5 rounded-2xl shadow-lg transition-all hover:-translate-y-0.5 duration-200 bg-white text-diu-primary hover:bg-diu-accent-light">
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
                            <div class="flex items-center gap-2 px-3.5 py-2 rounded-2xl"
                                 style="background: rgba(255,255,255,0.10); border: 1px solid rgba(255,255,255,0.16);">
                                <span class="font-display font-extrabold text-white text-base leading-none">{{ $s['n'] }}</span>
                                <span class="text-white/50 text-[10px] font-bold uppercase tracking-wider">{{ $s['l'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ════════════════════════════════════════════════════════
              FACULTY BENTO GRID
         ════════════════════════════════════════════════════════ --}}
        <section id="faculties" class="space-y-5">

            <div class="flex items-center gap-3">
                <div class="w-1 h-6 bg-diu-primary rounded-full"></div>
                <h2 class="font-display font-extrabold text-xl text-slate-900">Explore by Academic Faculty</h2>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                @foreach($faculties as $faculty)
                    @php
                        $facSlug      = strtolower($faculty->short_name ?? $faculty->code ?? '');
                        $allDepts     = $faculty->departments()->where('is_active', true)->orderBy('sort_order')->get();
                        $visibleDepts = $allDepts->take(4);
                        $hiddenDepts  = $allDepts->skip(4);

                        $headerGrads = [
                            'linear-gradient(135deg, var(--color-diu-primary-dark) 0%, var(--color-diu-primary) 100%)',
                            'linear-gradient(135deg, color-mix(in srgb, var(--color-diu-primary-dark) 70%, black) 0%, var(--color-diu-primary-light) 100%)',
                            'linear-gradient(135deg, color-mix(in srgb, var(--color-diu-primary-dark) 80%, black) 0%, var(--color-diu-accent) 100%)',
                            'linear-gradient(135deg, var(--color-diu-primary-dark) 0%, color-mix(in srgb, var(--color-diu-primary-light) 80%, black) 100%)',
                            'linear-gradient(135deg, color-mix(in srgb, var(--color-diu-primary-dark) 70%, black) 0%, var(--color-diu-primary) 100%)',
                            'linear-gradient(135deg, color-mix(in srgb, var(--color-diu-primary-dark) 80%, black) 0%, var(--color-diu-accent) 100%)',
                        ];
                        $grad = $headerGrads[$loop->index % count($headerGrads)];
                    @endphp

                    <div class="group relative flex flex-col card card-hover rounded-3xl overflow-hidden min-h-[230px]">

                        {{-- Header strip --}}
                        <a href="{{ $faculty->url }}"
                           class="block relative h-28 overflow-hidden flex-shrink-0"
                           style="background: {{ $grad }};">

                            <div class="absolute inset-0 opacity-20"
                                 style="background-image: radial-gradient(circle, rgba(255,255,255,0.85) 1px, transparent 1px); background-size: 18px 18px;"></div>

                            <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent"></div>

                            <div class="absolute top-3 right-3 text-[10px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded-xl"
                                 style="background: color-mix(in srgb, var(--color-diu-primary) 92%, black); color: #ffffff;">
                                {{ $faculty->code ?? $faculty->short_name }}
                            </div>

                            <div class="absolute bottom-0 left-0 right-0 px-4 pb-3.5 z-10">
                                <h3 class="text-white font-display font-bold text-base leading-snug drop-shadow-sm line-clamp-2">
                                    {{ $faculty->name }}
                                </h3>
                            </div>
                        </a>

                        {{-- Body --}}
                        <div class="px-4 py-4 flex-1 flex flex-col gap-3">
                            <p class="text-xs leading-relaxed line-clamp-2" style="color: #64748b;">
                                {{ $faculty->description ?? 'Academic faculty of ' . \App\Helpers\Branding::get('site_name') . ' offering quality education across multiple disciplines.' }}
                            </p>

                            @if($allDepts->isNotEmpty())
                                <div x-data="{ expanded: false }">
                                    <p class="text-[10px] uppercase font-bold tracking-widest mb-1.5" style="color: #94a3b8;">Departments</p>

                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($visibleDepts as $dept)
                                            @php
                                                $deptUrl = route('department.show', [
                                                    'faculty_short_name' => $facSlug,
                                                    'department_code'    => strtolower($dept->code),
                                                ]);
                                            @endphp
                                            <a href="{{ $deptUrl }}" title="{{ $dept->name }}"
                                               class="inline-flex items-center text-diu-primary bg-diu-primary/10 border border-diu-primary/20 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full transition-colors duration-150 hover:bg-diu-primary hover:text-white hover:border-diu-primary">
                                                {{ $dept->code }}
                                            </a>
                                        @endforeach

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
                                                           class="inline-flex items-center text-diu-primary bg-diu-primary/10 border border-diu-primary/20 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full transition-colors duration-150 hover:bg-diu-primary hover:text-white hover:border-diu-primary">
                                                            {{ $dept->code }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </template>

                                            <button @click="expanded = ! expanded"
                                                    class="inline-flex items-center text-slate-500 bg-slate-100 border border-slate-200 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full cursor-pointer transition-colors duration-150 hover:bg-slate-200 hover:text-slate-700">
                                                <span x-show="!expanded">+{{ $hiddenDepts->count() }} More</span>
                                                <span x-show="expanded" style="display:none;">Show less</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif

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
