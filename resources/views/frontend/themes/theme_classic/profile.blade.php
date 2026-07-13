<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $teacher->first_name }} {{ $teacher->last_name }} - Profile (Classic)</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        serif: ['"Merriweather"', 'serif'],
                        display: ['"Playfair Display"', 'serif'],
                    },
                    colors: {
                        royal: {
                            50: '#f0f4fe',
                            100: '#e1e9fd',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Merriweather', serif;
        }
    </style>
</head>
<body class="bg-[#F8F9FA] text-[#212529] min-h-screen flex flex-col antialiased">

    <!-- Header -->
    <header class="bg-royal-900 text-white py-6 shadow-md border-b-4 border-royal-700">
        <div class="max-w-6xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="text-center md:text-left">
                <span class="text-2xl font-serif font-bold tracking-tight font-display">Daffodil International University</span>
                <p class="text-xs uppercase tracking-wider text-royal-100 font-semibold mt-1">Faculty & Scholar Directory</p>
            </a>
            
            <a href="{{ url('/') }}" class="text-xs uppercase tracking-wider font-bold text-royal-100 hover:text-white transition">
                &larr; Back to Directory
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow max-w-5xl mx-auto w-full px-4 py-12">
        
        <!-- Profile Box -->
        <div class="bg-white border border-gray-200 shadow-sm p-8 md:p-12 mb-8">
            <div class="flex flex-col md:flex-row items-center md:items-start gap-8">
                <!-- Photo -->
                <div class="w-32 h-32 bg-gray-100 border border-gray-200 shrink-0">
                    @if($teacher->photo)
                        <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover" />
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-royal-50 text-royal-800 font-bold text-3xl">
                            {{ substr($teacher->first_name, 0, 1) }}
                        </div>
                    @endif
                </div>

                <!-- Info -->
                <div class="text-center md:text-left flex-grow">
                    <h2 class="text-3xl font-display font-bold text-gray-900">
                        {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
                    </h2>
                    <p class="text-sm text-royal-700 font-bold uppercase tracking-wider mt-2">
                        {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
                    </p>
                    <p class="text-xs text-gray-500 font-medium mt-1">
                        Department of {{ optional($teacher->department)->name ?? 'General' }}
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6 text-sm text-left bg-gray-50 p-4 border border-gray-100">
                        <div>
                            <span class="text-xs font-bold text-gray-400 uppercase">Email Address</span>
                            <p class="text-gray-800 font-medium break-all">{{ $teacher->secondary_email ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-gray-400 uppercase">Contact Phone</span>
                            <p class="text-gray-800 font-medium">{{ $teacher->phone ?? 'N/A' }}</p>
                        </div>
                        @if($teacher->webpage)
                            <div class="md:col-span-2">
                                <span class="text-xs font-bold text-gray-400 uppercase">Academic Webpage</span>
                                <p class="text-royal-800 font-medium break-all">
                                    <a href="{{ $teacher->webpage }}" target="_blank" class="hover:underline">{{ $teacher->webpage }}</a>
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Dossier -->
        <div class="space-y-8">
            <!-- Biography -->
            @if($teacher->bio || $teacher->research_interest)
                <div class="bg-white border border-gray-200 p-8 shadow-sm">
                    <h3 class="text-lg font-display font-bold text-royal-900 border-b border-royal-700/20 pb-2 mb-4">
                        Biography & Research
                    </h3>
                    @if($teacher->bio)
                        <p class="text-gray-700 text-sm leading-relaxed whitespace-pre-line">{{ $teacher->bio }}</p>
                    @endif
                    @if($teacher->research_interest)
                        <div class="mt-4 p-4 border-l-4 border-royal-800 bg-gray-50">
                            <span class="text-xs font-bold text-royal-900 uppercase">Keywords / Fields</span>
                            <p class="text-gray-600 text-sm italic mt-1">"{{ $teacher->research_interest }}"</p>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Educations -->
            @if(method_exists($teacher, 'educations') && $teacher->educations && $teacher->educations->isNotEmpty())
                <div class="bg-white border border-gray-200 p-8 shadow-sm">
                    <h3 class="text-lg font-display font-bold text-royal-900 border-b border-royal-700/20 pb-2 mb-4">
                        Academic Qualifications
                    </h3>
                    <ul class="space-y-6">
                        @foreach($teacher->educations as $edu)
                            <li>
                                <h4 class="font-bold text-gray-900">{{ $edu->degree_name ?? 'Degree' }}</h4>
                                <p class="text-sm text-gray-700">{{ $edu->institution_name ?? $edu->educational_institution_id }}</p>
                                <p class="text-xs text-gray-500 mt-1">Graduation Year: {{ $edu->passing_year ?? 'N/A' }} | Result Score: {{ $edu->result ?? 'N/A' }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-royal-900 text-royal-100 py-8 border-t-4 border-royal-700 mt-12">
        <div class="max-w-6xl mx-auto px-4 text-center text-xs">
            &copy; {{ date('Y') }} Daffodil International University. Legacy Academic Directory.
        </div>
    </footer>
</body>
</html>
