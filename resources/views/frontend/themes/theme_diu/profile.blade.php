@extends('frontend.themes.theme_diu.layouts.app')

{{-- Sharing and structured data. Built once and used for the title, the
     description and the tags, so a preview card and a search result cannot
     disagree with each other. --}}
@php
    $seo = \App\Helpers\SeoPayload::forTeacher($teacher, $faculty, $department);
@endphp

@section('title', $seo['title'])

@section('meta_description', $seo['description'])

@section('seo')
    @include('frontend.themes.theme_diu.partials.seo-tags', ['seo' => $seo])
@endsection

@section('content')

    @php
        $facSlug = $faculty->short_name ? strtolower($faculty->short_name) : null;
        $deptSlug = $department->code ? strtolower($department->code) : null;

        $deptUrl = ($facSlug && $deptSlug)
            ? route('department.show', ['faculty_short_name' => $facSlug, 'department_code' => $deptSlug])
            : route('home');

        // The CV and contact-card routes take all three segments, and every one
        // of them is a nullable column.
        $downloadable = $facSlug && $deptSlug && $teacher->webpage;

        /*
         * The post, with where it is held. "Head" alone does not say of what.
         */
        $adminRoleFirst = $teacher->administrativeRoles->first();
        $adminRoleName = $adminRoleFirst?->administrativeRole?->name;
        $adminRoleScope = $adminRoleFirst?->faculty?->name ?: $adminRoleFirst?->department?->name;
        $adminRoleTitle = $adminRoleName
            ? ($adminRoleScope ? "{$adminRoleName}, {$adminRoleScope}" : $adminRoleName)
            : null;

        /*
         * Contact details this person actually has. The strip used to print
         * three boxes whatever happened, so a profile with no phone on record
         * said "Contact Number: N/A" — and a secondary address, often the one
         * a person reads, was never shown at all.
         */
        $primaryEmail = $teacher->user?->email;
        $secondaryEmail = ($teacher->secondary_email && $teacher->secondary_email !== $primaryEmail)
            ? $teacher->secondary_email
            : null;

        $contact = array_values(array_filter([
            $primaryEmail ? ['type' => 'email', 'label' => 'Email Address', 'value' => $primaryEmail] : null,
            $secondaryEmail ? ['type' => 'email', 'label' => 'Secondary Email', 'value' => $secondaryEmail] : null,
            ($phone = $teacher->phone ?: $teacher->personal_phone) ? ['type' => 'phone', 'label' => 'Contact Number', 'value' => $phone] : null,
            $teacher->office_room ? ['type' => 'office', 'label' => 'Office Location', 'value' => $teacher->office_room] : null,
        ]));

        // A short numeric summary of what the page below holds. Zeroes are
        // dropped rather than printed.
        $summary = array_filter([
            'Publications' => $teacher->publications->count(),
            'Teaching Areas' => $teacher->teachingAreas->count(),
            'Awards' => $teacher->awards->count(),
        ]);

        /*
         * The sections of the profile, and which of them this person has
         * anything to put in.
         *
         * This used to be nine tabs whatever happened, so a new lecturer got
         * eight panels saying "nothing recorded", and only one panel was ever in
         * the page at a time — the browser's find could not see the rest, a
         * print gave you one tab, and a link could not point at publications.
         * Now the whole profile is on the page, an empty section is simply not
         * part of it, and the strip above lists exactly what is there.
         *
         * researchProjects is not among the controller's eager loads, so it is
         * resolved once here rather than inside the list below.
         */
        $projects = $teacher->researchProjects;

        $hasOverview = filled($teacher->bio) || $teacher->skills->isNotEmpty();

        $sections = collect([
            ['id' => 'overview',     'label' => 'Overview',            'count' => null, 'show' => $hasOverview],
            ['id' => 'academic',     'label' => 'Academic Background', 'count' => $teacher->educations->count()],
            ['id' => 'teaching',     'label' => 'Teaching Area',       'count' => $teacher->teachingAreas->count()],
            ['id' => 'research',     'label' => 'Research Interest',            'count' => $teacher->researchInterests->count() + $projects->count()],
            ['id' => 'expertise',    'label' => 'Area of Expertise',   'count' => $teacher->areasOfExpertise->count()],
            ['id' => 'publications', 'label' => 'Publications',        'count' => $teacher->publications->count()],
            ['id' => 'experience',   'label' => 'Experience',          'count' => $teacher->jobExperiences->count()],
            // Certifications are rendered inside the training section, so they
            // count towards whether it appears at all.
            ['id' => 'training',     'label' => 'Training',            'count' => $teacher->trainingExperiences->count() + $teacher->certifications->count()],
            ['id' => 'awards',       'label' => 'Awards',              'count' => $teacher->awards->count()],
            ['id' => 'memberships',  'label' => 'Memberships',         'count' => $teacher->memberships->count()],
        ])->filter(fn ($s) => ($s['show'] ?? false) || ($s['count'] ?? 0) > 0)->values();

        $shown = $sections->pluck('id')->all();
    @endphp

    <!-- Breadcrumbs -->
    <div class="text-xs text-slate-500 font-semibold mb-8 flex flex-wrap items-center gap-2 glass-panel py-2.5 px-5 rounded-2xl">
        <a href="{{ route('home') }}" wire:navigate class="hover:text-diu-primary transition">Home</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $faculty->url }}" wire:navigate class="hover:text-diu-primary transition">{{ $faculty->short_name }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a href="{{ $deptUrl }}" wire:navigate class="hover:text-diu-primary transition">{{ $department->code }}</a>
        <svg class="w-3.5 h-3.5 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <span class="text-diu-primary truncate max-w-xs">{{ $teacher->full_name }}</span>
    </div>

    {{-- overflow-clip, not overflow-hidden: both round the corners off, but
         hidden also turns the card into a scroll container, and a sticky
         element inside one sticks to the card rather than to the window — the
         section strip below would never park. --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-clip" id="teacher-profile-{{ $teacher->id }}">

        <!-- Cover / Hero header banner -->
        <div class="relative h-48 bg-gradient-to-r from-diu-primary-dark via-diu-primary to-diu-accent/80 p-6 md:p-8 flex items-end border-b border-slate-200/60">
            <a href="{{ $deptUrl }}" wire:navigate
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
                        @if($teacher->photo_url)
                            <img src="{{ $teacher->photo_url }}" alt="{{ $teacher->full_name }}" loading="eager" class="w-full h-full object-cover" />
                        @else
                            <div class="w-full h-full bg-diu-primary text-white flex items-center justify-center font-display font-bold text-4xl">
                                {{ $teacher->initials }}
                            </div>
                        @endif
                    </div>

                    <div class="pt-2">
                        <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 mb-1.5">
                            @if($adminRoleTitle)
                                <span class="bg-diu-accent text-white text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm shadow-xs border border-diu-accent/20">
                                    {{ $adminRoleTitle }}
                                </span>
                            @endif
                            <span class="bg-slate-100 text-slate-700 text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm border border-slate-200">
                                {{ $teacher->designation_title ?? 'Faculty Member' }}
                            </span>
                        </div>
                        <h1 class="text-xl md:text-2xl font-display font-bold text-slate-900 tracking-tight leading-tight">
                            {{ $teacher->full_name }}
                        </h1>
                        <p class="text-xs text-slate-500 font-sans font-medium mt-1">
                            @if($department?->name)
                                <a href="{{ $deptUrl }}" wire:navigate class="hover:text-diu-primary transition-colors">{{ $department->name }}</a>
                            @endif
                            @if($faculty?->name)
                                • <a href="{{ $faculty->url }}" wire:navigate class="text-slate-400 hover:text-diu-primary transition-colors">{{ $faculty->name }}</a>
                            @endif
                        </p>

                        {{-- Says so when this person is on leave, so the page does
                             not imply they are at their desk. Silent otherwise. --}}
                        <x-teacher-engagement :teacher="$teacher" variant="full" class="mt-3" />
                        <x-teacher-status :teacher="$teacher" variant="full" class="mt-3 justify-center sm:justify-start" />
                    </div>
                </div>

                <!-- Social / Scholars Web Profile Buttons -->
                @if($teacher->socialLinks->isNotEmpty())
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        @foreach($teacher->socialLinks as $link)
                            <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                               class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg border border-slate-200 transition-colors"
                               title="{{ optional($link->platform)->name ?? 'Link' }}">
                                @include('frontend.themes.theme_diu.partials.social_icon', ['platform' => optional($link->platform)->name ?? ''])
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Summary and the two things you can take away. The downloads used
                 to sit loose under the whole profile, past the last section. --}}
            @if($summary || ($downloadable && (\App\Helpers\ProfileDownload::cvEnabled() || \App\Helpers\ProfileDownload::vcardEnabled())))
                <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
                    @if($summary)
                        <dl class="flex flex-wrap gap-x-8 gap-y-3">
                            @foreach($summary as $label => $value)
                                <div>
                                    <dt class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">{{ $label }}</dt>
                                    <dd class="font-display text-xl font-extrabold text-slate-900 mt-0.5 tabular-nums">{{ number_format($value) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if($downloadable)
                        <div class="flex flex-wrap gap-2">
                            @if(\App\Helpers\ProfileDownload::vcardEnabled())
                                <a href="{{ route('teacher.vcard', ['faculty_short_name' => $faculty->short_name, 'department_code' => $department->code, 'teacher_webpage' => $teacher->webpage]) }}"
                                   class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-diu-primary border border-diu-primary/30 hover:bg-diu-primary hover:text-white transition-colors px-3 py-1.5 rounded-lg">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    Save Contact
                                </a>
                            @endif
                            @if(\App\Helpers\ProfileDownload::cvEnabled())
                                <a href="{{ route('teacher.cv', ['faculty_short_name' => $faculty->short_name, 'department_code' => $department->code, 'teacher_webpage' => $teacher->webpage]) }}"
                                   class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-white bg-diu-primary hover:bg-diu-primary-hover transition-colors px-3 py-1.5 rounded-lg">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                                    Download CV
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            <!-- Contact Strip -->
            @if($contact)
                <div class="grid grid-cols-1 sm:grid-cols-2 {{ match (count($contact)) { 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', default => '' } }} gap-4 py-4 px-5 bg-slate-50 rounded-xl border border-slate-100 mb-8 text-xs text-slate-600 font-sans ring-1 ring-slate-900/5">
                    @foreach($contact as $item)
                        <div class="flex items-center gap-2.5 min-w-0">
                            @if($item['type'] === 'email')
                                <svg class="w-4 h-4 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            @elseif($item['type'] === 'phone')
                                <svg class="w-4 h-4 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            @else
                                <svg class="w-4 h-4 text-diu-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            @endif
                            <div class="min-w-0">
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">{{ $item['label'] }}</p>
                                @if($item['type'] === 'email')
                                    <a href="mailto:{{ $item['value'] }}" class="block font-mono truncate font-semibold text-slate-700 hover:text-diu-primary transition-colors" title="{{ $item['value'] }}">{{ $item['value'] }}</a>
                                @else
                                    <p class="font-semibold text-slate-700 truncate" title="{{ $item['value'] }}">{{ $item['value'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- The section strip. It keeps the look of the tab bar it replaced,
                 but every entry is an anchor to a section already on the page,
                 and it parks under the header as you read. The active entry is set
                 by an IntersectionObserver in theme.js; with JavaScript off these
                 are still working links. --}}
            <nav class="diu-tabs mb-6" aria-label="Sections of this profile">
                @foreach($sections as $section)
                    <a href="#{{ $section['id'] }}" data-section-link="{{ $section['id'] }}" class="diu-tab">
                        {{ $section['label'] }}
                        @if($section['count'])
                            <span class="diu-tab-count">{{ $section['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div>
                @if(in_array('overview', $shown, true))
                    @include('frontend.themes.theme_diu.partials.profile.overview')
                @endif

                @if(in_array('academic', $shown, true))
                    @include('frontend.themes.theme_diu.partials.profile.academic')
                @endif

                @if(in_array('teaching', $shown, true))
                    @include('frontend.themes.theme_diu.partials.profile.courses')
                @endif

                @if(in_array('research', $shown, true))
                    @include('frontend.themes.theme_diu.partials.profile.research')
                @endif

                @if(in_array('expertise', $shown, true))
                    @include('frontend.themes.theme_diu.partials.profile.expertise')
                @endif

                @if(in_array('publications', $shown, true))
                    @include('frontend.themes.theme_diu.partials.profile.publications')
                @endif

                @if(in_array('experience', $shown, true))
                    @include('frontend.themes.theme_diu.partials.profile.experience')
                @endif

                @if(in_array('training', $shown, true))
                    @include('frontend.themes.theme_diu.partials.profile.training')
                @endif

                @if(in_array('awards', $shown, true))
                    @include('frontend.themes.theme_diu.partials.profile.awards')
                @endif

                @if(in_array('memberships', $shown, true))
                    @include('frontend.themes.theme_diu.partials.profile.memberships')
                @endif
            </div>
        </div>
    </div>

@endsection
