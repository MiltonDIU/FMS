<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $department->name }} - Faculty Directory (Classic)</title>
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
    <main class="flex-grow max-w-6xl mx-auto w-full px-4 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Sidebar: Designations -->
            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-200 p-6 shadow-sm">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-royal-900 border-b border-gray-100 pb-2 mb-4">
                        Roles
                    </h3>
                    <div class="space-y-1">
                        <a 
                            href="{{ url('/departments/' . $department->id) }}" 
                            class="block text-left px-3 py-2 rounded text-sm font-semibold transition {{ !request('designation') ? 'bg-royal-50 text-royal-900 border-l-4 border-royal-700 pl-2' : 'text-gray-600 hover:bg-gray-50' }}"
                        >
                            All Roles
                        </a>
                        @foreach($designations as $desig)
                            <a 
                                href="{{ url('/departments/' . $department->id . '?designation=' . $desig->id) }}" 
                                class="block text-left px-3 py-2 rounded text-sm font-semibold transition {{ (request('designation') == $desig->id) ? 'bg-royal-50 text-royal-900 border-l-4 border-royal-700 pl-2' : 'text-gray-600 hover:bg-gray-50' }}"
                            >
                                {{ $desig->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Content Area: Teachers -->
            <div class="lg:col-span-3 space-y-8">
                <div>
                    <h1 class="text-2xl font-display font-bold text-royal-900">
                        {{ $department->name }}
                    </h1>
                    <div class="border-b border-gray-200 my-4"></div>
                </div>

                @if($teachers->isEmpty())
                    <div class="bg-white border border-gray-200 p-12 text-center shadow-sm">
                        <p class="text-gray-500 italic">No faculty members found matching your filter.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($teachers as $teacher)
                            <div class="bg-white border border-gray-200 shadow-sm p-6">
                                <div class="flex items-start space-x-4">
                                    <div class="w-16 h-16 bg-gray-100 border border-gray-200 shrink-0">
                                        @if($teacher->photo)
                                            <img src="{{ $teacher->photo }}" alt="{{ $teacher->first_name }}" class="w-full h-full object-cover" />
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-royal-50 text-royal-800 font-bold text-lg">
                                                {{ substr($teacher->first_name, 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <h3 class="font-display font-bold text-gray-900">
                                            {{ $teacher->first_name }} {{ $teacher->last_name }}
                                        </h3>
                                        <p class="text-xs text-royal-700 font-bold uppercase tracking-wider mt-1">
                                            {{ optional($teacher->designation)->name ?? 'Faculty' }}
                                        </p>
                                        <a href="{{ url('/teachers/' . $teacher->webpage) }}" class="inline-block text-[11px] font-bold text-royal-800 hover:underline mt-4">
                                            View Academic CV &rarr;
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

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
