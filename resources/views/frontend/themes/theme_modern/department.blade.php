<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $department->name }} - Faculty Directory (Modern Dark)</title>
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
            
            <a href="{{ url('/') }}" class="text-sm font-semibold text-gray-400 hover:text-brand-500 transition">
                &larr; Back to Directory
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Sidebar: Designations -->
            <div class="lg:col-span-1">
                <div class="bg-gray-900/30 border border-gray-900 p-6 rounded-3xl sticky top-28">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-brand-500 mb-4">
                        Roles
                    </h3>
                    <div class="space-y-1">
                        <a 
                            href="{{ url('/departments/' . $department->id) }}" 
                            class="block text-left px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ !request('designation') ? 'bg-brand-600 text-white shadow-md' : 'text-gray-400 hover:text-white hover:bg-gray-900/50' }}"
                        >
                            All Roles
                        </a>
                        @foreach($designations as $desig)
                            <a 
                                href="{{ url('/departments/' . $department->id . '?designation=' . $desig->id) }}" 
                                class="block text-left px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ (request('designation') == $desig->id) ? 'bg-brand-600 text-white shadow-md' : 'text-gray-400 hover:text-white hover:bg-gray-900/50' }}"
                            >
                                {{ $desig->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Content Area: Faculty Members -->
            <div class="lg:col-span-3 space-y-8">
                <div>
                    <h1 class="text-2xl font-extrabold text-white leading-tight">
                        {{ $department->name }}
                    </h1>
                    <div class="border-b border-gray-900 my-4"></div>
                </div>

                @if($teachers->isEmpty())
                    <div class="bg-gray-900/30 border border-gray-900 p-12 text-center rounded-3xl">
                        <p class="text-gray-500 italic">No faculty members matched the active filters.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($teachers as $teacher)
                            <div class="bg-gray-900/30 border border-gray-900 hover:border-brand-500/40 p-6 rounded-3xl hover:bg-gray-900/60 transition duration-300 flex flex-col justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="w-14 h-14 bg-gray-800 rounded-2xl overflow-hidden border border-gray-700/50 shrink-0">
                                        @if($teacher->photo)
                                            <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover" />
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-brand-600/10 text-brand-500 font-bold text-lg">
                                                {{ substr($teacher->first_name, 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <h3 class="text-white font-bold text-sm leading-snug">
                                            {{ $teacher->first_name }} {{ $teacher->last_name }}
                                        </h3>
                                        <p class="text-xs text-brand-500 font-semibold mt-1">
                                            {{ optional($teacher->designation)->name ?? 'Faculty' }}
                                        </p>
                                    </div>
                                </div>
                                <a href="{{ url('/teachers/' . $teacher->webpage) }}" class="mt-6 w-full py-2 bg-gray-800 hover:bg-brand-600 text-white text-center rounded-xl text-xs font-bold transition duration-300 block">
                                    View Portfolio
                                </a>
                            </div>
                        @endforeach
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
