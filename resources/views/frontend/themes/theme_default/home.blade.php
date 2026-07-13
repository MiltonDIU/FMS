<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Directory - DIU FMS</title>
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
    <style>
        .glass-header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(229, 231, 235, 0.5);
        }
        .hero-gradient {
            background: radial-gradient(circle at 10% 20%, rgba(218, 253, 224, 0.3) 0%, rgba(244, 252, 246, 0.1) 90%);
        }
    </style>
</head>
<body class="bg-[#FAFDFB] text-[#1D2921] min-h-screen flex flex-col font-sans antialiased">

    <!-- Header -->
    <header class="sticky top-0 z-50 glass-header">
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
            
            <a href="{{ url('/admin/login') }}" class="px-5 py-2.5 bg-gray-900 text-white rounded-xl font-semibold text-sm hover:bg-gray-800 transition duration-300 shadow-sm">
                Portal Login
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
        <!-- Hero Section -->
        <section class="hero-gradient py-16 px-4 border-b border-gray-100">
            <div class="max-w-4xl mx-auto text-center">
                <span class="px-4 py-1.5 bg-primary-100 text-primary-700 rounded-full text-xs font-semibold tracking-wide uppercase">
                    Theme: Default Clean
                </span>
                <h1 class="mt-6 text-4xl sm:text-5xl font-extrabold text-gray-900 tracking-tight leading-tight">
                    Explore Our Distinguished <span class="text-primary-600">Faculty Members</span>
                </h1>
                <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">
                    Search and connect with professors, researchers, and administrative leaders at Daffodil International University.
                </p>

                <!-- Search & Filters -->
                <form method="GET" action="{{ url('/') }}" class="mt-8 max-w-2xl mx-auto bg-white p-3 rounded-2xl shadow-xl shadow-gray-100/50 border border-gray-100 flex flex-col sm:flex-row gap-2">
                    <div class="flex-grow relative">
                        <input 
                            type="text" 
                            name="search" 
                            value="{{ request('search') }}"
                            placeholder="Search by name, research interest, bio..." 
                            class="w-full pl-5 pr-4 py-3 bg-transparent text-sm focus:outline-none text-gray-900"
                        />
                    </div>
                    <button type="submit" class="sm:px-8 py-3 bg-primary-600 text-white font-semibold rounded-xl text-sm hover:bg-primary-700 transition duration-300 shadow-lg shadow-primary-600/20">
                        Search Directory
                    </button>
                </form>
            </div>
        </section>

        <!-- Directory Grid -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            @if($teachers->isEmpty())
                <div class="text-center py-12">
                    <div class="text-gray-400 text-5xl mb-4">🔍</div>
                    <h3 class="text-lg font-semibold text-gray-900">No faculty members found</h3>
                    <p class="text-gray-500 mt-1">Try adjusting your search criteria or query.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($teachers as $teacher)
                        <div class="bg-white rounded-2xl border border-gray-100 hover:border-primary-100 p-6 flex flex-col items-center text-center hover:shadow-xl hover:shadow-primary-600/5 transition-all duration-300 group">
                            <!-- Photo -->
                            <div class="relative w-28 h-28 mb-4">
                                @if($teacher->photo)
                                    <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover rounded-2xl shadow-sm border border-gray-50" />
                                @else
                                    <div class="w-full h-full rounded-2xl bg-primary-50 flex items-center justify-center text-primary-700 font-extrabold text-2xl border border-primary-100">
                                        {{ substr($teacher->first_name, 0, 1) }}{{ substr($teacher->last_name, 0, 1) }}
                                    </div>
                                @endif
                                <span class="absolute bottom-1 right-1 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full"></span>
                            </div>

                            <!-- Identity -->
                            <h3 class="font-bold text-gray-900 group-hover:text-primary-600 transition duration-300">
                                {{ $teacher->first_name }} {{ $teacher->middle_name }} {{ $teacher->last_name }}
                            </h3>
                            <p class="text-xs text-gray-500 font-medium mt-0.5">
                                {{ optional($teacher->designation)->name ?? 'Faculty Member' }}
                            </p>
                            <p class="text-[11px] text-primary-700 bg-primary-50 px-2.5 py-1 rounded-full font-semibold mt-2">
                                {{ optional($teacher->department)->name ?? 'General' }}
                            </p>

                            <!-- Mini Bio -->
                            @if($teacher->research_interest)
                                <p class="text-xs text-gray-500 mt-4 line-clamp-2 italic">
                                    "{{ $teacher->research_interest }}"
                                </p>
                            @endif

                            <div class="w-full border-t border-gray-100 my-4"></div>

                            <!-- CTA -->
                            <a href="{{ url('/teachers/' . ($teacher->employee_id ?? $teacher->id)) }}" class="w-full py-2 bg-gray-50 hover:bg-primary-600 hover:text-white rounded-xl text-xs font-semibold text-gray-700 transition duration-300">
                                View Profile
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-100 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-xs text-gray-500">
            &copy; {{ date('Y') }} Daffodil International University. All rights reserved. Powered by DIU FMS.
        </div>
    </footer>
</body>
</html>
