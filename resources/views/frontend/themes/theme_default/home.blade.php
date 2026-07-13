<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Directory - Daffodil International University</title>
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
        .hero-bg {
            background: radial-gradient(circle at 10% 20%, rgba(228, 239, 251, 0.4) 0%, rgba(255, 255, 255, 0) 80%);
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
            
            <nav class="hidden md:flex items-center space-x-8 text-sm font-semibold text-gray-600">
                <a href="{{ url('/') }}" class="text-diu-600">Home</a>
                <a href="#" class="hover:text-diu-600 transition">Forum</a>
                <a href="#" class="hover:text-diu-600 transition">Contact Us</a>
            </nav>

            <div class="flex items-center space-x-4">
                <!-- Theme Switcher placeholder -->
                <button class="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center hover:bg-gray-50 transition" title="Toggle theme">
                    🌓
                </button>
                <a href="https://daffodilvarsity.edu.bd" target="_blank" class="px-5 py-2.5 bg-gradient-to-r from-diu-600 to-diu-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider hover:opacity-90 transition duration-300 shadow-md shadow-diu-600/10">
                    Apply Now
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow">
        <!-- Hero Section -->
        <section class="hero-bg py-16 px-4 border-b border-gray-100/50">
            <div class="max-w-5xl mx-auto text-center">
                <h1 class="text-4xl sm:text-5xl font-extrabold text-diu-900 tracking-tight leading-none">
                    Welcome to the <span class="text-diu-600">DIU Faculty Directory</span>
                </h1>
                <p class="mt-4 text-base text-gray-600 max-w-2xl mx-auto">
                    Discover our distinguished faculty members, explore their research portfolios, and connect with academic departments.
                </p>
            </div>
        </section>

        <!-- Dynamic Faculties and Departments Grid -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                
                <!-- Left Sidebar: Filter by Faculty -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-4">
                            Filter by Faculty ({{ $faculties->count() }})
                        </h3>
                        <div class="space-y-1">
                            @foreach($faculties as $faculty)
                                <a 
                                    href="{{ url('/' . strtolower($faculty->short_name)) }}" 
                                    class="w-full text-left px-4 py-3 rounded-xl text-sm font-semibold flex items-center justify-between transition-all duration-200 {{ ($selectedFaculty && $selectedFaculty->id === $faculty->id) ? 'bg-diu-50 text-diu-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                                >
                                    <span>{{ $faculty->name }}</span>
                                    @if($selectedFaculty && $selectedFaculty->id === $faculty->id)
                                        <span class="text-xs bg-diu-600/10 text-diu-700 px-2 py-0.5 rounded-md font-bold">&bull;</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Right Side: Departments Grid -->
                <div class="lg:col-span-3 space-y-6">
                    <div>
                        <span class="text-[10px] bg-diu-600/10 text-diu-700 font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">
                            Faculty Active
                        </span>
                        <h2 class="text-2xl font-extrabold text-gray-900 mt-2">
                            {{ $selectedFaculty ? $selectedFaculty->name : 'Departments' }}
                        </h2>
                    </div>

                    @if($departments->isEmpty())
                        <div class="bg-white border border-gray-100 rounded-3xl p-12 text-center shadow-sm">
                            <p class="text-gray-500 font-semibold">No departments found under this faculty.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($departments as $dept)
                                <a 
                                    href="{{ url('/' . strtolower($selectedFaculty->short_name) . '/' . strtolower($dept->code)) }}" 
                                    class="bg-white border border-gray-100 hover:border-diu-100 p-6 rounded-3xl shadow-sm hover:shadow-xl hover:shadow-diu-600/5 transition-all duration-300 group flex flex-col justify-between"
                                >
                                    <div>
                                        <!-- Badge/Icon placeholder -->
                                        <div class="w-12 h-12 rounded-2xl bg-diu-50 flex items-center justify-center text-diu-600 font-bold text-lg mb-4 group-hover:scale-110 transition duration-300">
                                            {{ substr($dept->name, 0, 1) }}
                                        </div>
                                        <h3 class="font-extrabold text-gray-900 group-hover:text-diu-600 transition duration-200">
                                            {{ $dept->name }}
                                        </h3>
                                    </div>
                                    <div class="mt-6 flex items-center text-xs font-bold text-diu-600 group-hover:underline">
                                        <span>View Directory</span>
                                        <span class="ml-1.5 transition-transform duration-200 group-hover:translate-x-1">&rarr;</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-100 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-xs text-gray-500">
            &copy; {{ date('Y') }} Daffodil International University. Faculty Directory. All rights reserved.
        </div>
    </footer>
</body>
</html>
