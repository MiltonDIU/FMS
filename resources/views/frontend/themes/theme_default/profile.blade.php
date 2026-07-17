@extends('frontend.themes.theme_default.layouts.app')

@section('title', $teacher->first_name . ' ' . $teacher->last_name . ' - Profile')

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
        <span class="text-diu-primary truncate max-w-xs">{{ $teacher->first_name }} {{ $teacher->last_name }}</span>
    </div>

    <div class="bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm overflow-hidden" id="teacher-profile-{{ $teacher->id }}">

        <!-- Cover / Hero header banner -->
        <div class="relative h-48 bg-gradient-to-r from-diu-primary-dark via-diu-primary to-diu-accent/80 p-6 md:p-8 flex items-end border-b border-white/20">
            <a href="{{ $deptUrl }}"
               class="absolute top-4 left-4 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all backdrop-blur-xs">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7M19 12H5"/></svg>
                Back to list
            </a>
            <div class="absolute right-6 top-6 text-white/10 font-display font-extrabold text-7xl select-none hidden sm:block">{{ \App\Helpers\Branding::get('short_name') }}</div>
        </div>

        <!-- Main Info Frame -->
        <div class="px-6 md:px-8 pb-8 relative">

            <!-- Profile Avatar shifted on top of cover -->
            <div class="flex flex-col md:flex-row md:items-end justify-between -mt-16 mb-6 gap-4">
                <div class="flex flex-col md:flex-row items-center md:items-end gap-5 text-center md:text-left">
                    <div class="w-32 h-32 rounded-2xl overflow-hidden border-4 border-white shadow-lg bg-slate-100 shrink-0">
                        @if($teacher->photo)
                            <img src="https://faculty.daffodilvarsity.edu.bd/images/teacher/{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover" />
                           @else
                            <div class="w-full h-full bg-diu-primary text-white flex items-center justify-center font-display font-bold text-4xl">
                                {{ strtoupper(substr($teacher->first_name, 0, 1)) }}
                            </div>
                        @endif
                    </div>

                    <div class="pt-2">
                        <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 mb-1.5">
                            @if($teacher->administrativeRoles->isNotEmpty())
                                @php
                                    $adminRoleName = optional($teacher->administrativeRoles->first())->administrativeRole?->name;
                                @endphp
                                @if($adminRoleName)
                                    <span class="bg-diu-accent text-white text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm shadow-xs border border-diu-accent/20">
                                        {{ $adminRoleName }}
                                    </span>
                                @endif
                            @endif
                            <span class="bg-white/60 text-slate-700 text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm border border-white/80">
                                {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
                            </span>
                        </div>
                        <h2 class="text-xl md:text-2xl font-display font-bold text-slate-900 tracking-tight leading-tight">
                            {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
                        </h2>
                        <p class="text-xs text-slate-500 font-sans font-medium mt-1">
                            {{ optional($teacher->department)->name ?? 'General' }} • <span class="text-slate-400">{{ $faculty->name ?? \App\Helpers\Branding::get('short_name') }}</span>
                        </p>
                    </div>
                </div>

                <!-- Social / Scholars Web Profile Buttons -->
                <div class="flex flex-wrap items-center justify-center gap-2">
                    @foreach($teacher->socialLinks as $link)
                        <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                           class="p-2 bg-white/40 hover:bg-white/80 text-slate-600 rounded-lg border border-white/60 transition-colors shadow-2xs"
                           title="{{ optional($link->platform)->name ?? 'Link' }}">
                            @include("frontend.themes.{$activeTheme}.partials.social_icon", ['platform' => optional($link->platform)->name ?? ''])
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Contact Strip -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 py-4 px-5 bg-white/30 backdrop-blur-xs rounded-xl border border-white/60 mb-8 text-xs text-slate-600 font-sans ring-1 ring-slate-900/5">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    <div class="min-w-0">
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Email Address</p>
                        <p class="font-mono truncate font-semibold text-slate-700">{{ $teacher->user->email ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Contact Number</p>
                        <p class="font-semibold text-slate-700">{{ $teacher->phone ?? ($teacher->personal_phone ?? 'N/A') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    <div class="min-w-0">
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Office Location</p>
                        <p class="font-semibold text-slate-700 truncate">{{ $teacher->office_room ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <!-- Tab Controls -->
            <div x-data="{ tab: 'overview' }">
                <div class="flex border-b border-white/40 mb-6 overflow-x-auto gap-1 pb-1">
                    @foreach([
                        ['id' => 'overview', 'label' => 'Overview'],
                        ['id' => 'academic', 'label' => 'Academic Background'],
                        ['id' => 'courses', 'label' => 'Teaching Area'],
                        ['id' => 'research', 'label' => 'Research'],
                        ['id' => 'publications', 'label' => 'Publications (' . $teacher->publications->count() . ')'],
                        ['id' => 'experience', 'label' => 'Experience'],
                        ['id' => 'training', 'label' => 'Training'],
                        ['id' => 'awards', 'label' => 'Awards'],
                        ['id' => 'memberships', 'label' => 'Memberships'],
                    ] as $tab)
                        <button @click="tab = '{{ $tab['id'] }}'"
                                :class="tab === '{{ $tab['id'] }}' ? 'border-diu-primary text-diu-primary font-bold bg-white/40 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-white/10'"
                                class="px-4 py-3 text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-2 border-b-2 -mb-px cursor-pointer">
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>

                @include('frontend.themes.theme_default.partials.profile.overview')
                @include('frontend.themes.theme_default.partials.profile.academic')
                @include('frontend.themes.theme_default.partials.profile.courses')
                @include('frontend.themes.theme_default.partials.profile.research')
                @include('frontend.themes.theme_default.partials.profile.publications')
                @include('frontend.themes.theme_default.partials.profile.experience')
                @include('frontend.themes.theme_default.partials.profile.training')
                @include('frontend.themes.theme_default.partials.profile.awards')
                @include('frontend.themes.theme_default.partials.profile.memberships')

            </div>
        </div>
    </div>

@endsection
