<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Directory - DIU FMS (Modern Dark)</title>
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
<body class="bg-gray-950 text-gray-200 min-h-screen flex flex-col font-sans antialiased selection:bg-brand-500 selection:text-white">

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
            
            <a href="{{ url('/admin/login') }}" class="px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-semibold text-sm transition duration-300 shadow-md shadow-brand-600/20">
                Portal Login
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
        <!-- Hero Section -->
        <section class="py-20 px-4 text-center relative overflow-hidden">
            <div class="absolute inset-0 bg-brand-950 opacity-30 blur-[150px] -z-10 w-96 h-96 mx-auto rounded-full"></div>
            
            <div class="max-w-4xl mx-auto">
                <span class="px-4 py-1.5 bg-brand-600/10 text-brand-500 border border-brand-500/20 rounded-full text-xs font-semibold tracking-wide uppercase">
                    Theme: Modern Dark Mode
                </span>
                <h1 class="mt-6 text-4xl sm:text-5xl font-extrabold text-white tracking-tight leading-tight">
                    Next-Gen Faculty <span class="bg-gradient-to-r from-brand-500 to-indigo-400 bg-clip-text text-transparent">Information Directory</span>
                </h1>
                <p class="mt-4 text-gray-400 text-base max-w-lg mx-auto">
                    Browse the academic departments and faculties shaping the future of Daffodil International University.
                </p>
            </div>
        </section>

        <!-- Dynamic Content Section -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                
                <!-- Sidebar: Faculties -->
                <div class="lg:col-span-1">
                    <div class="bg-gray-900/30 border border-gray-900 p-6 rounded-3xl">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-brand-500 mb-4">
                            Faculties
                        </h3>
                        <div class="space-y-1">
                            @foreach($faculties as $faculty)
                                <a 
                                    href="{{ url('/?faculty=' . strtolower($faculty->short_name)) }}" 
                                    class="block text-left px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ ($selectedFaculty && $selectedFaculty->id === $faculty->id) ? 'bg-brand-600 text-white shadow-md' : 'text-gray-400 hover:text-white hover:bg-gray-900/50' }}"
                                >
                                    {{ $faculty->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Main Area: Departments -->
                <div class="lg:col-span-3">
                    @if($departments->isEmpty())
                        <div class="bg-gray-900/30 border border-gray-900 p-12 text-center rounded-3xl">
                            <p class="text-gray-400 italic">No departments listed for this faculty.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($departments as $dept)
                                <div class="bg-gray-900/30 border border-gray-900 hover:border-brand-500/40 p-6 rounded-3xl hover:bg-gray-900/60 transition duration-300 flex flex-col justify-between">
                                    <div>
                                        <div class="w-10 h-10 rounded-xl bg-brand-600/10 text-brand-500 flex items-center justify-center font-bold text-lg mb-4">
                                            {{ substr($dept->name, 0, 1) }}
                                        </div>
                                        <h3 class="text-white font-bold text-base leading-snug">{{ $dept->name }}</h3>
                                    </div>
                                    <a href="{{ url('/departments/' . $dept->code) }}" class="mt-6 w-full py-2 bg-gray-800 hover:bg-brand-600 text-white text-center rounded-xl text-xs font-bold transition duration-300 block">
                                        Browse Directory
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-900 py-8 mt-12 bg-gray-950">
        <div class="max-w-7xl mx-auto px-4 text-center text-xs text-gray-500">
            &copy; {{ date('Y') }} Daffodil International University. Next-Gen FMS Directory.
        </div>
    </footer>
</body>
</html>
