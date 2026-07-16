@extends('frontend.themes.theme_default.layouts.app')

@section('title', ($department->name ?? 'Department') . ' — Contact Directory')

@section('content')

    @php
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;
        $deptUrl = $facSlug
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => strtolower($department->code)])
            : route('home');
    @endphp

    <!-- Breadcrumbs -->
    <div class="text-xs text-slate-500 font-semibold mb-8 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
        <a href="{{ route('home') }}" class="hover:text-diu-primary transition">Home</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $faculty->url }}" class="hover:text-diu-primary transition">{{ $faculty->short_name }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $deptUrl }}" class="hover:text-diu-primary transition">{{ $department->code }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <span class="text-diu-primary truncate max-w-xs">Contact</span>
    </div>

    <div class="bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-xs overflow-hidden font-sans">

        <!-- Header banner -->
        <div class="bg-gradient-to-r from-diu-primary to-diu-primary-dark p-6 md:p-8 text-white relative">
            @if(! empty($sections['department']['department_photo']))
                <img src="{{ $sections['department']['department_photo'] }}" alt="{{ $sections['department']['department_name'] ?? $department->name }}" class="absolute inset-0 w-full h-full object-cover opacity-15 mix-blend-overlay" />
            @endif
            <a href="{{ $deptUrl }}"
               class="absolute top-4 left-4 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all backdrop-blur-xs">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7M19 12H5"/></svg>
                Back to Department
            </a>
            <div class="absolute right-6 top-6 text-white/10 font-display font-extrabold text-7xl select-none hidden sm:block">DIU</div>

            <div class="relative z-10 w-full">
                <span class="bg-diu-accent text-white text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm tracking-wide shadow-xs border border-diu-accent/20">
                    Contact Directory
                </span>
                <h2 class="text-lg md:text-2xl font-display font-bold text-white tracking-tight mt-3 leading-snug">
                    {{ $sections['department']['department_name'] ?? $department->name }}
                </h2>
                <p class="text-xs text-white/85 mt-1.5 font-medium">
                    {{ $sections['department']['faculty_name'] ?? ($faculty->name ?? 'Daffodil International University') }}
                </p>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                @if($apiError)
                    <div class="md:col-span-2 p-4 bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-xl">
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

                            <div class="grid grid-cols-1 gap-5">
                                @foreach($people as $person)
                                    <div class="flex items-center gap-4 bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md hover:border-diu-primary/30 transition-all">
                                        <div class="w-14 h-14 rounded-full overflow-hidden bg-slate-100 shrink-0 ring-1 ring-slate-200">
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
