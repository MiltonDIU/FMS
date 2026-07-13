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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#FAFDFB] text-[#1D2921] min-h-screen flex flex-col font-sans antialiased">

    <!-- Header -->
    <header class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center text-white font-extrabold text-xl shadow-md shadow-primary-600/20">
                    F
                </div>
                <div>
                    <span class="text-xl font-bold tracking-tight text-gray-900">Faculty <span class="text-primary-600">Portal</span></span>
                    <p class="text-[10px] text-gray-500 font-medium tracking-wide uppercase">Daffodil International University</p>
                </div>
            </a>
            
            <a href="{{ url('/') }}" class="text-sm font-semibold text-gray-600 hover:text-primary-600 transition">
                &larr; Back to Directory
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column: Profile Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl border border-gray-100 p-8 flex flex-col items-center text-center shadow-lg shadow-gray-100/40 sticky top-28">
                    <!-- Photo -->
                    <div class="w-36 h-36 relative mb-6">
                        @if($teacher->photo)
                            <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover rounded-3xl shadow-md border-2 border-white" />
                        @else
                            <div class="w-full h-full rounded-3xl bg-primary-50 flex items-center justify-center text-primary-700 font-extrabold text-4xl border border-primary-100">
                                {{ substr($teacher->first_name, 0, 1) }}{{ substr($teacher->last_name, 0, 1) }}
                            </div>
                        @endif
                        <span class="absolute bottom-2 right-2 w-4 h-4 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>

                    <!-- Details -->
                    <h2 class="text-2xl font-extrabold text-gray-900">
                        {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
                    </h2>
                    <p class="text-sm font-semibold text-primary-700 bg-primary-50 px-3.5 py-1.5 rounded-full mt-3">
                        {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
                    </p>
                    <p class="text-xs text-gray-500 font-medium mt-2">
                        Department of {{ optional($teacher->department)->name ?? 'General' }}
                    </p>

                    <div class="w-full border-t border-gray-100 my-6"></div>

                    <!-- Contact Details -->
                    <div class="w-full space-y-4 text-left">
                        <div class="flex items-start space-x-3 text-sm">
                            <span class="text-gray-400 mt-0.5">📧</span>
                            <div class="break-all">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">University Email</p>
                                <a href="mailto:{{ $teacher->secondary_email }}" class="text-gray-900 font-medium hover:underline">{{ $teacher->secondary_email ?? 'N/A' }}</a>
                            </div>
                        </div>

                        @if($teacher->phone)
                            <div class="flex items-start space-x-3 text-sm">
                                <span class="text-gray-400 mt-0.5">📞</span>
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Contact Phone</p>
                                    <span class="text-gray-900 font-medium">{{ $teacher->phone }}</span>
                                </div>
                            </div>
                        @endif

                        @if($teacher->webpage)
                            <div class="flex items-start space-x-3 text-sm">
                                <span class="text-gray-400 mt-0.5">🌐</span>
                                <div class="break-all">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Personal Website</p>
                                    <a href="{{ $teacher->webpage }}" target="_blank" class="text-primary-700 font-medium hover:underline">{{ $teacher->webpage }}</a>
                                </div>
                            </div>
                        @endif

                        @if($teacher->present_address)
                            <div class="flex items-start space-x-3 text-sm">
                                <span class="text-gray-400 mt-0.5">📍</span>
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Office Location</p>
                                    <span class="text-gray-900 font-medium">{{ $teacher->present_address }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column: Professional Dossier -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- About & Bio -->
                @if($teacher->bio || $teacher->research_interest)
                    <div class="bg-white rounded-3xl border border-gray-100 p-8 shadow-sm">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <span class="w-1.5 h-6 bg-primary-600 rounded-full mr-3"></span>
                            Biography & Interests
                        </h3>
                        @if($teacher->bio)
                            <p class="text-gray-600 text-sm leading-relaxed whitespace-pre-line">{{ $teacher->bio }}</p>
                        @endif
                        
                        @if($teacher->research_interest)
                            <div class="mt-6 p-5 bg-primary-50/50 rounded-2xl border border-primary-50">
                                <h4 class="text-xs font-bold text-primary-800 uppercase tracking-wider mb-2">Research Focus</h4>
                                <p class="text-gray-700 text-sm italic">"{{ $teacher->research_interest }}"</p>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Academic background / Educations -->
                @if(method_exists($teacher, 'educations') && $teacher->educations && $teacher->educations->isNotEmpty())
                    <div class="bg-white rounded-3xl border border-gray-100 p-8 shadow-sm">
                        <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                            <span class="w-1.5 h-6 bg-primary-600 rounded-full mr-3"></span>
                            Educational Background
                        </h3>
                        <div class="relative pl-6 border-l-2 border-primary-100 space-y-8">
                            @foreach($teacher->educations as $edu)
                                <div class="relative">
                                    <span class="absolute -left-[31px] top-1.5 w-4 h-4 rounded-full bg-primary-600 border-4 border-white shadow-sm"></span>
                                    <h4 class="font-bold text-gray-900">{{ $edu->degree_name ?? 'Degree' }}</h4>
                                    <p class="text-sm text-gray-700 font-medium mt-1">{{ $edu->institution_name ?? $edu->educational_institution_id }}</p>
                                    <p class="text-xs text-gray-500 mt-1">Passed Year: {{ $edu->passing_year ?? 'N/A' }} | Result: {{ $edu->result ?? 'N/A' }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Job Experiences -->
                @if(method_exists($teacher, 'jobExperiences') && $teacher->jobExperiences && $teacher->jobExperiences->isNotEmpty())
                    <div class="bg-white rounded-3xl border border-gray-100 p-8 shadow-sm">
                        <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                            <span class="w-1.5 h-6 bg-primary-600 rounded-full mr-3"></span>
                            Professional Experience
                        </h3>
                        <div class="relative pl-6 border-l-2 border-primary-100 space-y-8">
                            @foreach($teacher->jobExperiences as $exp)
                                <div class="relative">
                                    <span class="absolute -left-[31px] top-1.5 w-4 h-4 rounded-full bg-primary-600 border-4 border-white shadow-sm"></span>
                                    <h4 class="font-bold text-gray-900">{{ $exp->designation ?? 'Role' }}</h4>
                                    <p class="text-sm text-gray-700 font-medium mt-1">{{ $exp->company_name ?? $exp->organization_id }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $exp->start_date ? date('M Y', strtotime($exp->start_date)) : 'N/A' }} &mdash; 
                                        {{ $exp->is_current ? 'Present' : ($exp->end_date ? date('M Y', strtotime($exp->end_date)) : 'N/A') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-100 py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-xs text-gray-500">
            &copy; {{ date('Y') }} Daffodil International University. All rights reserved. Powered by DIU FMS.
        </div>
    </footer>
</body>
</html>
