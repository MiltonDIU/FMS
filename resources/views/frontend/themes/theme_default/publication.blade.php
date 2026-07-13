<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $publication->title }} - Publication Details</title>
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
            
            <a href="{{ url('/' . strtolower($faculty->short_name) . '/' . strtolower($department->code) . '/' . $teacher->webpage) }}" class="text-sm font-bold text-gray-600 hover:text-diu-600 transition">
                &larr; Back to Profile
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow max-w-5xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
        
        <!-- Breadcrumbs -->
        <div class="text-xs text-gray-500 font-semibold mb-8 flex flex-wrap items-center gap-2">
            <a href="{{ url('/') }}" class="hover:text-diu-600 transition">Home</a>
            <span>/</span>
            <a href="{{ url('/' . strtolower($faculty->short_name)) }}" class="hover:text-diu-600 transition">{{ $faculty->short_name }}</a>
            <span>/</span>
            <a href="{{ url('/' . strtolower($faculty->short_name) . '/' . strtolower($department->code)) }}" class="hover:text-diu-600 transition">{{ $department->code }}</a>
            <span>/</span>
            <a href="{{ url('/' . strtolower($faculty->short_name) . '/' . strtolower($department->code) . '/' . $teacher->webpage) }}" class="hover:text-diu-600 transition">{{ $teacher->first_name }} {{ $teacher->last_name }}</a>
            <span>/</span>
            <span class="text-diu-600 truncate max-w-xs">Publication Details</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Side: Publication Metadata -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm space-y-6">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">
                        Publication Metrics
                    </h3>

                    <div class="space-y-4">
                        @if($publication->journal_name)
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Journal / Venue</p>
                                <p class="text-sm font-extrabold text-gray-900 leading-snug">{{ $publication->journal_name }}</p>
                            </div>
                        @endif

                        @if($publication->publication_year)
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Year of Publication</p>
                                <p class="text-sm font-extrabold text-gray-900">{{ $publication->publication_year }}</p>
                            </div>
                        @endif

                        @if($publication->impact_factor)
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Impact Factor</p>
                                <p class="text-sm font-extrabold text-emerald-600">{{ $publication->impact_factor }}</p>
                            </div>
                        @endif

                        @if($publication->citescore)
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">CiteScore</p>
                                <p class="text-sm font-extrabold text-blue-600">{{ $publication->citescore }}</p>
                            </div>
                        @endif

                        @if($publication->h_index)
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">H-Index</p>
                                <p class="text-sm font-extrabold text-indigo-600">{{ $publication->h_index }}</p>
                            </div>
                        @endif

                        @if($publication->research_area)
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Research Area</p>
                                <p class="text-xs font-semibold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-md inline-block mt-1">
                                    {{ $publication->research_area }}
                                </p>
                            </div>
                        @endif
                    </div>

                    @if($publication->journal_link || $publication->paper_link)
                        <div class="border-t border-gray-100 pt-6 space-y-2">
                            @if($publication->paper_link)
                                <a href="{{ $publication->paper_link }}" target="_blank" class="w-full py-3 bg-diu-600 hover:bg-diu-700 text-white rounded-2xl text-center font-bold text-xs uppercase tracking-wider block transition duration-300 shadow-md shadow-diu-600/10">
                                    Open Paper Link
                                </a>
                            @endif
                            @if($publication->journal_link)
                                <a href="{{ $publication->journal_link }}" target="_blank" class="w-full py-3 bg-slate-50 hover:bg-slate-100 border border-gray-200 text-gray-700 rounded-2xl text-center font-bold text-xs uppercase tracking-wider block transition duration-300">
                                    Journal Website
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right Side: Publication Content -->
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white rounded-3xl border border-gray-100 p-8 shadow-sm space-y-6">
                    <span class="px-2.5 py-1 bg-diu-50 text-diu-700 text-[10px] font-bold rounded-lg uppercase tracking-wide">
                        {{ $publication->type ?? 'Research Publication' }}
                    </span>
                    
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-snug">
                        {{ $publication->title }}
                    </h1>

                    @if($publication->abstract)
                        <div class="border-t border-gray-100 pt-6">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-3">
                                Abstract
                            </h3>
                            <p class="text-gray-600 text-sm leading-relaxed text-justify">
                                {{ $publication->abstract }}
                            </p>
                        </div>
                    @endif

                    @if($publication->keywords)
                        <div class="border-t border-gray-100 pt-6">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-3">
                                Keywords
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach(explode(',', $publication->keywords) as $keyword)
                                    <span class="px-3 py-1 bg-slate-50 border border-gray-100 rounded-lg text-xs font-medium text-gray-600">
                                        {{ trim($keyword) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
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
