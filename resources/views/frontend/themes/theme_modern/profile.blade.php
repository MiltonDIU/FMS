@extends('frontend.themes.theme_modern.layouts.app')

@section('title', $teacher->first_name . ' ' . $teacher->last_name . ' - Profile')

@section('meta_description', $metaDescription ?? null)

@section('seo')
    @include('frontend.partials.seo')
@endsection

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

    <!-- Bento shell: 12-col grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6" id="teacher-profile-{{ $teacher->id }}">

        <!-- Sticky Left Sidebar -->
        <aside class="lg:col-span-3">
            <div class="lg:sticky lg:top-[calc(var(--header-h)+1rem)] space-y-5">
                <!-- Profile card -->
                <div class="card overflow-hidden">
                    <div class="relative h-24 flex items-start p-4"
                         style="background: linear-gradient(135deg, var(--color-diu-primary-dark) 0%, var(--color-diu-primary) 100%);">
                        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle, rgba(255,255,255,0.6) 1px, transparent 1px); background-size: 16px 16px;"></div>
                        <div class="absolute right-4 bottom-2 text-white/10 font-display font-extrabold text-5xl select-none hidden sm:block pointer-events-none">{{ \App\Helpers\Branding::get('short_name') }}</div>
                        <a href="{{ $deptUrl }}"
                           class="relative z-10 inline-flex items-center gap-1.5 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-xl transition-all backdrop-blur-xs">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l-7-7 7-7M19 12H5"/></svg>
                            Back to list
                        </a>
                    </div>

                    <div class="px-5 pb-5 -mt-12 relative z-10">
                        <div class="w-24 h-24 rounded-3xl overflow-hidden border-4 border-white shadow-lg bg-slate-100 shrink-0 relative z-10">

                           {{ $teacher->photo }}

                            @if($teacher->photo)
                                <img src="https://faculty.daffodilvarsity.edu.bd/images/teacher/{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover" />
                            @else
                                <div class="w-full h-full bg-diu-primary text-white flex items-center justify-center font-display font-bold text-4xl">
                                    {{ strtoupper(substr($teacher->first_name, 0, 1)) }}
                                </div>
                            @endif
                        </div>

                        <div class="mt-3">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
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
                                <span class="bg-slate-100 text-slate-700 text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm border border-slate-200">
                                    {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
                                </span>
                            </div>
                            <h2 class="text-lg md:text-xl font-display font-bold text-slate-900 tracking-tight leading-tight">
                                {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
                            </h2>
                            <p class="text-xs text-slate-500 font-sans font-medium mt-1">
                                {{ optional($teacher->department)->name ?? 'General' }} • <span class="text-slate-400">{{ $faculty->name ?? \App\Helpers\Branding::get('short_name') }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Social / Scholar web profile buttons -->
                @if($teacher->socialLinks->isNotEmpty())
                    <div class="card p-4">
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-3">Connect</p>
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach($teacher->socialLinks as $link)
                                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                                   class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl border border-slate-200 transition-colors"
                                   title="{{ optional($link->platform)->name ?? 'Link' }}">
                                    @include("frontend.themes.theme_modern.partials.social_icon", ['platform' => optional($link->platform)->name ?? ''])
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Quick contact card -->
                <div class="card p-5 space-y-3">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Contact</p>
                    <div class="flex items-center gap-2.5 text-xs text-slate-600 font-sans">
                        <svg class="w-4 h-4 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <span class="truncate font-semibold text-slate-700">{{ $teacher->user->email ?? 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2.5 text-xs text-slate-600 font-sans">
                        <svg class="w-4 h-4 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <span class="font-semibold text-slate-700">{{ $teacher->phone ?? ($teacher->personal_phone ?? 'N/A') }}</span>
                    </div>
                    <div class="flex items-center gap-2.5 text-xs text-slate-600 font-sans">
                        <svg class="w-4 h-4 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span class="truncate font-semibold text-slate-700">{{ $teacher->office_room ?? 'N/A' }}</span>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        @if(\App\Helpers\ProfileDownload::vcardEnabled())
                        <a href="{{ route('teacher.vcard', ['faculty_short_name' => $faculty->short_name, 'department_code' => $department->code, 'teacher_webpage' => $teacher->webpage]) }}"
                           class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-diu-primary border border-diu-primary/30 hover:bg-diu-primary hover:text-white transition-colors px-3 py-1.5 rounded-lg">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Save Contact
                        </a>
                        @endif
                        @if(\App\Helpers\ProfileDownload::cvEnabled())
                        <a href="{{ route('teacher.cv', ['faculty_short_name' => $faculty->short_name, 'department_code' => $department->code, 'teacher_webpage' => $teacher->webpage]) }}"
                           class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-white bg-diu-primary hover:bg-diu-primary-hover transition-colors px-3 py-1.5 rounded-lg">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                            Download CV
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </aside>

        <!-- Bento container (right) -->
        <div class="lg:col-span-9">
            <!-- Tab Controls -->
            <div x-data="{ tab: 'overview' }">
                <div class="lg:sticky lg:top-[var(--header-h)] z-20 flex border-b border-slate-200 mb-6 overflow-x-auto gap-1 pb-1 lg:pt-4 pt-1 backdrop-blur-md rounded-b-xl scroll-slim"
                     style="background-color: var(--surface-page);">
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
                                :class="tab === '{{ $tab['id'] }}' ? 'border-diu-primary text-diu-primary font-bold bg-white rounded-t-xl' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'"
                                class="px-4 py-3 text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-2 border-b-2 -mb-px cursor-pointer">
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>

                @include('frontend.themes.theme_modern.partials.profile.overview')
                @include('frontend.themes.theme_modern.partials.profile.academic')
                @include('frontend.themes.theme_modern.partials.profile.courses')
                @include('frontend.themes.theme_modern.partials.profile.research')
                @include('frontend.themes.theme_modern.partials.profile.publications')
                @include('frontend.themes.theme_modern.partials.profile.experience')
                @include('frontend.themes.theme_modern.partials.profile.training')
                @include('frontend.themes.theme_modern.partials.profile.awards')
                @include('frontend.themes.theme_modern.partials.profile.memberships')
            </div>
        </div>
    </div>

@endsection
