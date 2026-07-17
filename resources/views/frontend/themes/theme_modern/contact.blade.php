@extends('frontend.themes.theme_modern.layouts.app')

@section('title', ($department->name ?? 'Department') . ' - Contact Directory')

@section('content')

    @php
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;
        $deptUrl = $facSlug
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => strtolower($department->code)])
            : route('home');

        $deptName    = $sections['department']['department_name'] ?? $department->name;
        $facultyName = $sections['department']['faculty_name'] ?? ($faculty->name ?? \App\Helpers\Branding::get('site_name'));
        $deptAddress = $sections['department']['address'] ?? ($sections['department']['location'] ?? \App\Helpers\Branding::get('address_full'));
        $deptEmail   = $sections['department']['email'] ?? null;
        $deptPhone   = $sections['department']['phone'] ?? ($sections['department']['mobile'] ?? null);

        // Institutional contact from the "Contact & External Links" system
        // settings — common across every page, independent of the department API.
        $orgEmail = \App\Helpers\Branding::get('email');
        $orgPhone = \App\Helpers\Branding::get('phone');

        $totalContacts = collect($blocks)->sum(fn ($b) => count($sections[$b['key']] ?? []));
    @endphp

    <!-- Breadcrumb -->
    <div class="text-xs text-slate-500 font-semibold mb-6 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
        <a href="{{ route('home') }}" class="hover:text-diu-primary transition">Home</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $faculty->url }}" class="hover:text-diu-primary transition">{{ $faculty->short_name }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $deptUrl }}" class="hover:text-diu-primary transition">{{ $department->code }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <span class="text-diu-primary truncate max-w-xs">Contact</span>
    </div>

    {{-- ════════════════════════════════════════════════════════
          TOP BENTO ROW — Split grid: Hero banner (large) + Info glass cards
     ════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-5">

        {{-- Block 1 (large): Department hero banner --}}
        <div class="lg:col-span-7 relative rounded-3xl overflow-hidden card min-h-[240px] flex items-end"
             style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-diu-primary-dark) 92%, #0a0f1a) 0%, color-mix(in srgb, var(--color-diu-primary) 78%, #0a0f1a) 55%, color-mix(in srgb, var(--color-diu-accent) 68%, #0a0f1a) 100%);">

            @if(! empty($sections['department']['department_photo']))
                <img src="{{ $sections['department']['department_photo'] }}" alt="{{ $deptName }}" class="absolute inset-0 w-full h-full object-cover opacity-20 mix-blend-overlay" />
            @endif

            {{-- Fine dot-mesh texture --}}
            <div class="absolute inset-0 pointer-events-none opacity-20"
                 style="background-image: radial-gradient(circle, rgba(255,255,255,0.5) 1px, transparent 1px); background-size: 24px 24px;"></div>

            {{-- Thin brand top-border line --}}
            <div class="absolute top-0 left-0 right-0 h-0.5"
                 style="background: linear-gradient(90deg, transparent, var(--color-diu-accent) 30%, var(--color-diu-primary) 60%, transparent);"></div>

            <a href="{{ $deptUrl }}"
               class="absolute top-4 left-4 z-20 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-xl flex items-center gap-1.5 transition-all backdrop-blur-xs">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7M19 12H5"/></svg>
                Back to Department
            </a>
            <div class="absolute right-6 top-5 text-white/10 font-display font-extrabold text-7xl select-none hidden sm:block">{{ \App\Helpers\Branding::get('short_name') }}</div>

            <div class="relative z-10 w-full px-6 pb-6 md:px-8 md:pb-8">
                <span class="bg-diu-accent text-white text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm tracking-wide shadow-xs border border-diu-accent/20">
                    Contact Directory
                </span>
                <h2 class="text-lg md:text-2xl font-display font-bold text-white tracking-tight mt-3 leading-snug">
                    {{ $deptName }}
                </h2>
                <p class="text-xs text-white/85 mt-1.5 font-medium">
                    {{ $facultyName }}
                </p>
            </div>
        </div>

        {{-- Block 2 & 3 (small): Department info glass cards --}}
        <div class="lg:col-span-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-5">

            {{-- Email + Phone glass card --}}
            <div class="glass-panel rounded-3xl p-5 flex flex-col justify-center gap-4">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-1 h-5 bg-diu-primary rounded-full"></div>
                    <p class="text-[10px] uppercase font-bold tracking-widest text-slate-500">Reach the Office</p>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-diu-primary/10 border border-diu-primary/20 flex items-center justify-center shrink-0 text-diu-primary">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Email</p>
                        @if($orgEmail)
                            <a href="mailto:{{ $orgEmail }}" class="text-xs font-semibold text-slate-700 hover:text-diu-primary transition-colors truncate block font-mono">{{ $orgEmail }}</a>
                        @elseif($deptEmail)
                            <a href="mailto:{{ $deptEmail }}" class="text-xs font-semibold text-slate-700 hover:text-diu-primary transition-colors truncate block font-mono">{{ $deptEmail }}</a>
                        @else
                            <p class="text-xs font-semibold text-slate-400">Not available</p>
                        @endif
                        @if($deptEmail && $orgEmail && $deptEmail !== $orgEmail)
                            <a href="mailto:{{ $deptEmail }}" class="text-[10px] text-slate-400 hover:text-diu-primary transition-colors truncate block mt-0.5">{{ $deptEmail }}</a>
                        @endif
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-diu-primary/10 border border-diu-primary/20 flex items-center justify-center shrink-0 text-diu-primary">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Phone</p>
                        @if($orgPhone)
                            <a href="tel:{{ $orgPhone }}" class="text-xs font-semibold text-slate-700 hover:text-diu-primary transition-colors">{{ $orgPhone }}</a>
                        @elseif($deptPhone)
                            <a href="tel:{{ $deptPhone }}" class="text-xs font-semibold text-slate-700 hover:text-diu-primary transition-colors">{{ $deptPhone }}</a>
                        @else
                            <p class="text-xs font-semibold text-slate-400">Not available</p>
                        @endif
                        @if($deptPhone && $orgPhone && $deptPhone !== $orgPhone)
                            <a href="tel:{{ $deptPhone }}" class="text-[10px] text-slate-400 hover:text-diu-primary transition-colors block mt-0.5">{{ $deptPhone }}</a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Location + Stats glass card --}}
            <div class="glass-panel rounded-3xl p-5 flex flex-col justify-center gap-4">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-diu-accent/10 border border-diu-accent/20 flex items-center justify-center shrink-0 text-diu-accent">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Location</p>
                        <p class="text-xs font-semibold text-slate-700 leading-snug">
                            {{ $deptAddress ?? \App\Helpers\Branding::get('address_full') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 border-t border-slate-100">
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-600">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Directory</p>
                        <p class="text-xs font-semibold text-slate-700">
                            <span class="font-display font-extrabold text-slate-900">{{ $totalContacts }}</span> contact{{ $totalContacts != 1 ? 's' : '' }} listed
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════
          CONTACT BENTO GRID — one card per role group
     ════════════════════════════════════════════════════════ --}}
    <div class="card overflow-hidden">
        <div class="p-6 md:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                @if($apiError)
                    <div class="md:col-span-2 p-4 bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-2xl">
                        {{ $apiError }}
                    </div>
                @endif

                @foreach($blocks as $block)
                    @php $people = $sections[$block['key']] ?? []; @endphp
                    @if(count($people) > 0)
                        <section>
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-1 h-6 bg-diu-primary rounded-full"></div>
                                <h3 class="font-display font-extrabold text-lg text-slate-900">{{ $block['title'] }}</h3>
                                <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ count($people) }}</span>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                @foreach($people as $person)
                                    <div class="flex items-center gap-4 card card-hover rounded-2xl p-4">
                                        <div class="w-16 h-16 rounded-2xl overflow-hidden bg-slate-100 shrink-0 ring-1 ring-slate-200">
                                            @if(! empty($person['photo']))
                                                <img src="https://webbackend.daffodilvarsity.edu.bd/{{ ltrim($person['photo'], '/') }}" alt="{{ $person['name'] }}" class="w-full h-full object-cover" />
                                            @else
                                                <div class="w-full h-full bg-diu-primary text-white flex items-center justify-center font-display font-bold text-lg">
                                                    {{ strtoupper(substr($person['name'], 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-slate-900 leading-tight truncate">{{ $person['name'] }}</p>
                                            <p class="text-[11px] font-semibold text-diu-primary truncate">{{ $person['designation'] ?? 'Faculty Member' }}</p>

                                            <div class="mt-1.5 space-y-0.5 text-[11px] text-slate-500">
                                                @if(! empty($person['email']))
                                                    <a href="mailto:{{ $person['email'] }}" class="flex items-center gap-1.5 hover:text-diu-primary transition-colors truncate">
                                                        <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                                        <span class="truncate font-mono">{{ $person['email'] }}</span>
                                                    </a>
                                                @endif
                                                @if(! empty($person['mobile']))
                                                    <div class="flex items-center gap-1.5">
                                                        <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                                        <span class="font-sans">{{ $person['mobile'] }}</span>
                                                    </div>
                                                @endif
                                                @if(! empty($person['ip_phone']))
                                                    <div class="flex items-center gap-1.5">
                                                        <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                                        <span class="font-sans">{{ $person['ip_phone'] }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach

                @if(! $apiError && collect($blocks)->every(fn ($b) => count($sections[$b['key']] ?? []) === 0))
                    <div class="md:col-span-2 p-12 text-center text-slate-400 text-sm">No contact records found for this department.</div>
                @endif

            </div>
        </div>
    </div>

@endsection
