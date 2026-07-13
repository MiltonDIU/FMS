<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $teacher->first_name }} {{ $teacher->last_name }} - Profile</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js for Tabs -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        diu: {
                            50: '#f2f7fd',
                            100: '#e4effb',
                            600: '#034ea2',
                            700: '#023c80',
                            900: '#011d3c',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
        }
    </style>
</head>
<body class="bg-slate-50 text-neutral-800 min-h-screen flex flex-col font-sans antialiased">

    <!-- Header Navigation -->
    <header class="sticky top-0 z-50 glass-header">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-diu-600 flex items-center justify-center text-white font-extrabold text-xl shadow-lg shadow-diu-600/20">
                    DIU
                </div>
                <div>
                    <span class="text-lg font-bold tracking-tight text-gray-900">Faculty <span class="text-diu-600">Directory</span></span>
                    <p class="text-[9px] text-gray-500 font-semibold tracking-wide uppercase">Daffodil International University</p>
                </div>
            </a>
            
            <a href="{{ url('/departments/' . (optional($teacher->department)->code ?? '')) }}" class="text-sm font-bold text-gray-600 hover:text-diu-600 transition">
                &larr; Back to Department
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Left Side: Profile Information & Photo Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl border border-gray-100 p-8 flex flex-col items-center text-center shadow-lg shadow-gray-100/40 sticky top-28 space-y-6">
                    <!-- Photo -->
                    <div class="w-36 h-36 relative">
                        @if($teacher->photo)
                            <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover rounded-3xl shadow-md border-2 border-white" />
                        @else
                            <div class="w-full h-full rounded-3xl bg-diu-50 flex items-center justify-center text-diu-600 font-extrabold text-4xl border border-diu-100/50">
                                {{ substr($teacher->first_name, 0, 1) }}{{ substr($teacher->last_name, 0, 1) }}
                            </div>
                        @endif
                        <span class="absolute bottom-2 right-2 w-4 h-4 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>

                    <!-- Profile Bio Info -->
                    <div>
                        <h2 class="text-xl font-extrabold text-gray-900 leading-tight">
                            {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
                        </h2>
                        <p class="text-xs font-semibold text-diu-700 bg-diu-50 px-3 py-1 rounded-full mt-2 inline-block">
                            {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
                        </p>
                        <p class="text-[10px] text-gray-400 font-bold uppercase mt-1">
                            {{ optional($teacher->department)->name ?? 'General' }}
                        </p>
                    </div>

                    <div class="w-full border-t border-gray-100 my-4"></div>

                    <!-- Contact Details -->
                    <div class="w-full space-y-4 text-left">
                        <div class="flex items-start space-x-3 text-sm">
                            <span class="text-gray-400">📧</span>
                            <div class="break-all">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Email Address</p>
                                <a href="mailto:{{ $teacher->secondary_email }}" class="text-gray-900 font-semibold hover:underline">{{ $teacher->secondary_email ?? 'N/A' }}</a>
                            </div>
                        </div>

                        @if($teacher->phone)
                            <div class="flex items-start space-x-3 text-sm">
                                <span class="text-gray-400">📞</span>
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Office Phone</p>
                                    <span class="text-gray-900 font-semibold">{{ $teacher->phone }}</span>
                                </div>
                            </div>
                        @endif

                        @if($teacher->personal_phone)
                            <div class="flex items-start space-x-3 text-sm">
                                <span class="text-gray-400">📱</span>
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mobile</p>
                                    <span class="text-gray-900 font-semibold">{{ $teacher->personal_phone }}</span>
                                </div>
                            </div>
                        @endif

                        @if($teacher->webpage)
                            <div class="flex items-start space-x-3 text-sm">
                                <span class="text-gray-400">🌐</span>
                                <div class="break-all">
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Webpage</p>
                                    <a href="{{ $teacher->webpage }}" target="_blank" class="text-diu-600 font-semibold hover:underline">{{ $teacher->webpage }}</a>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Social Icons -->
                    @if($teacher->socialLinks->isNotEmpty())
                        <div class="w-full border-t border-gray-100 my-4"></div>
                        <div class="flex items-center justify-center space-x-3">
                            @foreach($teacher->socialLinks as $link)
                                <a href="{{ $link->url }}" target="_blank" class="w-8 h-8 rounded-xl bg-gray-50 border border-gray-100 hover:border-diu-200 flex items-center justify-center text-gray-500 hover:text-diu-600 transition" title="{{ optional($link->platform)->name }}">
                                    🔗
                                </a>
                            @endforeach
                        </div>
                    @endif

                </div>
            </div>

            <!-- Right Side: Portfolio Content Tabs (Alpine.js) -->
            <div class="lg:col-span-3 space-y-8" x-data="{ tab: 'overview' }">
                
                <!-- Tab Headers -->
                <div class="bg-white border border-gray-100 rounded-2xl p-2 shadow-sm flex items-center space-x-2 overflow-x-auto whitespace-nowrap">
                    <button 
                        @click="tab = 'overview'" 
                        :class="tab === 'overview' ? 'bg-diu-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50'"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition duration-200"
                    >
                        Overview
                    </button>
                    <button 
                        @click="tab = 'courses'" 
                        :class="tab === 'courses' ? 'bg-diu-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50'"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition duration-200"
                    >
                        Courses
                    </button>
                    <button 
                        @click="tab = 'research'" 
                        :class="tab === 'research' ? 'bg-diu-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50'"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition duration-200"
                    >
                        Research
                    </button>
                    <button 
                        @click="tab = 'publications'" 
                        :class="tab === 'publications' ? 'bg-diu-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50'"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition duration-200"
                    >
                        Publications ({{ $teacher->publications->count() }})
                    </button>
                    <button 
                        @click="tab = 'training'" 
                        :class="tab === 'training' ? 'bg-diu-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50'"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition duration-200"
                    >
                        Training
                    </button>
                    <button 
                        @click="tab = 'awards'" 
                        :class="tab === 'awards' ? 'bg-diu-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50'"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition duration-200"
                    >
                        Awards
                    </button>
                    <button 
                        @click="tab = 'memberships'" 
                        :class="tab === 'memberships' ? 'bg-diu-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50'"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition duration-200"
                    >
                        Memberships
                    </button>
                </div>

                <!-- Tab Contents -->
                
                <!-- Overview Tab -->
                <div x-show="tab === 'overview'" class="space-y-8" x-cloak>
                    <!-- Biography -->
                    @if($teacher->bio)
                        <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                            <h3 class="text-lg font-extrabold text-gray-900 mb-4 flex items-center">
                                <span class="w-1.5 h-5 bg-diu-600 rounded-full mr-2.5"></span>
                                Biography
                            </h3>
                            <p class="text-gray-600 text-sm leading-relaxed whitespace-pre-line">{{ $teacher->bio }}</p>
                        </div>
                    @endif

                    <!-- Educations -->
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <h3 class="text-lg font-extrabold text-gray-900 mb-6 flex items-center">
                            <span class="w-1.5 h-5 bg-diu-600 rounded-full mr-2.5"></span>
                            Education Background
                        </h3>
                        @if($teacher->educations->isEmpty())
                            <p class="text-sm text-gray-500 italic">No education records found.</p>
                        @else
                            <div class="relative pl-6 border-l border-slate-200 space-y-8">
                                @foreach($teacher->educations as $edu)
                                    <div class="relative">
                                        <span class="absolute -left-[30.5px] top-1.5 w-3 h-3 rounded-full bg-diu-600 border-2 border-white shadow-sm"></span>
                                        <h4 class="font-extrabold text-gray-900 text-sm">{{ $edu->degree_name }}</h4>
                                        <p class="text-xs text-gray-600 font-medium mt-1">{{ $edu->institution_name }}</p>
                                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wide mt-1">
                                            Passed Year: {{ $edu->passing_year ?? 'N/A' }} | Result: {{ $edu->result ?? 'N/A' }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Courses Tab -->
                <div x-show="tab === 'courses'" class="space-y-8" x-cloak>
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <h3 class="text-lg font-extrabold text-gray-900 mb-4 flex items-center">
                            <span class="w-1.5 h-5 bg-diu-600 rounded-full mr-2.5"></span>
                            Courses Assigned
                        </h3>
                        @if($teacher->teachingAreas->isEmpty())
                            <p class="text-sm text-gray-500 italic">No assigned teaching courses found.</p>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($teacher->teachingAreas as $area)
                                    <div class="bg-slate-50 border border-gray-100 p-4 rounded-2xl flex items-center space-x-3">
                                        <span class="text-diu-600 text-lg">📚</span>
                                        <div>
                                            <h4 class="font-bold text-gray-900 text-sm">{{ $area->name }}</h4>
                                            <p class="text-[10px] text-gray-500 uppercase tracking-wider font-semibold">Active Curriculum</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Research Tab -->
                <div x-show="tab === 'research'" class="space-y-8" x-cloak>
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <h3 class="text-lg font-extrabold text-gray-900 mb-4 flex items-center">
                            <span class="w-1.5 h-5 bg-diu-600 rounded-full mr-2.5"></span>
                            Research Profile
                        </h3>
                        @if($teacher->research_interest)
                            <div class="p-5 bg-diu-50/50 border border-diu-50 rounded-2xl italic text-gray-700 text-sm">
                                "{{ $teacher->research_interest }}"
                            </div>
                        @endif

                        @if($teacher->researchProjects->isEmpty())
                            <p class="text-sm text-gray-500 italic mt-4">No specific research projects registered.</p>
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
                </div>

                <!-- Publications Tab -->
                <div x-show="tab === 'publications'" class="space-y-6" x-cloak>
                    @if($teacher->publications->isEmpty())
                        <div class="bg-white border border-gray-100 rounded-3xl p-8 text-center shadow-sm">
                            <p class="text-sm text-gray-500 italic">No publications indexed.</p>
                        </div>
                    @else
                        @foreach($teacher->publications as $pub)
                            <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm hover:border-diu-100 transition-all duration-300">
                                <span class="px-2.5 py-1 bg-diu-50 text-diu-700 text-[10px] font-bold rounded-lg uppercase tracking-wide">
                                    {{ $pub->type ?? 'Research Paper' }}
                                </span>
                                <h4 class="font-extrabold text-gray-900 text-base mt-3 leading-snug">
                                    {{ $pub->title }}
                                </h4>
                                <p class="text-xs text-gray-500 mt-2">
                                    Published in: <span class="text-gray-700 font-semibold">{{ $pub->journal_name ?? 'N/A' }}</span> | Year: <span class="text-gray-700 font-semibold">{{ $pub->published_year ?? 'N/A' }}</span>
                                </p>
                                @if($pub->paper_link)
                                    <a href="{{ $pub->paper_link }}" target="_blank" class="inline-flex items-center text-xs font-bold text-diu-600 hover:underline mt-4">
                                        <span>View Document</span>
                                        <span class="ml-1.5">&rarr;</span>
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>

                <!-- Training Tab -->
                <div x-show="tab === 'training'" class="space-y-8" x-cloak>
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <h3 class="text-lg font-extrabold text-gray-900 mb-4 flex items-center">
                            <span class="w-1.5 h-5 bg-diu-600 rounded-full mr-2.5"></span>
                            Training & Workshops
                        </h3>
                        @if($teacher->trainingExperiences->isEmpty())
                            <p class="text-sm text-gray-500 italic">No training experience indexed.</p>
                        @else
                            <div class="space-y-6">
                                @foreach($teacher->trainingExperiences as $trn)
                                    <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                                        <h4 class="font-bold text-gray-900 text-sm">{{ $trn->title }}</h4>
                                        <p class="text-xs text-gray-600 mt-1">{{ $trn->institution_name }} ({{ $trn->duration ?? 'N/A' }})</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Awards Tab -->
                <div x-show="tab === 'awards'" class="space-y-8" x-cloak>
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <h3 class="text-lg font-extrabold text-gray-900 mb-4 flex items-center">
                            <span class="w-1.5 h-5 bg-diu-600 rounded-full mr-2.5"></span>
                            Awards & Scholarships
                        </h3>
                        @if($teacher->awards->isEmpty())
                            <p class="text-sm text-gray-500 italic">No awards registered.</p>
                        @else
                            <div class="space-y-6">
                                @foreach($teacher->awards as $awr)
                                    <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                                        <h4 class="font-bold text-gray-900 text-sm">{{ $awr->title }}</h4>
                                        <p class="text-xs text-gray-600 mt-1">Given by: {{ $awr->awarding_body ?? 'N/A' }} | Year: {{ $awr->year ?? 'N/A' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Memberships Tab -->
                <div x-show="tab === 'memberships'" class="space-y-8" x-cloak>
                    <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-sm">
                        <h3 class="text-lg font-extrabold text-gray-900 mb-4 flex items-center">
                            <span class="w-1.5 h-5 bg-diu-600 rounded-full mr-2.5"></span>
                            Professional Memberships
                        </h3>
                        @if($teacher->memberships->isEmpty())
                            <p class="text-sm text-gray-500 italic">No memberships indexed.</p>
                        @else
                            <div class="space-y-6">
                                @foreach($teacher->memberships as $mem)
                                    <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                                        <h4 class="font-bold text-gray-900 text-sm">{{ $mem->title }}</h4>
                                        <p class="text-xs text-gray-600 mt-1">Role: {{ $mem->membership_role ?? 'Member' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-100 py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-xs text-gray-500">
            &copy; {{ date('Y') }} Daffodil International University. Faculty Directory. All rights reserved.
        </div>
    </footer>
</body>
</html>
