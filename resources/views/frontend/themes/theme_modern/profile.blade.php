<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $teacher->first_name }} {{ $teacher->last_name }} - Profile (Modern)</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Outfit"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            950: '#0f0b1e',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-950 text-gray-200 min-h-screen flex flex-col font-sans antialiased">

    <!-- Header -->
    <header class="border-b border-gray-900 bg-gray-950/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-brand-600 flex items-center justify-center text-white font-extrabold text-xl shadow-lg shadow-brand-600/30">
                    F
                </div>
                <div>
                    <span class="text-xl font-bold tracking-tight text-white">FMS <span class="text-brand-500">Dark</span></span>
                    <p class="text-[9px] text-gray-400 font-semibold tracking-wide uppercase">Daffodil International University</p>
                </div>
            </a>
            
            <a href="{{ url('/') }}" class="text-sm font-semibold text-gray-400 hover:text-brand-500 transition">
                &larr; Back to Portal
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow max-w-6xl mx-auto w-full px-4 py-12">
        <div class="bg-gray-900/20 border border-gray-900 rounded-3xl p-8 md:p-12 mb-8 relative overflow-hidden">
            <div class="absolute right-0 top-0 w-64 h-64 bg-brand-600/10 blur-[100px] rounded-full"></div>
            
            <div class="flex flex-col md:flex-row items-center md:items-start gap-8 relative z-10">
                <!-- Photo -->
                <div class="w-32 h-32 bg-gray-800 rounded-3xl overflow-hidden border border-gray-700/50 shrink-0 shadow-lg">
                    @if($teacher->photo)
                        <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover" />
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-brand-600/10 text-brand-500 font-bold text-3xl">
                            {{ substr($teacher->first_name, 0, 1) }}
                        </div>
                    @endif
                </div>

                <!-- Info -->
                <div class="text-center md:text-left flex-grow">
                    <h2 class="text-3xl font-extrabold text-white">
                        {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
                    </h2>
                    <p class="text-sm font-semibold text-brand-500 mt-2 uppercase tracking-wider">
                        {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                        Department of {{ optional($teacher->department)->name ?? 'General' }}
                    </p>

                    <!-- Info Badges -->
                    <div class="flex flex-wrap gap-4 mt-6">
                        <div class="bg-gray-900/60 border border-gray-800 px-4 py-2 rounded-xl text-xs">
                            <span class="text-gray-500 block uppercase tracking-wider font-bold mb-0.5">Email</span>
                            <a href="mailto:{{ $teacher->secondary_email }}" class="text-white hover:text-brand-500 transition">{{ $teacher->secondary_email ?? 'N/A' }}</a>
                        </div>
                        @if($teacher->phone)
                            <div class="bg-gray-900/60 border border-gray-800 px-4 py-2 rounded-xl text-xs">
                                <span class="text-gray-500 block uppercase tracking-wider font-bold mb-0.5">Phone</span>
                                <span class="text-white">{{ $teacher->phone }}</span>
                            </div>
                        @endif
                        @if($teacher->webpage)
                            <div class="bg-gray-900/60 border border-gray-800 px-4 py-2 rounded-xl text-xs">
                                <span class="text-gray-500 block uppercase tracking-wider font-bold mb-0.5">Website</span>
                                <a href="{{ $teacher->webpage }}" target="_blank" class="text-brand-500 hover:underline break-all">{{ $teacher->webpage }}</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Dossier -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-8">
                <!-- Biography -->
                @if($teacher->bio)
                    <div class="bg-gray-900/20 border border-gray-900 rounded-3xl p-8">
                        <h3 class="text-lg font-bold text-white mb-4">Biography</h3>
                        <p class="text-gray-400 text-sm leading-relaxed whitespace-pre-line">{{ $teacher->bio }}</p>
                    </div>
                @endif

                <!-- Educations -->
                @if(method_exists($teacher, 'educations') && $teacher->educations && $teacher->educations->isNotEmpty())
                    <div class="bg-gray-900/20 border border-gray-900 rounded-3xl p-8">
                        <h3 class="text-lg font-bold text-white mb-6">Education</h3>
                        <div class="space-y-6 border-l-2 border-gray-800 pl-6">
                            @foreach($teacher->educations as $edu)
                                <div class="relative">
                                    <span class="absolute -left-[31px] top-1.5 w-3 h-3 rounded-full bg-brand-500 border-2 border-gray-900"></span>
                                    <h4 class="font-bold text-white text-sm">{{ $edu->degree_name ?? 'Degree' }}</h4>
                                    <p class="text-xs text-gray-400 mt-1">{{ $edu->institution_name ?? $edu->educational_institution_id }}</p>
                                    <p class="text-[10px] text-gray-500 mt-0.5">Graduated: {{ $edu->passing_year ?? 'N/A' }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Side Cards -->
            <div class="space-y-8">
                <!-- Research Interests -->
                @if($teacher->research_interest)
                    <div class="bg-gray-900/20 border border-gray-900 rounded-3xl p-8">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-brand-500 mb-4">Research Interests</h3>
                        <p class="text-gray-300 text-sm italic">"{{ $teacher->research_interest }}"</p>
                    </div>
                @endif
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-900 py-8 mt-12 bg-gray-950">
        <div class="max-w-7xl mx-auto px-4 text-center text-xs text-gray-500">
            &copy; {{ date('Y') }} Daffodil International University. Next-Gen FMS Directory.
        </div>
    </footer>
</body>
</html>
