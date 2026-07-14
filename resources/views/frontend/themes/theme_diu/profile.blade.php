<!DOCTYPE html>
<html lang="en">
<head>
    @include('frontend.themes.theme_diu.partials.head', ['title' => $teacher->first_name . ' ' . $teacher->last_name . ' - Profile'])
</head>
<body class="bg-transparent min-h-screen flex flex-col font-sans text-slate-800 antialiased">

    @include('frontend.themes.theme_diu.partials.header')

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">

        <div class="bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm overflow-hidden" id="teacher-profile-{{ $teacher->id }}">

            <!-- Cover / Hero header banner -->
            <div class="relative h-48 bg-gradient-to-r from-diu-green-dark via-diu-green to-diu-orange/80 p-6 md:p-8 flex items-end border-b border-white/20">
                <a href="{{ url('/' . strtolower($faculty->short_name) . '/' . strtolower($department->code)) }}"
                   class="absolute top-4 left-4 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all backdrop-blur-xs">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7M19 12H5"/></svg>
                    Back to list
                </a>
                <div class="absolute right-6 top-6 text-white/10 font-display font-extrabold text-7xl select-none hidden sm:block">DIU</div>
            </div>

            <!-- Main Info Frame -->
            <div class="px-6 md:px-8 pb-8 relative">

                <!-- Profile Avatar shifted on top of cover -->
                <div class="flex flex-col md:flex-row md:items-end justify-between -mt-16 mb-6 gap-4">
                    <div class="flex flex-col md:flex-row items-center md:items-end gap-5 text-center md:text-left">
                        <div class="w-32 h-32 rounded-2xl overflow-hidden border-4 border-white shadow-lg bg-slate-100 shrink-0">
                            @if($teacher->photo)
                                <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover" />
                            @else
                                <div class="w-full h-full bg-diu-green text-white flex items-center justify-center font-display font-bold text-4xl">
                                    {{ strtoupper(substr($teacher->first_name, 0, 1)) }}
                                </div>
                            @endif
                        </div>

                        <div class="pt-2">
                            <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 mb-1.5">
                                @if(optional($teacher->designation)->name && preg_match('/(dean|head|chairman|director|coordinator|advisor)/i', optional($teacher->designation)->name))
                                    <span class="bg-diu-orange text-white text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm shadow-xs border border-diu-orange/20">
                                        {{ optional($teacher->designation)->name }}
                                    </span>
                                @endif
                                <span class="bg-white/60 text-slate-700 text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm border border-white/80">
                                    {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
                                </span>
                            </div>
                            <h2 class="text-xl md:text-2xl font-display font-bold text-slate-900 tracking-tight leading-tight">
                                {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
                            </h2>
                            <p class="text-xs text-slate-500 font-sans font-medium mt-1">
                                {{ optional($teacher->department)->name ?? 'General' }} • <span class="text-slate-400">{{ $faculty->name ?? 'DIU' }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Social / Scholars Web Profile Buttons -->
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        @foreach($teacher->socialLinks as $link)
                            @php $p = strtolower(optional($link->platform)->name ?? ''); @endphp
                            <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                               class="p-2 bg-white/40 hover:bg-white/80 text-slate-600 rounded-lg border border-white/60 transition-colors shadow-2xs"
                               title="{{ optional($link->platform)->name ?? 'Link' }}">
                                @if($p == 'linkedin')
                                    <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                                @elseif($p == 'github')
                                    <svg class="w-4 h-4 text-slate-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>
                                @elseif($p == 'facebook')
                                    <svg class="w-4 h-4 text-blue-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                                @elseif($p == 'instagram')
                                    <svg class="w-4 h-4 text-pink-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                                @elseif($p == 'google scholar' || $p == 'googlescholar')
                                    <svg class="w-4 h-4 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                @elseif($p == 'researchgate' || $p == 'research gate')
                                    <span class="font-sans font-black text-xs leading-none text-emerald-600">RG</span>
                                @else
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Contact Strip -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 py-4 px-5 bg-white/30 backdrop-blur-xs rounded-xl border border-white/60 mb-8 text-xs text-slate-600 font-sans ring-1 ring-slate-900/5">
                    <div class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-diu-green shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <div class="min-w-0">
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Email Address</p>
                            <p class="font-mono truncate font-semibold text-slate-700">{{ $teacher->secondary_email ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-diu-green shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <div>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Contact Number</p>
                            <p class="font-semibold text-slate-700">{{ $teacher->phone ?? ($teacher->personal_phone ?? 'N/A') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-diu-green shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
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
                            ['id' => 'courses', 'label' => 'Courses'],
                            ['id' => 'research', 'label' => 'Research'],
                            ['id' => 'publications', 'label' => 'Publications (' . $teacher->publications->count() . ')'],
                            ['id' => 'experience', 'label' => 'Experience'],
                            ['id' => 'training', 'label' => 'Training'],
                            ['id' => 'awards', 'label' => 'Awards'],
                            ['id' => 'memberships', 'label' => 'Memberships'],
                        ] as $tab)
                            <button @click="tab = '{{ $tab['id'] }}'"
                                    :class="tab === '{{ $tab['id'] }}' ? 'border-diu-green text-diu-green font-bold bg-white/40 rounded-t-lg' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-white/10'"
                                    class="px-4 py-3 text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-2 border-b-2 -mb-px cursor-pointer">
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <!-- Overview Tab -->
                    <div x-show="tab === 'overview'" class="space-y-6" x-cloak>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-2">Biography</h3>
                            <p class="text-sm text-slate-600 leading-relaxed font-sans">{{ $teacher->bio ?: ($teacher->research_interest ?: 'No biography added yet.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                            <div class="bg-white/30 backdrop-blur-xs p-5 rounded-xl border border-white/60 ring-1 ring-slate-900/5">
                                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><path d="M21 21v-2a4 4 0 0 0-3-3.87M9 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    Teaching Areas
                                </h4>
                                @if($teacher->teachingAreas->isEmpty())
                                    <p class="text-xs text-slate-400">No teaching areas specified.</p>
                                @else
                                    <ul class="space-y-2">
                                        @foreach($teacher->teachingAreas as $area)
                                            <li class="flex items-center justify-between text-xs text-slate-600 font-sans">
                                                <span class="flex items-center gap-2"><svg class="w-3 h-3 text-diu-orange shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>{{ $area->name }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="bg-white/30 backdrop-blur-xs p-5 rounded-xl border border-white/60 ring-1 ring-slate-900/5">
                                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.48M12 3v18"/></svg>
                                    Research Interests
                                </h4>
                                @php $interests = $teacher->research_interest ? array_filter(array_map('trim', explode(',', $teacher->research_interest))) : []; @endphp
                                @if(count($interests) > 0)
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($interests as $interest)
                                            <span class="bg-white/60 border border-white/80 text-slate-700 text-xs font-sans px-3 py-1 rounded-full shadow-2xs">{{ $interest }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-xs text-slate-400">No research interests listed.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Academic Background Tab -->
                    <div x-show="tab === 'academic'" class="space-y-4" x-cloak>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                            Academic Degrees &amp; Background
                        </h3>
                        @if($teacher->educations->isEmpty())
                            <div class="p-4 rounded-xl border border-white/60 bg-white/30 backdrop-blur-xs">
                                <p class="text-xs text-slate-500 font-medium">B.Sc. &amp; M.Sc. in Engineering / relevant discipline from Daffodil International University / reputable public university.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($teacher->educations as $edu)
                                    <div class="p-4 rounded-xl border border-white/60 bg-white/30 backdrop-blur-xs ring-1 ring-slate-900/5">
                                        <span class="bg-diu-green/10 text-diu-green text-[9px] font-sans font-black uppercase px-2 py-0.5 rounded-xs">Year: {{ $edu->passing_year ?? 'N/A' }}</span>
                                        <h4 class="text-sm font-bold text-slate-800 mt-2 font-display">{{ $edu->degree_name }}</h4>
                                        <p class="text-xs text-slate-600 mt-0.5 font-medium">{{ $edu->institution_name }}</p>
                                        @if($edu->result)
                                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-2 bg-slate-50 border border-slate-100 rounded-sm inline-block px-1.5 py-0.5">Result: {{ $edu->result }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Courses Tab -->
                    <div x-show="tab === 'courses'" class="space-y-4" x-cloak>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                            Courses Assigned
                        </h3>
                        @if($teacher->teachingAreas->isEmpty())
                            <p class="text-sm text-slate-500 italic">No assigned teaching courses found.</p>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($teacher->teachingAreas as $area)
                                    <div class="bg-slate-50 border border-gray-100 p-4 rounded-2xl flex items-center space-x-3">
                                        <span class="text-diu-green text-lg">📚</span>
                                        <div><h4 class="font-bold text-gray-900 text-sm">{{ $area->name }}</h4><p class="text-[10px] text-gray-500 uppercase tracking-wider font-semibold">Active Curriculum</p></div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Research Tab -->
                    <div x-show="tab === 'research'" class="space-y-4" x-cloak>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.5c-1.8 0-3.5 1.4-3.5 3.5s1.7 3.5 3.5 3.5 3.5-1.4 3.5-3.5a2 2 0 0 0-.063-.5"/><path d="M10 10a2 2 0 0 0 2-2c0-1-.9-1.9-2-2s-2 .9-2 2a2 2 0 0 0 2 2Z"/><path d="M4.5 11h.5a2 2 0 0 0 2-2c0-1-.9-1.9-2-2s-2 .9-2 2a2 2 0 0 0 2 2Z"/><path d="M6 17.5a2 2 0 0 0 2 2c1.8 0 3.5-1.4 3.5-3.5S9.8 12.5 8 12.5a2 2 0 0 0-2 2c0 .7.3 1.3.5 1.5Z"/><path d="m15 8 .5.5a2 2 0 0 1 0 2.8l-3 3a2 2 0 0 1-2.8 0l-.5-.5"/><path d="m13 14-.5-.5a2 2 0 0 1 0-2.8l3-3a2 2 0 0 1 2.8 0l.5.5"/></svg>
                            Research Profile
                        </h3>
                        @if($teacher->research_interest)
                            <div class="p-5 bg-diu-green/5 border border-diu-green/10 rounded-2xl italic text-gray-700 text-sm">"{{ $teacher->research_interest }}"</div>
                        @endif
                        @if($teacher->researchProjects->isEmpty())
                            <p class="text-sm text-slate-500 italic mt-4">No specific research projects registered.</p>
                        @else
                            <div class="space-y-4 mt-6">
                                @foreach($teacher->researchProjects as $proj)
                                    <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                                        <h4 class="font-extrabold text-gray-900 text-sm">{{ $proj->title }}</h4>
                                        <p class="text-xs text-gray-500 mt-1">Funding: {{ $proj->funding_agency ?? 'N/A' }} | Role: {{ $proj->role ?? 'N/A' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Publications Tab -->
                    <div x-show="tab === 'publications'" class="space-y-4" x-cloak>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                            List of Scholarly Papers
                        </h3>
                        @if($teacher->publications->isEmpty())
                            <div class="text-center py-12 border-2 border-dashed border-white/60 rounded-xl bg-white/10">
                                <svg class="w-10 h-10 text-slate-400 mx-auto mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                                <p class="text-sm text-slate-500 font-sans font-medium">No publications added yet for this teacher.</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($teacher->publications as $pub)
                                    <div class="p-4 rounded-xl border border-white/60 hover:border-diu-green/40 bg-white/30 backdrop-blur-xs shadow-3xs hover:shadow-xs transition-all flex items-start gap-4">
                                        <div class="bg-diu-green/10 text-diu-green p-2.5 rounded-lg shrink-0 mt-0.5">
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-[9px] font-sans font-bold px-1.5 py-0.5 rounded-xs {{ stripos($pub->type?->name ?? '', 'journal') !== false ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-indigo-50 text-indigo-700 border border-indigo-100' }}">
                                                    {{ $pub->type?->name ?? 'Research Paper' }}
                                                </span>
                                                <span class="text-[10px] text-slate-400 font-semibold font-sans">{{ $pub->publication_year ?? 'N/A' }}</span>
                                            </div>
                                            <h4 class="text-sm font-semibold text-slate-800 tracking-tight leading-snug group-hover:text-diu-green transition-colors">{{ $pub->title }}</h4>
                                            <p class="text-xs text-slate-500 mt-1 italic font-sans">{{ $pub->journal_name ?? '' }}</p>
                                            <div class="flex items-center space-x-4 mt-4">
                                                <a href="{{ url('/' . strtolower($faculty->short_name) . '/' . strtolower($department->code) . '/' . $teacher->webpage . '/publication/' . \Illuminate\Support\Str::slug($pub->title)) }}" class="inline-flex items-center text-xs font-bold text-diu-green hover:underline">
                                                    <span>View Details</span><span class="ml-1.5">→</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Experience Tab -->
                    <div x-show="tab === 'experience'" class="space-y-4" x-cloak>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="7" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                            Employment History
                        </h3>
                        @if($teacher->jobExperiences->isEmpty())
                            <p class="text-xs text-slate-400">No corporate or academic work history submitted.</p>
                        @else
                            <div class="relative border-l border-white/40 pl-5 ml-2.5 space-y-6">
                                @foreach($teacher->jobExperiences as $exp)
                                    <div class="relative">
                                        <span class="absolute -left-7.5 top-1 bg-white border-2 border-diu-green rounded-full w-4 h-4 flex items-center justify-center shadow-xs"><span class="w-1.5 h-1.5 bg-diu-green rounded-full"></span></span>
                                        <div class="flex items-center gap-2 text-xs font-bold text-diu-green tracking-wide">
                                            <svg class="w-3.5 h-3.5 text-diu-green shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
                                            {{ $exp->start_date ? date('Y', strtotime($exp->start_date)) : '' }} - {{ $exp->is_current ? 'Present' : ($exp->end_date ? date('Y', strtotime($exp->end_date)) : 'Past') }}
                                        </div>
                                        <h4 class="text-sm font-bold text-slate-800 mt-1 font-display">{{ $exp->title }}</h4>
                                        <p class="text-xs text-slate-500 font-semibold mt-0.5">{{ $exp->institution_name ?? '' }}</p>
                                        @if($exp->description)<p class="text-xs text-slate-500 font-sans mt-1 leading-relaxed">{{ $exp->description }}</p>@endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Training Tab -->
                    <div x-show="tab === 'training'" class="space-y-4" x-cloak>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                            Special Training &amp; Pedagogy Programs
                        </h3>
                        @if($teacher->trainingExperiences->isEmpty())
                            <p class="text-xs text-slate-400">No training experiences recorded.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($teacher->trainingExperiences as $trn)
                                    <div class="p-4 rounded-xl border border-white/60 bg-white/30 backdrop-blur-xs flex gap-3 ring-1 ring-slate-900/5">
                                        <div class="bg-diu-orange/15 text-diu-orange p-2 rounded-lg shrink-0 h-9 w-9 flex items-center justify-center">
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-bold text-slate-800 leading-snug font-display">{{ $trn->title }}</h4>
                                            <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ $trn->institution_name ?? '' }}</p>
                                            <div class="flex items-center gap-4 mt-2 text-[10px] text-slate-400 font-bold uppercase">
                                                <span>Year: {{ $trn->year ?? 'N/A' }}</span>
                                                @if($trn->duration)<span>• Duration: {{ $trn->duration }}</span>@endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Awards Tab -->
                    <div x-show="tab === 'awards'" class="space-y-4" x-cloak>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-orange" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6m0 5h12m0-5h1.5a2.5 2.5 0 0 1 0 5H18m0 0v2a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4v-2m8 0h-4"/></svg>
                            Special Awards, Fellowships &amp; Scholarships
                        </h3>
                        @if($teacher->awards->isEmpty())
                            <p class="text-xs text-slate-400">No special awards or achievements documented.</p>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($teacher->awards as $awr)
                                    <div class="p-4 rounded-xl border border-diu-orange/20 bg-diu-orange/5 backdrop-blur-xs flex gap-3.5 items-start">
                                        <div class="bg-white text-diu-orange p-2 rounded-lg shrink-0 border border-diu-orange/10 shadow-3xs">
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6m0 5h12m0-5h1.5a2.5 2.5 0 0 1 0 5H18m0 0v2a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4v-2m8 0h-4"/></svg>
                                        </div>
                                        <div>
                                            <span class="bg-diu-orange text-white text-[8px] font-sans font-bold uppercase px-1.5 py-0.5 rounded-xs">{{ $awr->title }}</span>
                                            <h4 class="text-xs font-bold text-slate-800 mt-1.5 leading-snug font-display">{{ $awr->title }}</h4>
                                            <p class="text-[11px] text-slate-500 font-semibold mt-0.5">{{ $awr->awarding_body ?? '' }} • {{ $awr->year ?? '' }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Memberships Tab -->
                    <div x-show="tab === 'memberships'" class="space-y-4" x-cloak>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-diu-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-5-4-4 4-4-4-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Professional Memberships &amp; Affiliations
                        </h3>
                        @if($teacher->memberships->isEmpty())
                            <p class="text-xs text-slate-400 font-sans">No affiliated professional bodies declared.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($teacher->memberships as $mem)
                                    <div class="p-3 bg-white/30 backdrop-blur-xs rounded-xl border border-white/60 flex items-center gap-2.5 text-xs text-slate-700 font-sans font-medium ring-1 ring-slate-900/5">
                                        <div class="w-2 h-2 rounded-full bg-diu-green shrink-0"></div>
                                        {{ $mem->title }}{{ $mem->membership_role ? ' — ' . $mem->membership_role : '' }}
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </main>

    @include('frontend.themes.theme_diu.partials.footer')

</body>
</html>
