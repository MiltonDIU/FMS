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
                    Meet the educators, creators, and innovators shaping the future of Daffodil International University.
                </p>
            </div>
        </section>

        <!-- Directory Grid -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            @if($teachers->isEmpty())
                <div class="text-center py-12">
                    <h3 class="text-lg font-semibold text-gray-300">No instructors matching the query</h3>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($teachers as $teacher)
                        <div class="bg-gray-900/40 border border-gray-900 hover:border-brand-500/40 p-6 rounded-3xl hover:bg-gray-900/80 transition-all duration-300 group flex flex-col justify-between">
                            <div>
                                <!-- Image & Status -->
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-14 h-14 bg-gray-800 rounded-2xl overflow-hidden border border-gray-700/50">
                                        @if($teacher->photo)
                                            <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover" />
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-brand-600/10 text-brand-500 font-bold text-lg">
                                                {{ substr($teacher->first_name, 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <span class="px-2.5 py-1 bg-brand-600/10 text-brand-500 text-[10px] font-bold rounded-lg uppercase tracking-wide">
                                        {{ optional($teacher->department)->name ?? 'Dept' }}
                                    </span>
                                </div>

                                <!-- Body -->
                                <h3 class="text-white font-bold text-md group-hover:text-brand-500 transition duration-300">
                                    {{ $teacher->first_name }} {{ $teacher->last_name }}
                                </h3>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ optional($teacher->designation)->name ?? 'Faculty' }}
                                </p>
                            </div>

                            <a href="{{ url('/teachers/' . ($teacher->employee_id ?? $teacher->id)) }}" class="mt-6 w-full py-2.5 bg-gray-800/80 hover:bg-brand-600 text-white hover:shadow-lg hover:shadow-brand-600/20 text-center rounded-xl text-xs font-bold transition duration-300 block">
                                Show Portfolio
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
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
